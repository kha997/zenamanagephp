<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;

/**
 * ProjectCostHealthApiTest
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 * 
 * Tests for GET /api/v1/app/projects/{proj}/cost-health endpoint
 * 
 * @group project-cost-health
 * @group api-v1
 */
class ProjectCostHealthApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;
    protected Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(226001);
        $this->setDomainName('project-cost-health-api');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        // Attach users to tenants
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);
    }

    /**
     * Test computes UNDER_BUDGET status
     */
    public function test_computes_under_budget(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        // Create contract with base amount: 800,000 (current = 800,000)
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 800000.0,
        ]);

        // No pending change orders
        // forecast_final_cost = 800,000
        // variance_vs_budget = 800,000 - 1,000,000 = -200,000
        // -200,000 < -5% of 1,000,000 (-50,000) → UNDER_BUDGET

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-health");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'project_id',
                    'cost_health_status',
                    'stats' => [
                        'budget_total',
                        'forecast_final_cost',
                        'variance_vs_budget',
                        'pending_change_orders_total',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('UNDER_BUDGET', $data['cost_health_status']);
        $this->assertEquals(1000000.0, $data['stats']['budget_total']);
        $this->assertEquals(800000.0, $data['stats']['forecast_final_cost']);
        $this->assertEquals(-200000.0, $data['stats']['variance_vs_budget']);
        $this->assertEquals(0.0, $data['stats']['pending_change_orders_total']);
    }

    /**
     * Test computes ON_BUDGET status
     */
    public function test_computes_on_budget(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        // Create contract with base amount: 980,000 (current = 980,000)
        Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 980000.0,
        ]);

        // No pending change orders
        // forecast_final_cost = 980,000
        // variance_vs_budget = 980,000 - 1,000,000 = -20,000
        // -20,000 is between -5% (-50,000) and 0, and pending = 0 → ON_BUDGET

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-health");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('ON_BUDGET', $data['cost_health_status']);
    }

    /**
     * Test computes AT_RISK status
     */
    public function test_computes_at_risk(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        // Create contract with base amount: 950,000 (current = 950,000)
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 950000.0,
        ]);

        // Create pending change order: +30,000
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 30000.0,
        ]);

        // forecast_final_cost = 950,000 + 30,000 = 980,000
        // variance_vs_budget = 980,000 - 1,000,000 = -20,000
        // -20,000 > -5% of 1,000,000 (-50,000), pending > 0, forecast <= budget → AT_RISK

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-health");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('AT_RISK', $data['cost_health_status']);
        $this->assertEquals(30000.0, $data['stats']['pending_change_orders_total']);
    }

    /**
     * Test computes OVER_BUDGET status
     */
    public function test_computes_over_budget(): void
    {
        Sanctum::actingAs($this->userA);

        // Set budget: 1,000,000
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        // Create contract with base amount: 950,000 (current = 950,000)
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 950000.0,
        ]);

        // Create pending change order: +100,000
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 100000.0,
        ]);

        // forecast_final_cost = 950,000 + 100,000 = 1,050,000
        // 1,050,000 > 1,000,000 → OVER_BUDGET

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-health");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('OVER_BUDGET', $data['cost_health_status']);
        $this->assertEquals(1050000.0, $data['stats']['forecast_final_cost']);
    }

    /**
     * Test handles zero budget
     */
    public function test_handles_zero_budget(): void
    {
        Sanctum::actingAs($this->userA);

        // No budget lines (budget_total = 0)

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-health");

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should default to ON_BUDGET when budget_total = 0
        $this->assertEquals('ON_BUDGET', $data['cost_health_status']);
        $this->assertEquals(0.0, $data['stats']['budget_total']);
    }

    /**
     * Test enforces tenant isolation
     */
    public function test_enforces_tenant_isolation(): void
    {
        Sanctum::actingAs($this->userA);

        // Try to access project from tenant B
        $response = $this->getJson("/api/v1/app/projects/{$this->projectB->id}/cost-health");

        $response->assertStatus(404);
    }

    /**
     * Test matches dashboard math (no drift)
     */
    public function test_matches_dashboard_math(): void
    {
        Sanctum::actingAs($this->userA);

        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000.0,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 900000.0,
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'approved',
            'amount_delta' => 50000.0,
        ]);

        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'status' => 'proposed',
            'amount_delta' => 20000.0,
        ]);

        // Get cost health
        $healthResponse = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-health");
        $healthData = $healthResponse->json('data');

        // Get cost dashboard
        $dashboardResponse = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-dashboard");
        $dashboardData = $dashboardResponse->json('data');

        // Verify math matches
        $this->assertEquals(
            $dashboardData['summary']['budget_total'],
            $healthData['stats']['budget_total']
        );
        $this->assertEquals(
            $dashboardData['variance']['forecast_final_cost'],
            $healthData['stats']['forecast_final_cost']
        );
        $this->assertEquals(
            $dashboardData['variance']['variance_vs_budget'],
            $healthData['stats']['variance_vs_budget']
        );
        $this->assertEquals(
            $dashboardData['variance']['pending_change_orders_total'],
            $healthData['stats']['pending_change_orders_total']
        );
    }
}
