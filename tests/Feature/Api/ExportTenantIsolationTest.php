<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
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
        CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'csv',
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'csv');

        $this->assertStringContainsString($taskA->id, $payload);
    }

    /** @test */
    public function project_export_no_ids_returns_all_tenant_projects(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id]);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'csv',
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'csv');

        $this->assertStringContainsString($projectA->name, $payload);
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

        foreach (['csv', 'json'] as $format) {
            $response = $this->export('tasks', $this->userA, $this->tenantA, [
                'format' => $format,
                'task_ids' => [$taskA->id],
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

    // Phase table does not exist in current schema; skip foreign-phase test
    // until the referenced table is introduced.

    /** @test */
    public function foreign_dependencies_are_filtered_in_json(): void
    {
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
        $taskB = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantB->id,
            'project_id' => CoreProject::factory()->createOne(['tenant_id' => $this->tenantB->id])->id,
        ]);
        $taskA = CoreTask::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'dependencies_json' => [$taskB->id],
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringNotContainsString($taskB->id, $payload);
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
        $projectA = CoreProject::factory()->createOne(['tenant_id' => $this->tenantA->id]);
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

        $this->assertStringContainsString('"project"', $payload);
        $this->assertStringContainsString('"id"', $payload);
        $this->assertStringContainsString('"name"', $payload);
        $this->assertStringNotContainsString('"tasks_count"', $payload);
        $this->assertStringNotContainsString('"completed_tasks_count"', $payload);
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
        $projectA = CoreProject::factory()->createOne([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $this->userB->id,
            'pm_id' => $this->userB->id,
            'created_by' => $this->userB->id,
        ]);

        $response = $this->export('projects', $this->userA, $this->tenantA, [
            'format' => 'json',
            'project_ids' => [$projectA->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringContainsString($projectA->name, $payload);
        $this->assertStringNotContainsString($this->userB->id, $payload);
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
            'project_ids' => [$projectB->id],
        ])->assertOk()->assertJsonPath('data.total_projects', 0);
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

        $this->assertStringContainsString($this->userA->id, $payload);
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
            'assignee_id' => $this->userA->id,
        ]);

        $response = $this->export('tasks', $this->userA, $this->tenantA, [
            'format' => 'json',
            'task_ids' => [$taskValid->id],
        ]);

        $response->assertOk();
        $payload = $this->storedPayload($response, 'json');

        $this->assertStringContainsString($this->userA->id, $payload);
    }
}