<?php declare(strict_types=1);

namespace Tests\Feature\Api\App\Projects;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\ProjectHealthSnapshot;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Tests for Project Health History API
 * 
 * Round 86: Project Health History (snapshots + history API, backend-only)
 * 
 * Tests that project health history endpoints work correctly with:
 * - Auth & RBAC (tenant.view_reports permission)
 * - Tenant isolation
 * - Snapshot correctness vs overview health
 * - History ordering and limit behavior
 * 
 * @group projects
 * @group health
 * @group reports
 */
class ProjectHealthHistoryTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private User $userWithoutPermission;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        $this->setDomainName('project-health-history');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'userA@test.com',
        ]);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email' => 'userB@test.com',
        ]);
        $this->userWithoutPermission = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'noperm@test.com',
        ]);
        
        // Attach users to tenants with 'admin' role (which has tenant.view_reports)
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'admin']);
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'admin']);
        // User without permission - attach with 'member' role (no view_reports)
        $this->userWithoutPermission->tenants()->attach($this->tenantA->id, ['role' => 'member']);
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test GET history - requires auth & permission
     */
    public function test_get_history_requires_auth(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$project->id}/health/history");

        $response->assertStatus(401);
    }

    /**
     * Test GET history - requires tenant.view_reports permission
     */
    public function test_get_history_requires_view_reports_permission(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        Sanctum::actingAs($this->userWithoutPermission);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->userWithoutPermission->createToken('test')->plainTextToken}",
        ])->getJson("/api/v1/app/projects/{$project->id}/health/history");

        $response->assertStatus(403);
    }

    /**
     * Test GET history - returns empty list when no snapshots
     */
    public function test_get_history_returns_empty_list_when_no_snapshots(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/health/history");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    /**
     * Test POST snapshot - requires auth & permission
     */
    public function test_post_snapshot_requires_auth(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $response = $this->postJson("/api/v1/app/projects/{$project->id}/health/snapshot");

        $response->assertStatus(401);
    }

    /**
     * Test POST snapshot - requires tenant.view_reports permission
     */
    public function test_post_snapshot_requires_view_reports_permission(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        Sanctum::actingAs($this->userWithoutPermission);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->userWithoutPermission->createToken('test')->plainTextToken}",
        ])->postJson("/api/v1/app/projects/{$project->id}/health/snapshot");

        $response->assertStatus(403);
    }

    /**
     * Test POST snapshot - creates snapshot successfully
     */
    public function test_post_snapshot_creates_snapshot_successfully(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        // Create some tasks
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/health/snapshot");

        $response->assertStatus(201);
        $data = $response->json('data');
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('snapshot_date', $data);
        $this->assertArrayHasKey('overall_status', $data);
        $this->assertArrayHasKey('schedule_status', $data);
        $this->assertArrayHasKey('cost_status', $data);
        $this->assertArrayHasKey('tasks_completion_rate', $data);
        $this->assertArrayHasKey('blocked_tasks_ratio', $data);
        $this->assertArrayHasKey('overdue_tasks', $data);
        $this->assertArrayHasKey('created_at', $data);

        // Verify snapshot was saved
        $snapshot = ProjectHealthSnapshot::where('project_id', $project->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals($this->tenantA->id, $snapshot->tenant_id);
        $this->assertEquals($project->id, $snapshot->project_id);
    }

    /**
     * Test snapshot data matches overview health
     */
    public function test_snapshot_data_matches_overview_health(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        // Create contract with expenses
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'client_id' => $client->id,
            'total_value' => 10000.00,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'amount' => 11000.00, // Over budget
        ]);

        // Create tasks (some overdue)
        $today = Carbon::today();
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'in_progress',
            'end_date' => $today->copy()->subDays(5), // Overdue
        ]);

        // Get overview health
        $overviewService = app(\App\Services\Projects\ProjectOverviewService::class);
        $overview = $overviewService->buildOverview($this->tenantA->id, $project->id);
        $expectedHealth = $overview['health'];

        // Create snapshot
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/health/snapshot");

        $response->assertStatus(201);
        $snapshotData = $response->json('data');

        // Verify snapshot matches overview health
        $this->assertEquals($expectedHealth['overall_status'], $snapshotData['overall_status']);
        $this->assertEquals($expectedHealth['schedule_status'], $snapshotData['schedule_status']);
        $this->assertEquals($expectedHealth['cost_status'], $snapshotData['cost_status']);
        $this->assertEquals($expectedHealth['tasks_completion_rate'], $snapshotData['tasks_completion_rate']);
        $this->assertEquals($expectedHealth['blocked_tasks_ratio'], $snapshotData['blocked_tasks_ratio']);
        $this->assertEquals($expectedHealth['overdue_tasks'], $snapshotData['overdue_tasks']);
    }

    /**
     * Test tenant isolation - Tenant A cannot see Tenant B snapshots
     */
    public function test_tenant_isolation_snapshots_isolated_by_tenant(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        // Create snapshot for Tenant A project
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'snapshot_date' => Carbon::today(),
        ]);

        // Create snapshot for Tenant B project
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
            'snapshot_date' => Carbon::today(),
        ]);

        // User A should only see Tenant A snapshots
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$projectA->id}/health/history");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        // Verify snapshot belongs to project A
        $snapshot = ProjectHealthSnapshot::find($data[0]['id']);
        $this->assertEquals($projectA->id, $snapshot->project_id);

        // User A should not be able to access Tenant B project
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$projectB->id}/health/history");

        $response->assertStatus(404);
    }

    /**
     * Test history ordering - snapshots ordered by snapshot_date descending
     */
    public function test_history_ordering_by_snapshot_date_descending(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $today = Carbon::today();
        
        // Create snapshots with different dates
        $snapshot1 = ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'snapshot_date' => $today->copy()->subDays(3),
        ]);

        $snapshot2 = ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'snapshot_date' => $today->copy()->subDays(1),
        ]);

        $snapshot3 = ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'snapshot_date' => $today->copy()->subDays(2),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/health/history");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(3, $data);
        
        // Should be ordered by snapshot_date descending (most recent first)
        $this->assertEquals($snapshot2->id, $data[0]['id']);
        $this->assertEquals($snapshot3->id, $data[1]['id']);
        $this->assertEquals($snapshot1->id, $data[2]['id']);
    }

    /**
     * Test history limit - respects limit parameter
     */
    public function test_history_limit_respects_limit_parameter(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $today = Carbon::today();
        
        // Create 10 snapshots
        for ($i = 0; $i < 10; $i++) {
            ProjectHealthSnapshot::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'project_id' => $project->id,
                'snapshot_date' => $today->copy()->subDays($i),
            ]);
        }

        Sanctum::actingAs($this->userA);
        
        // Request with limit=5
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/health/history?limit=5");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(5, $data);

        // Request with limit=100 (should cap at available)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/health/history?limit=100");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(10, $data);
    }

    /**
     * Test snapshot upsert - same day snapshot updates existing
     */
    public function test_snapshot_upsert_same_day_updates_existing(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        // Create initial snapshot
        Sanctum::actingAs($this->userA);
        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/health/snapshot");

        $response1->assertStatus(201);
        $snapshotId1 = $response1->json('data.id');

        // Create another snapshot on the same day (should update)
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->postJson("/api/v1/app/projects/{$project->id}/health/snapshot");

        $response2->assertStatus(201);
        $snapshotId2 = $response2->json('data.id');

        // Should be the same snapshot (upserted)
        $this->assertEquals($snapshotId1, $snapshotId2);

        // Verify only one snapshot exists for today
        $snapshots = ProjectHealthSnapshot::where('project_id', $project->id)
            ->whereDate('snapshot_date', Carbon::today())
            ->get();
        $this->assertCount(1, $snapshots);
    }
}

