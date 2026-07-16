<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\EventRecord;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class TaskCreatedPipelineTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    protected Tenant $tenant;
    protected User $actor;
    protected Project $project;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['admin'], [
            'task.view',
            'task.create',
        ]);

        $token = $this->apiLoginToken($this->actor, $this->tenant);
        $this->headers = $this->authHeadersForUser($this->actor, $token);

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
        ]);
    }

    public function test_task_create_writes_audit_log_entry(): void
    {
        $this->withHeaders($this->headers)
            ->postJson(route('api.zena.tasks.store', [], false), [
                'project_id' => (string) $this->project->id,
                'name' => 'Audit pipeline test task',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $this->tenant->id,
            'action' => 'zena.task.created',
            'entity_type' => 'task',
        ]);
    }

    public function test_task_create_writes_event_record(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson(route('api.zena.tasks.store', [], false), [
                'project_id' => (string) $this->project->id,
                'name' => 'EventRecord pipeline test task',
            ])
            ->assertStatus(201);

        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('event_records', [
            'tenant_id' => (string) $this->tenant->id,
            'aggregate_type' => 'task',
            'aggregate_id' => $taskId,
            'event_key' => 'zena.task.created',
            'actor_user_id' => (string) $this->actor->id,
        ]);
    }

    public function test_task_create_with_assignee_sends_inapp_notification(): void
    {
        $assignee = $this->createTenantUser($this->tenant, [], ['member'], ['task.view']);

        $response = $this->withHeaders($this->headers)
            ->postJson(route('api.zena.tasks.store', [], false), [
                'project_id' => (string) $this->project->id,
                'name' => 'Notification pipeline test task',
                'assignee_id' => (string) $assignee->id,
            ])
            ->assertStatus(201);

        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $assignee->id,
            'type' => 'task_assigned',
            'channel' => Notification::CHANNEL_INAPP,
            'event_key' => 'zena.task.created',
        ]);
    }

    public function test_task_create_without_assignee_does_not_send_notification(): void
    {
        $countBefore = Notification::count();

        $this->withHeaders($this->headers)
            ->postJson(route('api.zena.tasks.store', [], false), [
                'project_id' => (string) $this->project->id,
                'name' => 'Unassigned pipeline test task',
            ])
            ->assertStatus(201);

        $this->assertSame($countBefore, Notification::count());
    }

    public function test_full_pipeline_end_to_end(): void
    {
        $assignee = $this->createTenantUser($this->tenant, [], ['member'], ['task.view']);

        $countAuditBefore = AuditLog::count();
        $countEventBefore = EventRecord::count();
        $countNotifBefore = Notification::count();

        $response = $this->withHeaders($this->headers)
            ->postJson(route('api.zena.tasks.store', [], false), [
                'project_id' => (string) $this->project->id,
                'name' => 'Full pipeline task',
                'assignee_id' => (string) $assignee->id,
            ])
            ->assertStatus(201);

        $taskId = $response->json('data.id');

        $this->assertSame($countAuditBefore + 1, AuditLog::count(), 'Audit log entry expected');
        $this->assertSame($countEventBefore + 1, EventRecord::count(), 'EventRecord entry expected');
        $this->assertSame($countNotifBefore + 1, Notification::count(), 'Notification entry expected');

        $eventRecord = EventRecord::where('aggregate_id', $taskId)->first();
        $this->assertNotNull($eventRecord);
        $this->assertSame('zena.task.created', $eventRecord->event_key);
        $this->assertSame($taskId, $eventRecord->payload['task']['id']);
        $this->assertSame((string) $assignee->id, $eventRecord->payload['task']['assigned_to']);
    }
}
