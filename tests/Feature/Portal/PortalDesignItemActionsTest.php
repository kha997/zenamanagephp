<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\DesignItem;
use App\Models\DesignItemRevision;
use App\Models\EventRecord;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PortalDesignItemActionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Account $account;
    private Project $project;
    private User $assignee;
    private DesignItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Warm-up CSRF token
        $this->get('/login');

        $this->tenant = Tenant::factory()->create(['slug' => 'zena-portal-actions']);
        $this->assignee = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang portal actions',
            'email' => 'portal-actions@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an portal actions',
            'code' => 'PRJ-PORTAL-ACT',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $this->account->id,
            'opportunity_name' => 'Co hoi portal actions',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $this->project->id,
            'sales_owner_id' => (string) $this->assignee->id,
            'created_by' => (string) $this->assignee->id,
        ]);

        $this->item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Phoi canh mat tien',
            'item_type' => 'concept',
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            'assigned_to' => (string) $this->assignee->id,
            'due_to_client_at' => now()->addDays(7),
            'created_by' => (string) $this->assignee->id,
        ]);

        $this->actingAs($this->account, 'client');
    }

    public function test_approve_happy_path(): void
    {
        // Check show page has approve button when sent_to_client
        $showPage = $this->get(route('portal.design-items.show', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]));
        $showPage->assertOk();
        $showPage->assertSee('Duyệt phương án');
        $showPage->assertSee('chỉnh sửa');

        $response = $this->post(route('portal.design-items.approve', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->item->refresh();
        $this->assertEquals(DesignItem::STATUS_APPROVED, $this->item->review_status);
        $this->assertEquals(DesignItem::EVIDENCE_CLIENT_PORTAL, $this->item->approval_evidence);

        $event = EventRecord::query()->where('aggregate_id', $this->item->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals($this->account->id, $event->payload['actor_account_id']);

        $notification = Notification::query()
            ->where('user_id', $this->assignee->id)
            ->where('type', 'portal_client_action')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Phoi canh mat tien', $notification->title);

        // After approval, show page should NOT have approve button
        $showPageAfter = $this->get(route('portal.design-items.show', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]));
        $showPageAfter->assertOk();
        $showPageAfter->assertDontSee('Duyệt phương án');
        $showPageAfter->assertSee('Bạn đã duyệt phương án này');
    }

    public function test_request_revision_happy_path(): void
    {
        $feedback = 'Mau mau phai doi mau xanh hon';

        $response = $this->post(route('portal.design-items.request-revision', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]), [
            'client_feedback_notes' => $feedback,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->item->refresh();
        $this->assertEquals(DesignItem::STATUS_REVISION_REQUESTED, $this->item->review_status);
        $this->assertEquals(1, $this->item->revision_count);

        $revision = DesignItemRevision::query()
            ->where('design_item_id', $this->item->id)
            ->first();
        $this->assertNotNull($revision);
        $this->assertEquals($feedback, $revision->client_feedback);
        $this->assertNull($revision->requested_by); // actor is account, not user

        // Show page should display revision history
        $showPage = $this->get(route('portal.design-items.show', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]));
        $showPage->assertOk();
        $showPage->assertSee('Sửa lần 1');
        $showPage->assertSee($feedback);
    }

    public function test_approve_wrong_status_returns_error(): void
    {
        $this->item->update(['review_status' => DesignItem::STATUS_DRAFT]);

        $response = $this->post(route('portal.design-items.approve', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('action');

        $this->item->refresh();
        $this->assertEquals(DesignItem::STATUS_DRAFT, $this->item->review_status);
    }

    public function test_cross_account_and_tenant_and_nonexistent_return_404(): void
    {
        // Other account in same tenant → 404 (anti-enumeration)
        $otherAccount = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang khac',
            'email' => 'other@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->actingAs($otherAccount, 'client');

        $this->post(route('portal.design-items.approve', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]))->assertNotFound();

        // Non-existent ID → 404
        $this->actingAs($this->account, 'client');

        $this->post(route('portal.design-items.approve', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => '01HZZZZZZZZZZZZZZZZZZZZZZZZZZ',
        ]))->assertNotFound();

        // Other tenant → middleware redirects to login (302), not 404
        $otherTenant = Tenant::factory()->create(['slug' => 'zena-other-tenant']);
        $this->get(route('portal.design-items.show', [
            'tenantSlug' => 'zena-other-tenant',
            'id' => $this->item->id,
        ]))->assertRedirect();
    }

    public function test_not_logged_in_redirects_to_portal_login(): void
    {
        auth()->guard('client')->logout();

        $this->get(route('portal.design-items.show', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]))->assertRedirect();
    }

    public function test_throttle_returns_429(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->post(route('portal.design-items.approve', [
                'tenantSlug' => 'zena-portal-actions',
                'id' => $this->item->id,
            ]));
        }

        $this->post(route('portal.design-items.approve', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]))->assertStatus(429);
    }

    public function test_item_without_assignee_succeeds_without_notification(): void
    {
        $this->item->update(['assigned_to' => null]);

        $response = $this->post(route('portal.design-items.approve', [
            'tenantSlug' => 'zena-portal-actions',
            'id' => $this->item->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->item->refresh();
        $this->assertEquals(DesignItem::STATUS_APPROVED, $this->item->review_status);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_dashboard_shows_pending_badge_for_sent_to_client_items(): void
    {
        $dashboard = $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-portal-actions']));
        $dashboard->assertOk();
        $dashboard->assertSee('Chờ bạn phản hồi');
        $dashboard->assertSee('Phoi canh mat tien');
    }
}
