<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\EventRecord;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class EventRecordOutboxApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    protected Tenant $tenant;
    protected User $actor;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['admin'], [
            'event-record.view',
            'task.view',
            'task.escalate-overdue',
        ]);

        $token = $this->apiLoginToken($this->actor, $this->tenant);
        $this->headers = $this->authHeadersForUser($this->actor, $token);
    }

    public function test_event_record_index_is_tenant_scoped(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
        ]);

        $own = EventRecord::create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'aggregate_type' => 'task',
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.task.test_event',
            'actor_user_id' => (string) $this->actor->id,
            'payload' => ['data' => 'canonical'],
            'occurred_at' => now(),
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'created_by' => (string) $foreignActor->id,
            'pm_id' => (string) $foreignActor->id,
        ]);
        EventRecord::create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'aggregate_type' => 'task',
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.task.foreign_event',
            'actor_user_id' => (string) $foreignActor->id,
            'payload' => ['data' => 'foreign'],
            'occurred_at' => now(),
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.zena.event-records.index', [], false));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', (string) $own->id)
            ->assertJsonPath('data.items.0.event_key', 'zena.task.test_event')
            ->assertJsonPath('data.items.0.aggregate_type', 'task');
    }

    public function test_event_record_index_filters_by_event_key(): void
    {
        EventRecord::create([
            'tenant_id' => (string) $this->tenant->id,
            'aggregate_type' => 'task',
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.task.created',
            'actor_user_id' => (string) $this->actor->id,
            'payload' => [],
            'occurred_at' => now(),
        ]);
        $escalated = EventRecord::create([
            'tenant_id' => (string) $this->tenant->id,
            'aggregate_type' => 'task',
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.task.overdue_escalated',
            'actor_user_id' => (string) $this->actor->id,
            'payload' => ['context' => 'capa'],
            'occurred_at' => now(),
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.event-records.index', ['event_key' => 'zena.task.overdue_escalated'], false))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', (string) $escalated->id);
    }

    public function test_event_record_index_filters_by_aggregate_type(): void
    {
        EventRecord::create([
            'tenant_id' => (string) $this->tenant->id,
            'aggregate_type' => 'rfi',
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.rfi.created',
            'actor_user_id' => (string) $this->actor->id,
            'payload' => [],
            'occurred_at' => now(),
        ]);
        $taskRecord = EventRecord::create([
            'tenant_id' => (string) $this->tenant->id,
            'aggregate_type' => 'task',
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.task.overdue_escalated',
            'actor_user_id' => (string) $this->actor->id,
            'payload' => [],
            'occurred_at' => now(),
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.event-records.index', ['aggregate_type' => 'task'], false))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', (string) $taskRecord->id);
    }

    public function test_event_record_show_is_tenant_safe(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
        ]);

        $record = EventRecord::create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'aggregate_type' => 'task',
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.task.test',
            'actor_user_id' => (string) $this->actor->id,
            'payload' => ['key' => 'value'],
            'occurred_at' => now(),
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.event-records.show', ['id' => (string) $record->id], false))
            ->assertOk()
            ->assertJsonPath('data.id', (string) $record->id)
            ->assertJsonPath('data.event_key', 'zena.task.test')
            ->assertJsonPath('data.payload.key', 'value');

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignRecord = EventRecord::create([
            'tenant_id' => (string) $foreignTenant->id,
            'aggregate_type' => 'task',
            'aggregate_id' => (string) \Illuminate\Support\Str::ulid(),
            'event_key' => 'zena.task.foreign',
            'actor_user_id' => (string) $foreignActor->id,
            'payload' => [],
            'occurred_at' => now(),
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.event-records.show', ['id' => (string) $foreignRecord->id], false))
            ->assertNotFound();
    }

    public function test_escalation_event_record_is_replayable_from_outbox(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
        ]);

        $assignee = $this->createTenantUser($this->tenant, [], ['member'], ['notification.view']);

        $task = Task::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'CAPA overdue task',
            'title' => 'CAPA overdue task',
            'description' => 'Overdue CAPA task for replay test',
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_HIGH,
            'end_date' => now()->subDay(),
            'tags' => ['inspection-ncr-capa'],
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $assignee->id,
        ]);

        $this->withHeaders($this->headers)
            ->postJson(route('api.zena.tasks.escalate-overdue', ['id' => (string) $task->id], false))
            ->assertOk();

        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.zena.event-records.index', [
                'event_key' => 'zena.task.overdue_escalated',
                'aggregate_type' => 'task',
            ], false));

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.event_key', 'zena.task.overdue_escalated')
            ->assertJsonPath('data.items.0.aggregate_type', 'task')
            ->assertJsonPath('data.items.0.aggregate_id', (string) $task->id)
            ->assertJsonPath('data.items.0.payload.task.id', (string) $task->id)
            ->assertJsonPath('data.items.0.payload.context.context_tag', 'inspection-ncr-capa')
            ->assertJsonPath('data.items.0.payload.notification.type', 'task_overdue_escalated');
    }

    public function test_event_record_index_enforces_rbac(): void
    {
        $restricted = $this->createTenantUser($this->tenant, [], ['member'], []);
        $token = $this->apiLoginToken($restricted, $this->tenant);
        $headers = $this->authHeadersForUser($restricted, $token);

        $this->withHeaders($headers)
            ->getJson(route('api.zena.event-records.index', [], false))
            ->assertForbidden();
    }
}
