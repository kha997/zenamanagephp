<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractLine;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * ProjectCostSummaryApiTest
 * 
 * Round 222: Project Cost Summary API (Budget vs Contract vs Actual)
 * 
 * Tests for project cost summary API endpoint with tenant isolation and aggregation logic
 * 
 * @group project-cost-summary
 * @group api-v1
 */
class ProjectCostSummaryApiTest extends TestCase
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
        $this->setDomainSeed(222001);
        $this->setDomainName('project-cost-summary-api');
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

    public function test_it_returns_overall_cost_summary_for_project(): void
    {
        Sanctum::actingAs($this->userA);

        // Create budget lines
        $budgetLine1 = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000,
            'cost_category' => 'structure',
        ]);

        $budgetLine2 = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 2000000,
            'cost_category' => 'interior',
        ]);

        // Create contracts
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 800000,
        ]);

        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 1200000,
        ]);

        // Create approved change order for contract1 (+100000)
        ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract1->id,
            'status' => 'approved',
            'amount_delta' => 100000,
        ]);

        // Create approved payment certificate for contract1
        ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract1->id,
            'status' => 'approved',
            'amount_payable' => 500000,
        ]);

        // Create actual payment for contract1
        ContractActualPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract1->id,
            'paid_date' => now(),
            'amount_paid' => 300000,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'project_id',
                    'currency',
                    'totals' => [
                        'budget_total',
                        'contract_base_total',
                        'contract_current_total',
                        'total_certified_amount',
                        'total_paid_amount',
                        'outstanding_amount',
                    ],
                    'categories' => [
                        '*' => [
                            'cost_category',
                            'budget_total',
                            'contract_base_total',
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');

        // Assert overall totals
        $this->assertEquals(3000000.0, $data['totals']['budget_total']); // 1000000 + 2000000
        $this->assertEquals(2000000.0, $data['totals']['contract_base_total']); // 800000 + 1200000
        $this->assertEquals(2100000.0, $data['totals']['contract_current_total']); // (800000 + 100000) + 1200000
        $this->assertEquals(500000.0, $data['totals']['total_certified_amount']);
        $this->assertEquals(300000.0, $data['totals']['total_paid_amount']);
        $this->assertEquals(1800000.0, $data['totals']['outstanding_amount']); // 2100000 - 300000
    }

    public function test_it_returns_per_category_budget_and_contract_base_totals(): void
    {
        Sanctum::actingAs($this->userA);

        // Create budget lines with different categories
        $budgetLineStructure = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000,
            'cost_category' => 'structure',
        ]);

        $budgetLineInterior = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 2000000,
            'cost_category' => 'interior',
        ]);

        // Create contract
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 500000,
        ]);

        // Create contract lines mapped to budget lines
        ContractLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'budget_line_id' => $budgetLineStructure->id,
            'amount' => 400000,
        ]);

        ContractLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'budget_line_id' => $budgetLineInterior->id,
            'amount' => 600000,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-summary");

        $response->assertStatus(200);

        $data = $response->json('data');
        $categories = $data['categories'];

        // Should have 2 categories
        $this->assertCount(2, $categories);

        // Find structure category
        $structureCategory = collect($categories)->firstWhere('cost_category', 'structure');
        $this->assertNotNull($structureCategory);
        $this->assertEquals(1000000.0, $structureCategory['budget_total']);
        $this->assertEquals(400000.0, $structureCategory['contract_base_total']);

        // Find interior category
        $interiorCategory = collect($categories)->firstWhere('cost_category', 'interior');
        $this->assertNotNull($interiorCategory);
        $this->assertEquals(2000000.0, $interiorCategory['budget_total']);
        $this->assertEquals(600000.0, $interiorCategory['contract_base_total']);
    }

    public function test_it_enforces_tenant_isolation_for_cost_summary(): void
    {
        Sanctum::actingAs($this->userA);

        // Create budget lines and contracts in tenant B's project
        ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'amount_budget' => 5000000,
        ]);

        Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'base_amount' => 3000000,
        ]);

        // Try to access tenant B's project cost summary from tenant A
        $response = $this->getJson("/api/v1/app/projects/{$this->projectB->id}/cost-summary");

        // Should return 404 because project doesn't belong to tenant A
        $response->assertStatus(404);
    }

    public function test_it_handles_empty_project_with_no_budget_or_contracts(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-summary");

        $response->assertStatus(200);

        $data = $response->json('data');

        // All totals should be 0
        $this->assertEquals(0.0, $data['totals']['budget_total']);
        $this->assertEquals(0.0, $data['totals']['contract_base_total']);
        $this->assertEquals(0.0, $data['totals']['contract_current_total']);
        $this->assertEquals(0.0, $data['totals']['total_certified_amount']);
        $this->assertEquals(0.0, $data['totals']['total_paid_amount']);
        $this->assertEquals(0.0, $data['totals']['outstanding_amount']);

        // Categories should be empty array
        $this->assertIsArray($data['categories']);
        $this->assertCount(0, $data['categories']);
    }

    public function test_it_excludes_soft_deleted_budget_lines_and_contracts(): void
    {
        Sanctum::actingAs($this->userA);

        // Create active budget line
        $activeBudgetLine = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 1000000,
        ]);

        // Create soft-deleted budget line
        $deletedBudgetLine = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'amount_budget' => 500000,
        ]);
        $deletedBudgetLine->delete();

        // Create active contract
        $activeContract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 800000,
        ]);

        // Create soft-deleted contract
        $deletedContract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'base_amount' => 200000,
        ]);
        $deletedContract->delete();

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/cost-summary");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Should only include active budget line
        $this->assertEquals(1000000.0, $data['totals']['budget_total']);

        // Should only include active contract
        $this->assertEquals(800000.0, $data['totals']['contract_base_total']);
    }
}
