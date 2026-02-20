<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompensationRouteInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_compensation_task_route_accepts_ulid_task_id(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $task = Task::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ])->getJson('/api/v1/compensation/tasks/' . $task->id);

        $this->assertNotSame(
            404,
            $response->status(),
            'ULID task ID must be routable for compensation endpoints.'
        );
    }

    public function test_compensation_task_route_blocks_cross_tenant_header_mismatch(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $taskA = Task::factory()->create([
            'tenant_id' => $tenantA->id,
            'project_id' => $projectA->id,
        ]);

        Sanctum::actingAs($userA, [], 'sanctum');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenantB->id,
        ])->getJson('/api/v1/compensation/tasks/' . $taskA->id);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'TENANT_INVALID');
    }
}
