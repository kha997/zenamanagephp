<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\EventRecord;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class TaskOverdueEscalationApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    private const CAPA_CONTEXT_TAG = 'inspection-ncr-capa';

    protected Tenant $tenant;
    protected User $actor;
    protected User $assignee;
    protected array $headers;
    protected array $assigneeHeaders;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['admin'], [
            'task.view',
            'task.escalate-overdue',
        ]);
        $this->assignee = $this->createTenantUser($this->tenant, [], ['admin'], [
            'notification.view',
        ]);

        $token = $this->apiLoginToken($this->actor, $this->tenant);
        $this->headers = $this->authHeadersForUser($this->actor, $token);
        $assigneeToken = $this->apiLoginToken($this->assignee, $this->tenant);
        $this->assigneeHeaders = $this->authHeadersForUser($this->assignee, $assigneeToken);

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
        ]);
    }

    public function test_can_trigger_overdue_capa_escalation_for_assigned_task(): void
    {
        $task = $this->createTask([
            'assigned_to' => (string) $this->assignee->id,
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson($this->escalateRoute($task));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.task_id', (string) $task->id)
            ->assertJsonPath('data.recipient_user_id', (string) $this->assignee->id)
            ->assertJsonPath('data.overdue_basis', 'end_date')
            ->assertJsonPath('data.context_tag', self::CAPA_CONTEXT_TAG);

        $this->assertSame(1, Notification::query()->count());
        $this->assertSame(1, EventRecord::query()->count());

        $notification = Notification::query()->firstOrFail();
        $this->assertSame((string) $this->tenant->id, (string) $notification->tenant_id);
        $this->assertSame((string) $this->project->id, (string) $notification->project_id);
        $this->assertSame((string) $this->assignee->id, (string) $notification->user_id);
        $this->assertSame('task_overdue_escalated', $notification->type);
        $this->assertSame('zena.task.overdue_escalated', $notification->event_key);
        $this->assertSame(Notification::CHANNEL_INAPP, $notification->channel);

        $eventRecord = EventRecord::query()->firstOrFail();
        $this->assertSame((string) $this->tenant->id, (string) $eventRecord->tenant_id);
        $this->assertSame((string) $this->project->id, (string) $eventRecord->project_id);
        $this->assertSame('task', $eventRecord->aggregate_type);
        $this->assertSame((string) $task->id, (string) $eventRecord->aggregate_id);
        $this->assertSame('zena.task.overdue_escalated', $eventRecord->event_key);
        $this->assertSame((string) $this->actor->id, (string) $eventRecord->actor_user_id);
        $this->assertSame('task_overdue_escalated', data_get($eventRecord->payload, 'notification.type'));
        $this->assertSame('inapp', data_get($eventRecord->payload, 'notification.channel'));
        $this->assertSame((string) $this->assignee->id, data_get($eventRecord->payload, 'notification.recipient_user_id'));
        $this->assertSame((string) $task->id, data_get($eventRecord->payload, 'task.id'));
        $this->assertSame('end_date', data_get($eventRecord->payload, 'context.overdue_basis'));
        $this->assertSame(self::CAPA_CONTEXT_TAG, data_get($eventRecord->payload, 'context.context_tag'));

        $audit = AuditLog::query()
            ->where('action', 'zena.task.escalate_overdue')
            ->where('entity_id', (string) $task->id)
            ->firstOrFail();

        $this->assertSame('task', $audit->entity_type);
        $this->assertSame((string) $this->tenant->id, (string) $audit->tenant_id);
        $this->assertSame((string) $this->project->id, (string) $audit->project_id);
        $this->assertSame((string) $this->actor->id, (string) $audit->user_id);
        $this->assertSame(200, (int) $audit->status_code);
        $this->assertSame('POST', $audit->method);
        $this->assertStringEndsWith('/escalate-overdue', (string) $audit->route);
    }

    public function test_overdue_escalation_notification_is_readable_from_canonical_notification_inbox_with_exact_tuple(): void
    {
        $task = $this->createTask([
            'assigned_to' => (string) $this->actor->id,
        ]);

        $this->withHeaders($this->headers)
            ->postJson($this->escalateRoute($task))
            ->assertOk();

        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.zena.notifications.index', ['type' => 'task_overdue_escalated'], false));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', (string) $this->actor->id)
            ->assertJsonPath('data.0.type', 'task_overdue_escalated')
            ->assertJsonPath('data.0.channel', 'inapp')
            ->assertJsonPath('data.0.event_key', 'zena.task.overdue_escalated')
            ->assertJsonPath('data.0.data.task_id', (string) $task->id)
            ->assertJsonPath('data.0.data.overdue_basis', 'end_date')
            ->assertJsonPath('data.0.data.context_tag', self::CAPA_CONTEXT_TAG);

        $this->assertSame(1, Notification::query()->count());
    }

    public function test_event_record_is_replayable_at_internal_test_level_only_for_the_proved_direct_in_app_tuple(): void
    {
        $task = $this->createTask([
            'assigned_to' => (string) $this->actor->id,
        ]);

        $this->withHeaders($this->headers)
            ->postJson($this->escalateRoute($task))
            ->assertOk();

        $eventRecord = EventRecord::query()->firstOrFail();

        $reconstructedTuple = [
            'event_key' => (string) $eventRecord->event_key,
            'notification.type' => data_get($eventRecord->payload, 'notification.type'),
            'notification.channel' => data_get($eventRecord->payload, 'notification.channel'),
            'notification.recipient_user_id' => data_get($eventRecord->payload, 'notification.recipient_user_id'),
            'data.task_id' => data_get($eventRecord->payload, 'task.id'),
            'data.overdue_basis' => data_get($eventRecord->payload, 'context.overdue_basis'),
            'data.context_tag' => data_get($eventRecord->payload, 'context.context_tag'),
        ];

        $this->assertSame([
            'event_key' => 'zena.task.overdue_escalated',
            'notification.type' => 'task_overdue_escalated',
            'notification.channel' => 'inapp',
            'notification.recipient_user_id' => (string) $this->actor->id,
            'data.task_id' => (string) $task->id,
            'data.overdue_basis' => 'end_date',
            'data.context_tag' => self::CAPA_CONTEXT_TAG,
        ], $reconstructedTuple);
    }

    public function test_rejects_task_that_is_not_overdue(): void
    {
        $task = $this->createTask([
            'assigned_to' => (string) $this->assignee->id,
            'end_date' => now()->addDay(),
        ]);

        $this->withHeaders($this->headers)
            ->postJson($this->escalateRoute($task))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Task is not overdue for escalation');

        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(0, EventRecord::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'zena.task.escalate_overdue')->count());
    }

    public function test_rejects_completed_task(): void
    {
        $task = $this->createTask([
            'assigned_to' => (string) $this->assignee->id,
            'status' => Task::STATUS_COMPLETED,
            'end_date' => now()->subDay(),
        ]);

        $this->withHeaders($this->headers)
            ->postJson($this->escalateRoute($task))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Completed CAPA task cannot be escalated');

        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(0, EventRecord::query()->count());
    }

    public function test_rejects_task_without_capa_context_marker(): void
    {
        $task = $this->createTask([
            'assigned_to' => (string) $this->assignee->id,
            'tags' => ['general-task'],
        ]);

        $this->withHeaders($this->headers)
            ->postJson($this->escalateRoute($task))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Task is not eligible for overdue CAPA escalation');

        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(0, EventRecord::query()->count());
    }

    public function test_rejects_overdue_capa_task_without_assignee(): void
    {
        $task = $this->createTask([
            'assigned_to' => null,
        ]);

        $this->withHeaders($this->headers)
            ->postJson($this->escalateRoute($task))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Overdue CAPA task has no assignee for escalation');

        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(0, EventRecord::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'zena.task.escalate_overdue')->count());
    }

    public function test_escalation_route_is_tenant_safe(): void
    {
        $task = $this->createTask([
            'assigned_to' => (string) $this->assignee->id,
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], [
            'task.view',
            'task.escalate-overdue',
        ]);
        $foreignToken = $this->apiLoginToken($foreignActor, $foreignTenant);
        $foreignHeaders = $this->authHeadersForUser($foreignActor, $foreignToken);

        $this->withHeaders($foreignHeaders)
            ->postJson($this->escalateRoute($task))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Task not found');

        $this->assertSame(0, EventRecord::query()->count());
    }

    public function test_escalation_route_enforces_rbac(): void
    {
        $task = $this->createTask([
            'assigned_to' => (string) $this->assignee->id,
        ]);

        $restricted = $this->createTenantUser($this->tenant, [], ['member'], ['task.view']);
        $restrictedToken = $this->apiLoginToken($restricted, $this->tenant);
        $restrictedHeaders = $this->authHeadersForUser($restricted, $restrictedToken);

        $this->withHeaders($restrictedHeaders)
            ->postJson($this->escalateRoute($task))
            ->assertStatus(403);
    }

    private function createTask(array $overrides = []): Task
    {
        return Task::query()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'CAPA overdue escalation task',
            'title' => 'CAPA overdue escalation task',
            'description' => 'Corrective action handoff from inspection-owned NCR.',
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_HIGH,
            'end_date' => now()->subDay(),
            'tags' => [self::CAPA_CONTEXT_TAG],
            'created_by' => (string) $this->actor->id,
        ], $overrides));
    }

    private function escalateRoute(Task $task): string
    {
        return route('api.zena.tasks.escalate-overdue', ['id' => (string) $task->id], false);
    }
}
