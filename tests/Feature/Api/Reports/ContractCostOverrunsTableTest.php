<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Cost Overruns Table API
 * 
 * Round 49: Full-page Cost Overruns Table + Export
 * 
 * Tests that cost overruns table endpoint returns paginated, sortable list
 * of contracts with overruns, with proper tenant isolation and filters.
 * 
 * @group reports
 * @group contracts
 * @group cost-overruns
 */
class ContractCostOverrunsTableTest extends TestCase
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
        $this->setDomainSeed(12345);
        
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
        
        // Grant permissions
        $this->userA->givePermissionTo('tenant.view_reports');
        $this->userB->givePermissionTo('tenant.view_reports');
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that table endpoint returns paginated results
     */
    public function test_table_endpoint_returns_paginated_results(): void
    {
        // Create multiple contracts with overruns
        for ($i = 1; $i <= 30; $i++) {
            $contract = Contract::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'code' => "CT-{$i}",
                'name' => "Contract {$i}",
                'total_value' => 1000.00,
                'created_by_id' => $this->userA->id,
                'updated_by_id' => $this->userA->id,
            ]);
            
            ContractExpense::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'contract_id' => $contract->id,
                'amount' => 1200.00, // overrun = 200
                'status' => 'recorded',
                'created_by_id' => $this->userA->id,
                'updated_by_id' => $this->userA->id,
            ]);
        }
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table?page=1&per_page=25');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(25, $data['items']);
        $this->assertEquals(30, $data['pagination']['total']);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(2, $data['pagination']['last_page']);
    }

    /**
     * Test that table endpoint respects filters
     */
    public function test_table_endpoint_respects_filters(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Project',
        ]);
        
        // Contract 1: matches all filters
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-001',
            'name' => 'Contract 1',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'status' => 'active',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'amount' => 1500.00, // overrun = 500
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Contract 2: doesn't match filters
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-002',
            'name' => 'Contract 2',
            'status' => 'completed',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'amount' => 1200.00, // overrun = 200
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table?' . http_build_query([
            'status' => 'active',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'min_overrun_amount' => 400,
        ]));
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data['items']);
        $this->assertEquals('CT-001', $data['items'][0]['code']);
    }

    /**
     * Test that table endpoint sorts correctly
     */
    public function test_table_endpoint_sorts_correctly(): void
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
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'amount' => 1100.00, // overrun = 100
            'status' => 'recorded',
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
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'amount' => 1300.00, // overrun = 300
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Test default sort (overrun_amount desc)
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(2, $data['items']);
        $this->assertEquals('CT-002', $data['items'][0]['code']); // 300 overrun
        $this->assertEquals('CT-001', $data['items'][1]['code']); // 100 overrun
        
        // Test sort asc
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table?sort_direction=asc');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('CT-001', $data['items'][0]['code']); // 100 overrun
        $this->assertEquals('CT-002', $data['items'][1]['code']); // 300 overrun
    }

    /**
     * Test that table endpoint is tenant isolated
     */
    public function test_table_endpoint_is_tenant_isolated(): void
    {
        // Create contract in tenant A
        $contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-A-001',
            'name' => 'Tenant A Contract',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractA->id,
            'amount' => 1200.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create contract in tenant B
        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'CT-B-001',
            'name' => 'Tenant B Contract',
            'total_value' => 1000.00,
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'contract_id' => $contractB->id,
            'amount' => 1200.00,
            'status' => 'recorded',
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
        
        // User A calls endpoint
        Sanctum::actingAs($this->userA);
        $responseA = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table');
        
        $responseA->assertStatus(200);
        $dataA = $responseA->json('data');
        
        // User A should only see tenant A contract
        $this->assertCount(1, $dataA['items']);
        $this->assertEquals('CT-A-001', $dataA['items'][0]['code']);
    }

    /**
     * Test that table endpoint requires view_reports permission
     */
    public function test_table_endpoint_requires_view_reports_permission(): void
    {
        $userWithoutPermission = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'noperm@test.com',
        ]);
        
        Sanctum::actingAs($userWithoutPermission);
        $token = $userWithoutPermission->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table');
        
        $response->assertStatus(403);
    }

    /**
     * Test type filter (budget, actual, both)
     */
    public function test_table_endpoint_type_filter(): void
    {
        // Contract with budget overrun only
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-001',
            'name' => 'Budget Overrun',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'total_amount' => 1200.00, // budget overrun
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Contract with actual overrun only
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-002',
            'name' => 'Actual Overrun',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'amount' => 1200.00, // actual overrun
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        Sanctum::actingAs($this->userA);
        
        // Test type=budget
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table?type=budget');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        $this->assertEquals('CT-001', $data['items'][0]['code']);
        
        // Test type=actual
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table?type=actual');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        $this->assertEquals('CT-002', $data['items'][0]['code']);
        
        // Test type=both (default)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table?type=both');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data['items']);
    }

    /**
     * Test that table endpoint returns currency field for each item
     * 
     * Round 50: Currency support
     */
    public function test_table_endpoint_returns_currency_field(): void
    {
        // Create contracts with different currencies
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-001',
            'name' => 'Contract USD',
            'currency' => 'USD',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'amount' => 1200.00, // overrun = 200
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-002',
            'name' => 'Contract VND',
            'currency' => 'VND',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'amount' => 1200.00, // overrun = 200
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/contracts/cost-overruns/table');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(2, $data['items']);
        
        // Check currency field exists and is correct
        $item1 = collect($data['items'])->firstWhere('code', 'CT-001');
        $item2 = collect($data['items'])->firstWhere('code', 'CT-002');
        
        $this->assertNotNull($item1);
        $this->assertNotNull($item2);
        $this->assertEquals('USD', $item1['currency']);
        $this->assertEquals('VND', $item2['currency']);
    }
}

