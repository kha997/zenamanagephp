<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ChangeRequest;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

/**
 * S3.2a — Broader approver/stakeholder notification semantics for canonical change requests.
 *
 * Canonical approver-discovery contract (S3.2a):
 *   - change_requests.assigned_to   = canonical single-approver field (direct-recipient on submit)
 *   - change_requests.requested_by  = requester (direct-recipient on approve/reject)
 *   - projects.pm_id                = project manager (broader stakeholder — notified on approve)
 *   - change_request_approvals table = canonical multi-approver owner surface (future expansion)
 *
 * Apply notification: explicitly deferred (no notification sent on apply).
 */
class ChangeRequestStakeholderNotificationTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    protected Tenant $tenant;
    protected User $approver;
    protected User $requester;
    protected User $pm;
    protected Project $project;
    protected array $approverHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->pm = $this->createTenantUser($this->tenant, [], ['admin'], [
            'change-request.view',
            'change-request.approve',
            'change-request.reject',
        ]);

        $this->requester = $this->createTenantUser($this->tenant, [], ['member'], [
            'change-request.view',
            'change-request.create',
            'change-request.submit',
        ]);

        $this->approver = $this->createTenantUser($this->tenant, [], ['admin'], [
            'change-request.view',
            'change-request.approve',
            'change-request.reject',
            'change-request.apply',
        ]);

        $token = $this->apiLoginToken($this->approver, $this->tenant);
        $this->approverHeaders = $this->authHeadersForUser($this->approver, $token);

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->approver->id,
            'pm_id' => (string) $this->pm->id,
        ]);
    }

    private function makeSubmittedCr(): ChangeRequest
    {
        return ChangeRequest::create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'S3.2a stakeholder test CR',
            'description' => 'Proving broader stakeholder fan-out on approve.',
            'change_type' => 'scope',
            'priority' => 'medium',
            'status' => ChangeRequest::STATUS_SUBMITTED,
            'requested_by' => (string) $this->requester->id,
            'assigned_to' => (string) $this->approver->id,
            'change_number' => 'CR-S3-2A-001',
            'requested_at' => now(),
        ]);
    }

    public function test_approve_notifies_requester_as_direct_recipient(): void
    {
        $cr = $this->makeSubmittedCr();

        $this->withHeaders($this->approverHeaders)
            ->postJson(route('api.zena.change-requests.approve', ['id' => (string) $cr->id], false))
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->requester->id,
            'type' => 'change_request_approved',
            'event_key' => 'change_request.approved',
        ]);
    }

    public function test_approve_notifies_project_pm_as_broader_stakeholder(): void
    {
        $cr = $this->makeSubmittedCr();

        $this->withHeaders($this->approverHeaders)
            ->postJson(route('api.zena.change-requests.approve', ['id' => (string) $cr->id], false))
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->pm->id,
            'type' => 'change_request_approved_pm',
            'event_key' => 'change_request.approved',
        ]);
    }

    public function test_approve_sends_two_notifications_when_pm_differs_from_requester(): void
    {
        $cr = $this->makeSubmittedCr();
        $countBefore = Notification::count();

        $this->withHeaders($this->approverHeaders)
            ->postJson(route('api.zena.change-requests.approve', ['id' => (string) $cr->id], false))
            ->assertOk();

        $this->assertSame($countBefore + 2, Notification::count(), 'Expected requester + PM notifications');
    }

    public function test_approve_sends_only_one_notification_when_pm_is_requester(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->approver->id,
            'pm_id' => (string) $this->requester->id,
        ]);

        $cr = ChangeRequest::create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'title' => 'CR where PM is requester',
            'description' => 'Single notification expected.',
            'change_type' => 'scope',
            'priority' => 'medium',
            'status' => ChangeRequest::STATUS_SUBMITTED,
            'requested_by' => (string) $this->requester->id,
            'assigned_to' => (string) $this->approver->id,
            'change_number' => 'CR-S3-2A-002',
            'requested_at' => now(),
        ]);

        $countBefore = Notification::count();

        $this->withHeaders($this->approverHeaders)
            ->postJson(route('api.zena.change-requests.approve', ['id' => (string) $cr->id], false))
            ->assertOk();

        $this->assertSame($countBefore + 1, Notification::count(), 'Expected only requester notification when PM = requester');
    }

    public function test_apply_does_not_send_any_notification(): void
    {
        $cr = ChangeRequest::create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Apply notification deferred test',
            'description' => 'Apply must not emit notifications.',
            'change_type' => 'scope',
            'priority' => 'medium',
            'status' => ChangeRequest::STATUS_APPROVED,
            'requested_by' => (string) $this->requester->id,
            'assigned_to' => (string) $this->approver->id,
            'approved_by' => (string) $this->approver->id,
            'change_number' => 'CR-S3-2A-003',
            'requested_at' => now(),
        ]);

        $countBefore = Notification::count();

        $this->withHeaders($this->approverHeaders)
            ->postJson(route('api.zena.change-requests.apply', ['id' => (string) $cr->id], false))
            ->assertOk();

        $this->assertSame($countBefore, Notification::count(), 'Apply must not create any notifications (explicitly deferred)');
    }
}
