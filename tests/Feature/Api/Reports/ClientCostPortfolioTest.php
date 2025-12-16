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
 * Tests for Client Cost Portfolio API
 * 
 * Round 53: Client Cost Portfolio
 * 
 * Tests that client cost portfolio endpoint returns paginated, sortable list
 * of clients with aggregated cost metrics, with proper tenant isolation and filters.
 * 
 * @group reports
 * @group clients
 * @group portfolio
 */
class ClientCostPortfolioTest extends TestCase
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
        
        // Attach users to tenants with 'member' role (which has tenant.view_reports in config)
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'member']);
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'member']);
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that portfolio endpoint returns paginated results
     */
    public function test_portfolio_endpoint_returns_paginated_results(): void
    {
        // Create multiple clients with contracts and overruns
        for ($i = 1; $i <= 30; $i++) {
            $client = Client::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'name' => "Client {$i}",
            ]);
            
            $contract = Contract::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'client_id' => $client->id,
                'code' => "CT-{$i}",
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
        ])->getJson('/api/v1/app/reports/portfolio/clients?page=1&per_page=25');
        
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
     * Test that portfolio endpoint respects filters
     */
    public function test_portfolio_endpoint_respects_filters(): void
    {
        // Client 1: matches all filters
        $client1 = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client ABC',
            'company' => 'ABC Corp',
        ]);
        
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client1->id,
            'code' => 'CT-001',
            'total_value' => 1000.00,
            'status' => 'active',
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
        
        // Client 2: doesn't match filters
        $client2 = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Other Client',
        ]);
        
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client2->id,
            'code' => 'CT-002',
            'total_value' => 1000.00,
            'status' => 'completed',
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
        
        // Test search filter
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/clients?search=ABC');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Test Client ABC', $data['items'][0]['client_name']);
        
        // Test client_id filter
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/reports/portfolio/clients?client_id={$client1->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        
        // Test status filter
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/clients?status=active');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        
        // Test min_overrun_amount filter
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/clients?min_overrun_amount=300');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Test Client ABC', $data['items'][0]['client_name']);
    }

    /**
     * Test that portfolio endpoint respects sort
     */
    public function test_portfolio_endpoint_respects_sort(): void
    {
        // Create clients with different overrun amounts
        $client1 = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Client A',
        ]);
        
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client1->id,
            'code' => 'CT-001',
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
        
        $client2 = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Client B',
        ]);
        
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client2->id,
            'code' => 'CT-002',
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
        
        // Test sort by overrun_amount_total desc (default)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/clients?sort_by=overrun_amount_total&sort_direction=desc');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data['items']);
        $this->assertEquals('Client A', $data['items'][0]['client_name']); // Higher overrun first
        $this->assertEquals(500.0, $data['items'][0]['overrun_amount_total']);
        
        // Test sort by client_name asc
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/clients?sort_by=client_name&sort_direction=asc');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data['items']);
        $this->assertEquals('Client A', $data['items'][0]['client_name']);
    }

    /**
     * Test that portfolio endpoint is tenant isolated
     */
    public function test_portfolio_endpoint_is_tenant_isolated(): void
    {
        // Create client in tenant A
        $clientA = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Client',
        ]);
        
        $contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $clientA->id,
            'code' => 'CT-A-001',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractA->id,
            'amount' => 1500.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create client in tenant B
        $clientB = Client::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Client',
        ]);
        
        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'client_id' => $clientB->id,
            'code' => 'CT-B-001',
            'total_value' => 1000.00,
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'contract_id' => $contractB->id,
            'amount' => 1500.00,
            'status' => 'recorded',
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
        
        // User A calls endpoint
        Sanctum::actingAs($this->userA);
        $responseA = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/clients');
        
        $responseA->assertStatus(200);
        $dataA = $responseA->json('data');
        
        // User A should only see tenant A client
        $this->assertCount(1, $dataA['items']);
        $this->assertEquals('Tenant A Client', $dataA['items'][0]['client_name']);
        
        // User B calls endpoint
        Sanctum::actingAs($this->userB);
        $responseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson('/api/v1/app/reports/portfolio/clients');
        
        $responseB->assertStatus(200);
        $dataB = $responseB->json('data');
        
        // User B should only see tenant B client
        $this->assertCount(1, $dataB['items']);
        $this->assertEquals('Tenant B Client', $dataB['items'][0]['client_name']);
    }

    /**
     * Test that portfolio endpoint requires tenant.view_reports permission
     */
    public function test_portfolio_endpoint_requires_permission(): void
    {
        $userWithoutPermission = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'noperm@test.com',
        ]);
        
        // Don't grant permission
        
        Sanctum::actingAs($userWithoutPermission);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/v1/app/reports/portfolio/clients');
        
        $response->assertStatus(403);
    }

    /**
     * Test that portfolio endpoint aggregates costs correctly
     */
    public function test_portfolio_endpoint_aggregates_costs_correctly(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client->id,
            'code' => 'PRJ-001',
            'name' => 'Project 1',
        ]);
        
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client->id,
            'code' => 'PRJ-002',
            'name' => 'Project 2',
        ]);
        
        // Create multiple contracts for the client (across different projects)
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client->id,
            'project_id' => $project1->id,
            'code' => 'CT-001',
            'total_value' => 1000.00,
            'currency' => 'VND',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'total_amount' => 1200.00,
            'status' => 'approved',
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
        
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client->id,
            'project_id' => $project2->id,
            'code' => 'CT-002',
            'total_value' => 2000.00,
            'currency' => 'VND',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'total_amount' => 1800.00,
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'amount' => 1900.00, // no overrun (1900 < 2000)
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/clients');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data['items']);
        $item = $data['items'][0];
        
        $this->assertEquals('Test Client', $item['client_name']);
        $this->assertEquals(2, $item['projects_count']); // 2 unique projects
        $this->assertEquals(2, $item['contracts_count']);
        $this->assertEquals(3000.00, $item['contracts_value_total']); // 1000 + 2000
        $this->assertEquals(3000.00, $item['budget_total']); // 1200 + 1800
        $this->assertEquals(3400.00, $item['actual_total']); // 1500 + 1900
        $this->assertEquals(500.00, $item['overrun_amount_total']); // Only contract1 has overrun (500)
        $this->assertEquals(1, $item['over_budget_contracts_count']); // contract1: 1200 > 1000
        $this->assertEquals(1, $item['overrun_contracts_count']); // contract1: 1500 > 1000
        $this->assertEquals('VND', $item['currency']);
    }

    /**
     * Test that portfolio endpoint excludes cancelled and soft-deleted budget/expenses from totals
     * 
     * Round 54: Hardening Client Portfolio
     * 
     * Verifies that:
     * - budget_total only includes active (not cancelled, not soft-deleted) budget lines
     * - actual_total only includes active (not cancelled, not soft-deleted) expenses
     */
    public function test_client_portfolio_excludes_cancelled_and_soft_deleted_budget_and_expenses_from_totals(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $client->id,
            'code' => 'CT-001',
            'total_value' => 1_000_000.00,
            'currency' => 'VND',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create 3 budget lines:
        // BL1: active (200_000) - should be included
        $budgetLine1 = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'total_amount' => 200_000.00,
            'status' => 'approved', // active status
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // BL2: cancelled (300_000) - should be excluded
        $budgetLine2 = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'total_amount' => 300_000.00,
            'status' => 'cancelled', // cancelled status
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // BL3: soft-deleted (400_000) - should be excluded
        $budgetLine3 = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'total_amount' => 400_000.00,
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        $budgetLine3->delete(); // Soft delete
        
        // Create 3 expenses:
        // EX1: active (150_000) - should be included
        $expense1 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'amount' => 150_000.00,
            'status' => 'recorded', // active status
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // EX2: cancelled (250_000) - should be excluded
        $expense2 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'amount' => 250_000.00,
            'status' => 'cancelled', // cancelled status
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // EX3: soft-deleted (350_000) - should be excluded
        $expense3 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'amount' => 350_000.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        $expense3->delete(); // Soft delete
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/clients');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data['items']);
        $item = $data['items'][0];
        
        $this->assertEquals('Test Client', $item['client_name']);
        
        // budget_total should only include BL1 (200_000), not BL2 (cancelled) or BL3 (soft-deleted)
        $this->assertEquals(200_000.00, $item['budget_total'], 'budget_total should only include active budget lines');
        
        // actual_total should only include EX1 (150_000), not EX2 (cancelled) or EX3 (soft-deleted)
        $this->assertEquals(150_000.00, $item['actual_total'], 'actual_total should only include active expenses');
        
        // Verify contract value is correct
        $this->assertEquals(1_000_000.00, $item['contracts_value_total']);
        
        // Verify overrun calculation (150_000 actual < 1_000_000 contract, so no overrun)
        $this->assertEquals(0.00, $item['overrun_amount_total']);
        $this->assertEquals(0, $item['overrun_contracts_count']);
    }
}

