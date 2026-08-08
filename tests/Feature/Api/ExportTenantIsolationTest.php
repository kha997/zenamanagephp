<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
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

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'is_active' => true,
        ]);
        $this->userA->assignRole('admin');

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'is_active' => true,
        ]);
        $this->userB->assignRole('admin');

        $this->userBAdmin = User::factory()->create([
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
        $projectA = CoreProject::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->create(['tenant_id' => $this->tenantB->id]);

        $taskA = CoreTask::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);
        $taskB = CoreTask::factory()->create([
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
        $projectA = CoreProject::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->create(['tenant_id' => $this->tenantB->id]);

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
        $projectA = CoreProject::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectB = CoreProject::factory()->create(['tenant_id' => $this->tenantB->id]);

        $taskA = CoreTask::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);
        CoreTask::factory()->create([
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
        $projectA = CoreProject::factory()->create(['tenant_id' => $this->tenantA->id]);
        CoreProject::factory()->create(['tenant_id' => $this->tenantB->id]);

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
        $projectB = CoreProject::factory()->create(['tenant_id' => $this->tenantB->id]);
        $taskB = CoreTask::factory()->create([
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

        $controller = new \App\Http\Controllers\Api\ExportController();
        $response = $controller->exportTasks($request);

        $this->assertSame(500, $response->status());
        $this->assertSame([], DB::getQueryLog(), 'No query must execute when tenant context is missing');
    }

    /** @test */
    public function tenant_mismatch_header_returns_403(): void
    {
        $projectA = CoreProject::factory()->create(['tenant_id' => $this->tenantA->id]);

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
}
