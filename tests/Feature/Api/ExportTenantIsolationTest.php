<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Services\ExportTenantProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\CoreProject\Models\Project as CoreProject;
use Src\CoreProject\Models\Task as CoreTask;
use Tests\TestCase;

class ExportTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private User $userBAdmin;
    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->createOne();
        $this->tenantB = Tenant::factory()->createOne();

        $this->userA = User::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        $this->userA->assignRole('admin');

        $this->userB = User::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'is_active' => true,
        ]);
        $this->userB->assignRole('admin');

        $this->userBAdmin = User::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'is_active' => true,
        ]);
        $this->userBAdmin->assignRole('admin');

        Storage::fake(config('filesystems.default'));
        $this->disk = config('filesystems.default');
    }

    private function actingAsExportUser(Tenant $tenant, User $user): array
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $response->json('data.token');

        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ];
    }

    private function export(string $resource, User $user, Tenant $tenant, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->actingAsExportUser($tenant, $user))
            ->postJson("/api/{$resource}/bulk/export", $payload);
    }

    private function storedPayload(\Illuminate\Testing\TestResponse $response, string $format): string
    {
        $this->assertContains($format, ['csv', 'json']);
        $response->assertOk()->assertJsonPath('success', true);

        return Storage::disk($this->disk)->get('exports/' . $response->json('data.filename'));
    }

    private function assertForeignIdsAbsent(string $payload, string ...$foreignIds): void
    {
        foreach ($foreignIds as $foreignId) {
            $this->assertStringNotContainsString($foreignId, $payload, "Foreign ID {$foreignId} must not appear in exported payload");
        }
    }

    // ------------------------------------------------------------------
    // Task 1: Trusted tenant and primary selection
    // ------------------------------------------------------------------

    /** @test */
    public function task_export_silently_filters_foreign_and_mixed_ids(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);

        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);
        $taskB = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'csv',
            'task_ids' => [$taskA->id, $taskB->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'csv');

        $this->assertStringContainsString($taskA->id, $payload);
        $this->assertForeignIdsAbsent($payload, $taskB->id);
    }

    /** @test */
    public function project_export_silently_filters_foreign_and_mixed_ids(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'csv',
            'project_ids' => [$projectA->id, $projectB->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'csv');

        $this->assertStringContainsString($projectA->name, $payload);
        $this->assertForeignIdsAbsent($payload, $projectB->id, $projectB->name);
    }

    /** @test */
    public function task_export_no_ids_returns_all_tenant_tasks(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);

        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);
        $taskB = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
            'name' => 'foreign-no-ids-task',
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'csv',
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'csv');

        $this->assertStringContainsString($taskA->id, $payload);
        $this->assertForeignIdsAbsent($payload, $taskB->id, 'foreign-no-ids-task');
    }

    /** @test */
    public function project_export_no_ids_returns_all_tenant_projects(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id, 'name' => 'foreign-no-ids-project']);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'csv',
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'csv');

        $this->assertStringContainsString($projectA->name, $payload);
        $this->assertForeignIdsAbsent($payload, $projectB->id, 'foreign-no-ids-project');
    }

    /** @test */
    public function foreign_project_filter_and_foreign_only_project_ids_return_empty(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);
        CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);

        $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'csv', 'filters' => ['project_id' => $projectB->id],
        ])->assertOk()->assertJsonPath('data.total_tasks', 0);

        $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'csv', 'project_ids' => [$projectB->id],
        ])->assertOk()->assertJsonPath('data.total_projects', 0);
    }

    /** @test */
    public function task_export_tenant_b_only_ids_returns_empty(): void
    {
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);
        $taskB = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'csv',
            'task_ids' => [$taskB->id],
        ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('data.total_tasks'));
    }

    /** @test */
    public function missing_request_tenant_attribute_fails_before_query(): void
    {
        $request = \Illuminate\Http\Request::create('/api/tasks/bulk/export', 'POST', ['format' => 'json']);

        DB::enableQueryLog();

        $controller = app(\App\Http\Controllers\Api\ExportController::class);
        $response = $controller->exportTasks($request);

        $this->assertSame(500, $response->status());
        $this->assertSame([], DB::getQueryLog(), 'No query must execute when tenant context is missing');
    }

    /** @test */
    public function tenant_mismatch_header_returns_403(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantB->id,
        ])->postJson('/api/auth/login', [
            'email' => $this->userA->email,
            'password' => 'password',
        ]);

        $token = $response->json('data.token');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantB->id,
        ])->postJson('/api/tasks/bulk/export', [
            'format' => 'csv',
        ])->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Task 2: Task-safe reference projection
    // ------------------------------------------------------------------

    /** @test */
    public function cross_tenant_project_makes_task_ineligible_in_every_format(): void
    {
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectB->id,
        ]);
        $staleProjectId = (string) \Illuminate\Support\Str::ulid();
        $staleTask = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id])->id,
        ]);
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('tasks')->where('id', $staleTask->id)->update(['project_id' => $staleProjectId]);
        $this->assertSame($staleProjectId, (string) CoreTask::query()->findOrFail($staleTask->id)->project_id);

        foreach (['csv', 'json', 'excel'] as $format) {
            $response = $this->export('tasks', $this->userA, $this->tenantA, [
                'format' => $format,
                'task_ids' => [$taskA->id, $staleTask->id],
            ]);

            $response->assertOk();
            $this->assertSame(0, $response->json('data.total_tasks'), "Format {$format} must exclude Task with foreign Project");
        }
    }

    /** @test */
    public function foreign_assignee_is_unassigned_and_never_emitted(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'assignee_id' => $this->userB->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'csv',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'csv');

        $this->assertStringContainsString('Unassigned', $payload);
        $this->assertForeignIdsAbsent($payload, $this->userB->id);

        $json = json_decode($this->storedPayload($this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json', 'task_ids' => [$taskA->id],
        ]), 'json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNull($json['tasks'][0]['assignee_id']);
    }

    /** @test */
    public function foreign_component_is_null_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $componentB = \Src\CoreProject\Models\Component::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id])->id,
        ]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'component_id' => $componentB->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString((string) $componentB->id, $payload);
    }

    /** @test */
    public function component_and_phase_references_require_the_same_project(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $componentA = \Src\CoreProject\Models\Component::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);
        $componentB = \Src\CoreProject\Models\Component::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectB->id,
        ]);
        $phaseA = (string) \Illuminate\Support\Str::ulid();
        $phaseB = (string) \Illuminate\Support\Str::ulid();
        DB::table('project_phases')->insert([
            ['id' => $phaseA, 'project_id' => $projectA->id, 'name' => 'Phase A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $phaseB, 'project_id' => $projectB->id, 'name' => 'Phase B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $valid = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);
        $valid->forceFill(['component_id' => $componentA->id, 'phase_id' => $phaseA])->save();
        $invalid = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);
        $invalid->forceFill(['component_id' => $componentB->id, 'phase_id' => $phaseB])->save();

        $this->assertSame((string) $componentB->id, (string) CoreTask::query()->findOrFail($invalid->id)->component_id);
        $this->assertSame($phaseB, (string) CoreTask::query()->findOrFail($invalid->id)->phase_id);

        $tasks = CoreTask::query()->with('project')->whereIn('id', [$valid->id, $invalid->id])->get();
        $rows = app(ExportTenantProjectionService::class)->projectTasks($tasks, (string) $this->tenantA->id)->keyBy('id');

        $this->assertSame((string) $componentA->id, $rows[$valid->id]['component_id']);
        $this->assertSame($phaseA, $rows[$valid->id]['phase_id']);
        $this->assertNull($rows[$invalid->id]['component_id']);
        $this->assertNull($rows[$invalid->id]['phase_id']);
    }

    /** @test */
    public function foreign_dependencies_are_filtered_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectA2 = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $valid = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);
        $crossProject = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA2->id]);
        $taskB = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id])->id,
        ]);
        $stale = (string) \Illuminate\Support\Str::ulid();
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'dependencies_json' => [$valid->id, $crossProject->id, $taskB->id, $stale],
        ]);
        $this->assertSame([$valid->id, $crossProject->id, $taskB->id, $stale], CoreTask::query()->findOrFail($taskA->id)->dependencies_json);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');
        $dependencies = json_decode($payload, true, flags: JSON_THROW_ON_ERROR)['tasks'][0]['dependencies_json'];
        $this->assertSame([(string) $valid->id], $dependencies);
    }

    /** @test */
    public function foreign_assigned_to_is_null_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'assigned_to' => $this->userB->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString($this->userB->id, $payload);
    }

    /** @test */
    public function foreign_created_by_is_null_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'created_by' => $this->userB->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString($this->userB->id, $payload);
    }

    /** @test */
    public function foreign_updated_by_is_null_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'updated_by' => $this->userB->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString($this->userB->id, $payload);
    }

    /** @test */
    public function foreign_watchers_are_filtered_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'watchers' => [$this->userB->id],
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString($this->userB->id, $payload);
    }

    /** @test */
    public function foreign_parent_task_is_null_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'parent_id' => CoreTask::factory()->createOne([
                'tenant_id' => $this->tenantB->id,
                'project_id' => CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id])->id,
            ])->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString($taskA->parent_id, $payload);
    }

    /** @test */
    public function foreign_work_instance_is_null_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $foreignInstance = WorkInstance::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
        ]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'work_instance_id' => $foreignInstance->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString($foreignInstance->id, $payload);
    }

    /** @test */
    public function foreign_work_instance_step_is_null_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $foreignStep = WorkInstanceStep::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'work_instance_id' => WorkInstance::factory()->createOne([
                'tenant_id' => $this->tenantB->id,
            ])->id,
        ]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'work_instance_step_id' => $foreignStep->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString($foreignStep->id, $payload);
    }

    /** @test */
    public function foreign_task_excluded_from_project_relation_and_aggregates(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);

        $taskB = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
        ]);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'csv',
            'project_ids' => [$projectA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'csv');

        $this->assertStringNotContainsString($taskB->id, $payload);
        $this->assertStringNotContainsString($projectB->id, $payload);
    }

    /** @test */
    public function task_json_contains_safe_project_projection(): void
    {
        $projectA = CoreProject::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PROJECT-SAFE-LOOKUP',
            'name' => 'Distinctive Project Name',
            'description' => 'Distinctive nested project description',
        ]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');
        $project = json_decode($payload, true, flags: JSON_THROW_ON_ERROR)['tasks'][0]['project'];

        $this->assertSame((string) $projectA->id, $project['id']);
        $this->assertSame('PROJECT-SAFE-LOOKUP', $project['code']);
        $this->assertSame('Distinctive Project Name', $project['name']);
        $this->assertSame('Distinctive nested project description', $project['description']);
        $this->assertStringNotContainsString('"tasks_count"', $payload);
        $this->assertStringNotContainsString('"completed_tasks_count"', $payload);

        $csv = $this->storedPayload($this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'csv', 'task_ids' => [$taskA->id],
        ]), 'csv');
        $this->assertStringContainsString('Distinctive Project Name', $csv);
        $this->assertStringNotContainsString('N/A', $csv);
    }

    /** @test */
    public function watcher_candidates_are_sanitized_and_preserve_string_and_array_representations(): void
    {
        $project = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $stale = (string) \Illuminate\Support\Str::ulid();
        $stringTask = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $project->id]);
        DB::table('tasks')->where('id', $stringTask->id)->update([
            'watchers' => json_encode([$this->userA->id, $this->userB->id, $stale], JSON_THROW_ON_ERROR),
        ]);
        $persistedString = CoreTask::query()->with('project')->findOrFail($stringTask->id);
        $this->assertIsString($persistedString->getAttribute('watchers'));
        $this->assertSame(
            [(string) $this->userA->id, (string) $this->userB->id, $stale],
            json_decode($persistedString->getAttribute('watchers'), true, flags: JSON_THROW_ON_ERROR)
        );

        $arrayTask = CoreTask::query()->with('project')->findOrFail($stringTask->id);
        $arrayTask->setAttribute('watchers', [$this->userA->id, $this->userB->id, $stale]);
        $this->assertIsArray($arrayTask->getAttribute('watchers'));

        $service = app(ExportTenantProjectionService::class);
        $stringRow = $service->projectTasks(collect([$persistedString]), (string) $this->tenantA->id)->first();
        $arrayRow = $service->projectTasks(collect([$arrayTask]), (string) $this->tenantA->id)->first();

        $this->assertIsString($stringRow['watchers']);
        $this->assertSame([(string) $this->userA->id], json_decode($stringRow['watchers'], true, flags: JSON_THROW_ON_ERROR));
        $this->assertIsArray($arrayRow['watchers']);
        $this->assertSame([(string) $this->userA->id], $arrayRow['watchers']);

        $arrayTask->setAttribute('watchers', '{malformed-json');
        $malformed = $service->projectTasks(collect([$arrayTask]), (string) $this->tenantA->id)->first();
        $this->assertSame('[]', $malformed['watchers']);
    }

    /** @test */
    public function project_json_preserves_tasks_key_with_safe_children(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'json',
            'project_ids' => [$projectA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringContainsString('"tasks"', $payload);
        $this->assertStringContainsString($taskA->id, $payload);
    }

    /** @test */
    public function project_optional_foreign_users_become_null_without_excluding_project(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectA->forceFill(['client_id' => $this->userB->id, 'pm_id' => $this->userB->id, 'created_by' => $this->userB->id])->save();
        $persisted = CoreProject::query()->findOrFail($projectA->id);
        $this->assertSame((string) $this->userB->id, (string) $persisted->client_id);
        $this->assertSame((string) $this->userB->id, (string) $persisted->pm_id);
        $this->assertSame((string) $this->userB->id, (string) $persisted->created_by);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'json',
            'project_ids' => [$projectA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');
        $project = json_decode($payload, true, flags: JSON_THROW_ON_ERROR)['projects'][0];
        $this->assertSame((string) $projectA->id, $project['id']);
        $this->assertNull($project['client_id']);
        $this->assertNull($project['pm_id']);
        $this->assertNull($project['created_by']);
    }

    /** @test */
    public function project_csv_never_hydrates_tasks_relation(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        CoreTask::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);

        DB::enableQueryLog();

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'csv',
            'project_ids' => [$projectA->id],
        ]);

        $response->assertOk();

        $queries = DB::getQueryLog();
        $taskHydrationQueries = array_filter($queries, function (array $query): bool {
            $sql = strtolower($query['query']);
            return str_contains($sql, 'where `tasks`') || str_contains($sql, 'where `core_project_tasks`');
        });

        $this->assertEmpty($taskHydrationQueries, 'Project CSV must not hydrate tasks relation');
    }

    /** @test */
    public function tenant_like_override_is_ignored(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantA->id,
        ])->postJson('/api/auth/login', [
            'email' => $this->userA->email,
            'password' => 'password',
        ]);

        $token = $response->json('data.token');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantA->id,
        ])->postJson('/api/projects/bulk/export', [
            'format' => 'csv',
            'tenant_id' => $this->tenantB->id,
            'project_ids' => [$projectA->id, $projectB->id],
        ])->assertOk()->assertJsonPath('data.total_projects', 1);
    }

    /** @test */
    public function project_json_does_not_contain_csv_count_keys(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        CoreTask::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'json',
            'project_ids' => [$projectA->id],
        ]);

        if ($response->status() !== 200) {
            dd($response->json());
        }

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString('"tasks_count"', $payload);
        $this->assertStringNotContainsString('"completed_tasks_count"', $payload);
    }

    /** @test */
    public function valid_project_users_are_preserved_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $this->userA->id,
            'pm_id' => $this->userA->id,
            'created_by' => $this->userA->id,
        ]);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'json',
            'project_ids' => [$projectA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');
        $project = json_decode($payload, true, flags: JSON_THROW_ON_ERROR)['projects'][0];
        $this->assertSame((string) $this->userA->id, $project['client_id']);
        $this->assertSame((string) $this->userA->id, $project['pm_id']);
        $this->assertSame((string) $this->userA->id, $project['created_by']);
    }

    /** @test */
    public function project_json_excludes_unexpected_fields(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'json',
            'project_ids' => [$projectA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString('"template_id"', $payload);
    }

    /** @test */
    public function valid_assignee_is_preserved_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskValid = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);
        DB::table('tasks')
            ->where('id', $taskValid->id)
            ->update(['assignee_id' => $this->userA->id]);
        $this->assertSame((string) $this->userA->id, (string) CoreTask::query()->findOrFail($taskValid->id)->assignee_id);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskValid->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringContainsString($this->userA->id, $payload);
        $csv = $this->storedPayload($this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'csv', 'task_ids' => [$taskValid->id],
        ]), 'csv');
        $this->assertStringContainsString('User ' . $this->userA->id, $csv);
    }

    /** @test */
    public function task_and_project_future_attributes_are_excluded_while_project_metadata_round_trips(): void
    {
        $project = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $project->forceFill([
            'tags' => ['critical', 'client-visible'],
            'settings' => ['notifications' => true, 'require_approval' => false],
            'template_id' => (string) \Illuminate\Support\Str::ulid(),
        ])->save();
        $project->setAttribute('future_foreign_reference_id', (string) $this->userB->id);

        $createdTask = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $project->id]);
        $task = CoreTask::query()->findOrFail($createdTask->id);
        $task->setRelation('project', $project);
        $task->setAttribute('future_foreign_reference_id', (string) $this->userB->id);

        $this->assertSame((string) $this->userB->id, $project->getAttribute('future_foreign_reference_id'));
        $this->assertSame((string) $this->userB->id, $task->getAttribute('future_foreign_reference_id'));

        $service = app(ExportTenantProjectionService::class);
        $projectRow = $service->projectScalarRows(collect([$project]), (string) $this->tenantA->id)->first();
        $taskRow = $service->projectTasks(collect([$task]), (string) $this->tenantA->id)->first();

        $this->assertSame(['critical', 'client-visible'], $projectRow['tags']);
        $this->assertSame(['notifications' => true, 'require_approval' => false], $projectRow['settings']);
        $this->assertArrayNotHasKey('template_id', $projectRow);
        $this->assertArrayNotHasKey('future_foreign_reference_id', $projectRow);
        $this->assertArrayNotHasKey('future_foreign_reference_id', $taskRow);
        $this->assertArrayNotHasKey('template_id', $taskRow['project']);
    }

    /** @test */
    public function malformed_foreign_project_child_is_excluded_from_json_and_aggregate_counts(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'name' => 'trusted-child-marker',
            'status' => 'completed',
        ]);
        $taskB = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
            'name' => 'foreign-child-marker',
            'status' => 'completed',
        ]);
        $taskB->forceFill(['project_id' => $projectA->id])->save();

        $this->assertSame((string) $projectA->id, (string) CoreTask::query()->findOrFail($taskB->id)->project_id);
        $this->assertSame((string) $this->tenantB->id, (string) CoreTask::query()->findOrFail($taskB->id)->tenant_id);

        $json = $this->storedPayload($this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'json', 'project_ids' => [$projectA->id],
        ]), 'json');
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $children = $decoded['projects'][0]['tasks'];
        $this->assertSame([(string) $taskA->id], array_column($children, 'id'));
        $this->assertStringNotContainsString((string) $taskB->id, $json);
        $this->assertStringNotContainsString('foreign-child-marker', $json);

        $csv = $this->storedPayload($this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'csv', 'project_ids' => [$projectA->id],
        ]), 'csv');
        $rows = array_map('str_getcsv', preg_split('/\r?\n/', trim($csv)));
        $this->assertSame('1', $rows[1][12]);
        $this->assertSame('1', $rows[1][13]);
        $this->assertStringNotContainsString((string) $taskB->id, $csv);
        $this->assertStringNotContainsString('foreign-child-marker', $csv);
    }

    /** @test */
    public function persisted_foreign_assignment_never_enters_task_safe_projection_or_json(): void
    {
        $project = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $task = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $project->id]);
        $assignmentId = (string) \Illuminate\Support\Str::ulid();
        DB::table('task_assignments')->insert([
            'id' => $assignmentId,
            'tenant_id' => $this->tenantB->id,
            'task_id' => $task->id,
            'user_id' => $this->userB->id,
            'role' => 'assignee',
            'status' => 'assigned',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('task_assignments', ['id' => $assignmentId, 'tenant_id' => $this->tenantB->id]);

        $loaded = CoreTask::query()->with(['project', 'assignments'])->findOrFail($task->id);
        $safe = app(ExportTenantProjectionService::class)->projectTasks(collect([$loaded]), (string) $this->tenantA->id)->first();
        $this->assertArrayNotHasKey('assignments', $safe);
        $this->assertStringNotContainsString($assignmentId, json_encode($safe, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString((string) $this->userB->id, json_encode($safe, JSON_THROW_ON_ERROR));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $payload = $this->storedPayload($this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json', 'task_ids' => [$task->id],
        ]), 'json');
        $this->assertStringNotContainsString($assignmentId, $payload);
        $this->assertStringNotContainsString((string) $this->userB->id, $payload);
        $this->assertFalse(collect(DB::getQueryLog())->contains(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'task_assignments')
        ));
    }

    /** @test */
    public function project_json_fetches_children_once_for_multiple_projects(): void
    {
        $projects = CoreProject::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        foreach ($projects as $project) {
            CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $project->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'json', 'project_ids' => $projects->pluck('id')->all(),
        ]);
        $response->assertOk();

        $childQueries = collect(DB::getQueryLog())->filter(function (array $query): bool {
            $sql = strtolower($query['query']);
            return str_contains($sql, 'from "tasks"') && str_contains($sql, '"project_id" in');
        });
        $this->assertCount(1, $childQueries, 'Project JSON must use one grouped eligible child Task query.');
    }

    /** @test */
    public function task_excel_legacy_boundary_receives_only_safe_arrays(): void
    {
        $project = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $created = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $project->id]);
        $created->forceFill(['assignee_id' => $this->userB->id])->save();
        $task = CoreTask::query()->with('project')->findOrFail($created->id);
        $task->setRelation('assignments', collect([(object) ['tenant_id' => $this->tenantB->id, 'user_id' => $this->userB->id]]));

        $safeRows = app(ExportTenantProjectionService::class)->projectTasks(collect([$task]), (string) $this->tenantA->id);
        $row = $safeRows->first();
        $this->assertIsArray($row);
        $this->assertNotInstanceOf(CoreTask::class, $row);
        $this->assertNull($row['assignee_id']);
        $this->assertArrayNotHasKey('assignments', $row);
        $this->assertSame((string) $this->tenantA->id, $row['project']['tenant_id']);
        $this->assertStringNotContainsString((string) $this->userB->id, json_encode($row, JSON_THROW_ON_ERROR));

    }

    /** @test */
    public function task_user_and_parent_references_apply_positive_foreign_cross_project_and_stale_controls(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectA2 = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $validParent = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);
        $crossParent = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA2->id]);
        $foreignParent = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id])->id,
        ]);
        $stale = (string) \Illuminate\Support\Str::ulid();

        $makeSource = function (array $references) use ($projectA): CoreTask {
            $created = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);
            $task = CoreTask::query()->with('project')->findOrFail($created->id);
            $task->setRawAttributes(array_merge($task->getAttributes(), $references), true);
            return $task;
        };
        $valid = $makeSource([
            'assigned_to' => $this->userA->id, 'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id, 'parent_id' => $validParent->id,
        ]);
        $foreign = $makeSource([
            'assigned_to' => $this->userB->id, 'created_by' => $this->userB->id,
            'updated_by' => $stale, 'parent_id' => $foreignParent->id,
        ]);
        $cross = $makeSource(['parent_id' => $crossParent->id]);
        $staleSource = $makeSource(['parent_id' => $stale]);
        $this->assertSame((string) $this->userA->id, (string) $valid->getAttribute('assigned_to'));
        $this->assertSame((string) $this->userA->id, (string) $valid->getAttribute('created_by'));
        $this->assertSame((string) $this->userA->id, (string) $valid->getAttribute('updated_by'));
        $this->assertSame((string) $validParent->id, (string) $valid->getAttribute('parent_id'));
        $this->assertSame((string) $this->userB->id, (string) $foreign->getAttribute('assigned_to'));
        $this->assertSame((string) $this->userB->id, (string) $foreign->getAttribute('created_by'));
        $this->assertSame($stale, (string) $foreign->getAttribute('updated_by'));
        $this->assertSame((string) $foreignParent->id, (string) $foreign->getAttribute('parent_id'));
        $this->assertSame((string) $crossParent->id, (string) $cross->getAttribute('parent_id'));
        $this->assertSame($stale, (string) $staleSource->getAttribute('parent_id'));

        $rows = app(ExportTenantProjectionService::class)
            ->projectTasks(collect([$valid, $foreign, $cross, $staleSource]), (string) $this->tenantA->id)
            ->keyBy('id');
        $this->assertSame((string) $this->userA->id, $rows[$valid->id]['assigned_to']);
        $this->assertSame((string) $this->userA->id, $rows[$valid->id]['created_by']);
        $this->assertSame((string) $this->userA->id, $rows[$valid->id]['updated_by']);
        $this->assertSame((string) $validParent->id, $rows[$valid->id]['parent_id']);
        $this->assertNull($rows[$foreign->id]['assigned_to']);
        $this->assertNull($rows[$foreign->id]['created_by']);
        $this->assertNull($rows[$foreign->id]['updated_by']);
        $this->assertNull($rows[$foreign->id]['parent_id']);
        $this->assertNull($rows[$cross->id]['parent_id']);
        $this->assertNull($rows[$staleSource->id]['parent_id']);
    }

    /** @test */
    public function work_instance_and_step_references_require_tenant_project_and_exact_instance_chain(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $projectA2 = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $validInstance = WorkInstance::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);
        $otherInstance = WorkInstance::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);
        $wrongProject = WorkInstance::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA2->id]);
        $foreignInstance = WorkInstance::factory()->createOne(['tenant_id' => $this->tenantB->id]);
        $validStep = WorkInstanceStep::factory()->createOne(['tenant_id' => $this->tenantA->id, 'work_instance_id' => $validInstance->id]);
        $otherStep = WorkInstanceStep::factory()->createOne(['tenant_id' => $this->tenantA->id, 'work_instance_id' => $otherInstance->id]);
        $stale = (string) \Illuminate\Support\Str::ulid();

        $makeSource = function (string $instanceId, string $stepId) use ($projectA): CoreTask {
            $created = CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $projectA->id]);
            $task = CoreTask::query()->with('project')->findOrFail($created->id);
            $task->setRawAttributes(array_merge($task->getAttributes(), ['work_instance_id' => $instanceId, 'work_instance_step_id' => $stepId]), true);
            return $task;
        };
        $valid = $makeSource((string) $validInstance->id, (string) $validStep->id);
        $wrongProjectTask = $makeSource((string) $wrongProject->id, $stale);
        $foreignTask = $makeSource((string) $foreignInstance->id, $stale);
        $wrongChain = $makeSource((string) $validInstance->id, (string) $otherStep->id);
        $staleTask = $makeSource($stale, $stale);

        $this->assertSame((string) $validInstance->id, (string) $valid->getAttribute('work_instance_id'));
        $this->assertSame((string) $validStep->id, (string) $valid->getAttribute('work_instance_step_id'));
        $this->assertSame((string) $wrongProject->id, (string) $wrongProjectTask->getAttribute('work_instance_id'));
        $this->assertSame((string) $foreignInstance->id, (string) $foreignTask->getAttribute('work_instance_id'));
        $this->assertSame((string) $otherStep->id, (string) $wrongChain->getAttribute('work_instance_step_id'));
        $this->assertSame($stale, (string) $staleTask->getAttribute('work_instance_id'));
        $this->assertSame($stale, (string) $staleTask->getAttribute('work_instance_step_id'));

        $rows = app(ExportTenantProjectionService::class)
            ->projectTasks(collect([$valid, $wrongProjectTask, $foreignTask, $wrongChain, $staleTask]), (string) $this->tenantA->id)
            ->keyBy('id');
        $this->assertSame((string) $validInstance->id, $rows[$valid->id]['work_instance_id']);
        $this->assertSame((string) $validStep->id, $rows[$valid->id]['work_instance_step_id']);
        foreach ([$wrongProjectTask, $foreignTask, $staleTask] as $invalid) {
            $this->assertNull($rows[$invalid->id]['work_instance_id']);
            $this->assertNull($rows[$invalid->id]['work_instance_step_id']);
        }
        $this->assertSame((string) $validInstance->id, $rows[$wrongChain->id]['work_instance_id']);
        $this->assertNull($rows[$wrongChain->id]['work_instance_step_id']);
    }

    /** @test */
    public function project_excel_tabular_boundary_uses_safe_arrays_counts_and_no_hydrated_tasks(): void
    {
        $project = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        CoreTask::factory()->createOne(['tenant_id' => $this->tenantA->id, 'project_id' => $project->id, 'status' => 'completed']);
        $foreign = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id])->id,
            'status' => 'completed',
        ]);
        $foreign->forceFill(['project_id' => $project->id])->save();
        $bounded = CoreProject::query()->whereKey($project->id)->withCount([
            'tasks as tasks_count' => fn ($q) => $q->where('tenant_id', $this->tenantA->id),
            'tasks as completed_tasks_count' => fn ($q) => $q->where('tenant_id', $this->tenantA->id)->where('status', 'completed'),
        ])->get();
        $rows = app(ExportTenantProjectionService::class)->projectTabularRows($bounded, (string) $this->tenantA->id);
        $row = $rows->first();
        $this->assertIsArray($row);
        $this->assertNotInstanceOf(CoreProject::class, $row);
        $this->assertSame(1, $row['tasks_count']);
        $this->assertSame(1, $row['completed_tasks_count']);
        $this->assertArrayNotHasKey('tasks', $row);
        $this->assertFalse($bounded->first()->relationLoaded('tasks'));
    }
}
