<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Cost Overruns API
 * 
 * Round 47: Cost Overruns Dashboard + Export
 * 
 * Tests that cost overruns endpoint returns correct lists of contracts
 * that are over budget or over actual cost, with proper tenant isolation.
 * 
 * @group reports
 * @group contracts
 * @group cost-overruns
 */
class ContractCostOverrunsTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        $this->setDomainName('contract-cost-overruns');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create user A with admin role (has tenant.view_reports)
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        // Create user B with admin role
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($this->userA);
        $this->tokenA = $this->userA->createToken('test-token-a')->plainTextToken;
        
        Sanctum::actingAs($this->userB);
        $this->tokenB = $this->userB->createToken('test-token-b')->plainTextToken;
    }

    /**
     * Test that cost overruns returns over budget and overrun lists correctly
     */
    public function test_contract_cost_overruns_returns_over_budget_and_overrun_lists_correctly(): void
    {
        // Contract C1: total_value = 1_000, budget = 1_200 (over budget), actual = 800 (not overrun)
        $contractC1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-C1',
            'name' => 'Contract C1',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractC1->id,
            'total_amount' => 1200.00,
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractC1->id,
            'amount' => 800.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Contract C2: total_value = 1_000, budget = 800 (ok), actual = 1_200 (overrun)
        $contractC2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-C2',
            'name' => 'Contract C2',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractC2->id,
            'total_amount' => 800.00,
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractC2->id,
            'amount' => 1200.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Contract C3: total_value = 1_000, budget = 1_100, actual = 1_050 (both over)
        $contractC3 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-C3',
            'name' => 'Contract C3',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractC3->id,
            'total_amount' => 1100.00,
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractC3->id,
            'amount' => 1050.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Contract C4: total_value = null (should be excluded)
        $contractC4 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-C4',
            'name' => 'Contract C4',
            'total_value' => null,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractC4->id,
            'total_amount' => 2000.00,
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Call endpoint
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'over_budget_contracts',
                'overrun_contracts',
            ],
        ]);
        
        $data = $response->json('data');
        
        // Assert over_budget_contracts contains C1 and C3
        $this->assertCount(2, $data['over_budget_contracts']);
        $overBudgetCodes = collect($data['over_budget_contracts'])->pluck('code')->toArray();
        $this->assertContains('CT-C1', $overBudgetCodes);
        $this->assertContains('CT-C3', $overBudgetCodes);
        
        // Assert overrun_contracts contains C2 and C3
        $this->assertCount(2, $data['overrun_contracts']);
        $overrunCodes = collect($data['overrun_contracts'])->pluck('code')->toArray();
        $this->assertContains('CT-C2', $overrunCodes);
        $this->assertContains('CT-C3', $overrunCodes);
        
        // Assert C4 is not in either list (total_value is null)
        $allCodes = array_merge(
            collect($data['over_budget_contracts'])->pluck('code')->toArray(),
            collect($data['overrun_contracts'])->pluck('code')->toArray()
        );
        $this->assertNotContains('CT-C4', $allCodes);
    }

    /**
     * Test that cost overruns is tenant isolated
     */
    public function test_contract_cost_overruns_is_tenant_isolated(): void
    {
        // Create over-budget contract in tenant A
        $contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-A-001',
            'name' => 'Tenant A Contract',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractA->id,
            'total_amount' => 1500.00,
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create over-budget contract in tenant B
        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'CT-B-001',
            'name' => 'Tenant B Contract',
            'total_value' => 1000.00,
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'contract_id' => $contractB->id,
            'total_amount' => 1500.00,
            'status' => 'approved',
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
        
        // User A calls endpoint
        Sanctum::actingAs($this->userA);
        $responseA = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns');
        
        $responseA->assertStatus(200);
        $dataA = $responseA->json('data');
        
        // User A should only see tenant A contract
        $this->assertCount(1, $dataA['over_budget_contracts']);
        $this->assertEquals('CT-A-001', $dataA['over_budget_contracts'][0]['code']);
        
        // User B calls endpoint
        Sanctum::actingAs($this->userB);
        $responseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns');
        
        $responseB->assertStatus(200);
        $dataB = $responseB->json('data');
        
        // User B should only see tenant B contract
        $this->assertCount(1, $dataB['over_budget_contracts']);
        $this->assertEquals('CT-B-001', $dataB['over_budget_contracts'][0]['code']);
    }

    /**
     * Test that cost overruns respects filters
     */
    public function test_contract_cost_overruns_respects_filters(): void
    {
        // Create contracts with different overrun amounts
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-001',
            'name' => 'Contract 1',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'total_amount' => 1100.00, // diff = 100
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-002',
            'name' => 'Contract 2',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'total_amount' => 1200.00, // diff = 200
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Call with min_budget_diff = 150 (should only return contract2)
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns?min_budget_diff=150');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only return contract2 (diff = 200 >= 150)
        $this->assertCount(1, $data['over_budget_contracts']);
        $this->assertEquals('CT-002', $data['over_budget_contracts'][0]['code']);
    }

    /**
     * Test that overrun_contracts are sorted by overrun_amount descending
     */
    public function test_overrun_contracts_are_sorted_by_overrun_amount_descending(): void
    {
        // Contract O1: total_value = 1_000, actual = 1_100 (overrun = 100)
        $contractO1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-O1',
            'name' => 'Contract O1',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractO1->id,
            'amount' => 1100.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Contract O2: total_value = 1_000, actual = 1_300 (overrun = 300)
        $contractO2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-O2',
            'name' => 'Contract O2',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractO2->id,
            'amount' => 1300.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Contract O3: total_value = 1_000, actual = 1_200 (overrun = 200)
        $contractO3 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-O3',
            'name' => 'Contract O3',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractO3->id,
            'amount' => 1200.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Call endpoint
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Assert overrun_contracts contains all 3 contracts
        $this->assertCount(3, $data['overrun_contracts']);
        
        // Assert order: O2 (300) > O3 (200) > O1 (100)
        $this->assertEquals('CT-O2', $data['overrun_contracts'][0]['code']);
        $this->assertEquals('CT-O3', $data['overrun_contracts'][1]['code']);
        $this->assertEquals('CT-O1', $data['overrun_contracts'][2]['code']);
        
        // Assert overrun_amount values
        $this->assertEquals(300.0, $data['overrun_contracts'][0]['overrun_amount']);
        $this->assertEquals(200.0, $data['overrun_contracts'][1]['overrun_amount']);
        $this->assertEquals(100.0, $data['overrun_contracts'][2]['overrun_amount']);
    }

    /**
     * Test that cost overruns requires view_reports permission
     */
    public function test_contract_cost_overruns_requires_view_reports_permission(): void
    {
        // Create user without view_reports permission
        $userNoPermission = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $userNoPermission->tenants()->attach($this->tenantA->id, [
            'role' => 'member', // member role typically doesn't have view_reports
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userNoPermission);
        $token = $userNoPermission->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns');
        
        // Should return 403
        $response->assertStatus(403);
    }
}

