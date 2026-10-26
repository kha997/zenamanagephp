<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

/**
 * GAP-014b: NCR create notifies the assignee, resolved notifies the creator, closed notifies
 * the assignee; nobody is notified about their own action; failures are logged, never fatal.
 */
class InspectionNcrNotificationTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    protected Tenant $tenant;

    protected User $creator;

    protected User $assignee;

    protected array $creatorHeaders;

    protected array $assigneeHeaders;

    protected QcInspection $inspection;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = ['inspection.view', 'inspection.create', 'inspection.edit'];
        $this->tenant = Tenant::factory()->create();
        $this->creator = $this->createTenantUser($this->tenant, [], ['admin'], $permissions);
        $this->assignee = $this->createTenantUser($this->tenant, [], ['admin'], $permissions);

        $this->creatorHeaders = $this->authHeadersForUser($this->creator, $this->apiLoginToken($this->creator, $this->tenant));
        $this->assigneeHeaders = $this->authHeadersForUser($this->assignee, $this->apiLoginToken($this->assignee, $this->tenant));

        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->creator->id,
            'pm_id' => (string) $this->creator->id,
        ]);
        $plan = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->creator->id,
        ]);
        $this->inspection = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $plan->id,
            'inspector_id' => (string) $this->creator->id,
            'status' => 'failed',
            'title' => 'GAP-014b NCR notification inspection',
        ]);
    }

    private function storeUrl(): string
    {
        return route('api.zena.inspections.ncrs.store', ['inspection' => (string) $this->inspection->id], false);
    }

    private function statusUrl(string $ncrId): string
    {
        return route('api.zena.inspections.ncrs.update-status', ['inspection' => (string) $this->inspection->id, 'ncr' => $ncrId], false);
    }

    private function createNcr(?string $assigneeId): string
    {
        $payload = ['title' => 'Rebar spacing non-conformance', 'description' => 'Spacing does not match the drawing.'];
        if ($assigneeId !== null) {
            $payload['assigned_to'] = $assigneeId;
        }

        return (string) $this->withHeaders($this->creatorHeaders)->postJson($this->storeUrl(), $payload)
            ->assertStatus(201)->json('data.id');
    }

    private function moveTo(array $headers, string $ncrId, string $status): void
    {
        $this->withHeaders($headers)->patchJson($this->statusUrl($ncrId), ['status' => $status])->assertOk();
    }

    public function test_create_with_assignee_notifies_the_assignee(): void
    {
        $this->createNcr((string) $this->assignee->id);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->assignee->id,
            'type' => 'ncr_assigned',
            'event_key' => 'ncr.assigned',
        ]);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_resolved_notifies_the_creator_and_in_progress_sends_nothing(): void
    {
        $ncrId = $this->createNcr((string) $this->assignee->id);
        Notification::query()->delete();

        $this->moveTo($this->assigneeHeaders, $ncrId, 'in_progress');
        $this->assertSame(0, Notification::query()->count());

        $this->moveTo($this->assigneeHeaders, $ncrId, 'resolved');
        $this->assertDatabaseHas('notifications', [
            'user_id' => (string) $this->creator->id,
            'type' => 'ncr_resolved',
            'event_key' => 'ncr.resolved',
        ]);
    }

    public function test_closed_notifies_the_assignee(): void
    {
        $ncrId = $this->createNcr((string) $this->assignee->id);
        $this->moveTo($this->assigneeHeaders, $ncrId, 'in_progress');
        $this->moveTo($this->assigneeHeaders, $ncrId, 'resolved');
        Notification::query()->delete();

        $this->moveTo($this->creatorHeaders, $ncrId, 'closed');

        $this->assertDatabaseHas('notifications', [
            'user_id' => (string) $this->assignee->id,
            'type' => 'ncr_closed',
            'event_key' => 'ncr.closed',
        ]);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_no_self_notification(): void
    {
        $ncrId = $this->createNcr((string) $this->creator->id);
        $this->moveTo($this->creatorHeaders, $ncrId, 'in_progress');
        $this->moveTo($this->creatorHeaders, $ncrId, 'resolved');
        $this->moveTo($this->creatorHeaders, $ncrId, 'closed');

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_refused_transition_sends_nothing(): void
    {
        $ncrId = $this->createNcr((string) $this->assignee->id);
        Notification::query()->delete();

        $this->withHeaders($this->assigneeHeaders)->patchJson($this->statusUrl($ncrId), ['status' => 'resolved'])
            ->assertStatus(422);

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_notification_failure_is_logged_and_create_still_succeeds(): void
    {
        Log::spy();
        Notification::creating(function (): void {
            throw new \RuntimeException('simulated notification failure');
        });

        $ncrId = $this->createNcr((string) $this->assignee->id);

        $this->assertNotSame('', $ncrId);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'ncr.notification_failed'
                && ($context['ncr_id'] ?? null) === $ncrId);
    }

    protected function tearDown(): void
    {
        Notification::flushEventListeners();

        parent::tearDown();
    }
}
