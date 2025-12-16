<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Tests for Project Health Portfolio API
 * 
 * Round 74: Project Health Portfolio
 * 
 * Tests that project health portfolio endpoint returns health summary
 * for all projects of a tenant, with proper tenant isolation and permission checks.
 * 
 * @group reports
 * @group projects
 * @group health
 */
class ProjectHealthReportTest extends TestCase
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
        
        // Attach users to tenants with 'admin' role (which has tenant.view_reports in config)
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'admin']);
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'admin']);
        // User without permission - don't attach or attach with role that doesn't have permission
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that health report returns health for tenant projects
     */
    public function test_project_health_report_returns_health_for_tenant_projects(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);

        // Create project 1 with tasks and contracts
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Project 1',
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        // Create contract for project 1
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project1->id,
            'code' => 'CT-001',
            'total_value' => 10000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'total_amount' => 12000.00,
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'amount' => 11000.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create tasks for project 1
        $today = Carbon::today();
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project1->id,
            'status' => 'done',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project1->id,
            'status' => 'in_progress',
        ]);

        // Create project 2 with minimal data
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-002',
            'name' => 'Project 2',
            'status' => 'planning',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should return array of projects with health
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));

        // Find project 1 in response
        $project1Data = collect($data)->firstWhere('project.id', (string) $project1->id);
        $this->assertNotNull($project1Data, 'Project 1 should be in response');

        // Check project structure
        $this->assertArrayHasKey('project', $project1Data);
        $this->assertArrayHasKey('health', $project1Data);

        // Check project data
        $this->assertEquals((string) $project1->id, $project1Data['project']['id']);
        $this->assertEquals('PRJ-001', $project1Data['project']['code']);
        $this->assertEquals('Project 1', $project1Data['project']['name']);

        // Check health structure
        $health = $project1Data['health'];
        $this->assertArrayHasKey('overall_status', $health);
        $this->assertArrayHasKey('schedule_status', $health);
        $this->assertArrayHasKey('cost_status', $health);
        $this->assertArrayHasKey('tasks_completion_rate', $health);
        $this->assertArrayHasKey('blocked_tasks_ratio', $health);
        $this->assertArrayHasKey('overdue_tasks', $health);
        $this->assertArrayHasKey('cost_overrun_percent', $health);

        // Check health values are valid
        $this->assertContains($health['overall_status'], ['good', 'warning', 'critical']);
        $this->assertContains($health['schedule_status'], ['on_track', 'at_risk', 'delayed', 'no_tasks']);
        $this->assertContains($health['cost_status'], ['on_budget', 'over_budget', 'at_risk', 'no_data']);

        // Find project 2 in response
        $project2Data = collect($data)->firstWhere('project.id', (string) $project2->id);
        $this->assertNotNull($project2Data, 'Project 2 should be in response');
        $this->assertArrayHasKey('health', $project2Data);
    }

    /**
     * Test that health report respects tenant isolation
     */
    public function test_project_health_report_respects_tenant_isolation(): void
    {
        // Create project in tenant A
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A-001',
            'name' => 'Tenant A Project',
        ]);

        // Create project in tenant B
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'PRJ-B-001',
            'name' => 'Tenant B Project',
        ]);

        // User A calls endpoint
        Sanctum::actingAs($this->userA);
        $responseA = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $responseA->assertStatus(200);
        $dataA = $responseA->json('data');

        // User A should only see tenant A project
        $projectIds = collect($dataA)->pluck('project.id')->all();
        $this->assertContains((string) $projectA->id, $projectIds, 'Should contain tenant A project');
        $this->assertNotContains((string) $projectB->id, $projectIds, 'Should not contain tenant B project');

        // User B calls endpoint
        Sanctum::actingAs($this->userB);
        $responseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $responseB->assertStatus(200);
        $dataB = $responseB->json('data');

        // User B should only see tenant B project
        $projectIdsB = collect($dataB)->pluck('project.id')->all();
        $this->assertContains((string) $projectB->id, $projectIdsB, 'Should contain tenant B project');
        $this->assertNotContains((string) $projectA->id, $projectIdsB, 'Should not contain tenant A project');
    }

    /**
     * Test that health report requires tenant.view_reports permission
     */
    public function test_project_health_report_requires_view_reports_permission(): void
    {
        // Create project in tenant A
        Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // User without permission calls endpoint
        Sanctum::actingAs($this->userWithoutPermission);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/v1/app/reports/projects/health');

        $response->assertStatus(403);
    }
}

