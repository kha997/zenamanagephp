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
 * Tests for Project Cost Portfolio API
 * 
 * Round 51: Project Cost Portfolio
 * 
 * Tests that project cost portfolio endpoint returns paginated, sortable list
 * of projects with aggregated cost metrics, with proper tenant isolation and filters.
 * 
 * @group reports
 * @group projects
 * @group portfolio
 */
class ProjectCostPortfolioTest extends TestCase
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
        
        // Attach users to tenants with 'admin' role (which has tenant.view_reports in config)
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'admin']);
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'admin']);
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that portfolio endpoint returns paginated results
     */
    public function test_portfolio_endpoint_returns_paginated_results(): void
    {
        // Create multiple projects with contracts and overruns
        for ($i = 1; $i <= 30; $i++) {
            $project = Project::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'code' => "PRJ-{$i}",
                'name' => "Project {$i}",
            ]);
            
            $contract = Contract::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'project_id' => $project->id,
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
        ])->getJson('/api/v1/app/reports/portfolio/projects?page=1&per_page=25');
        
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
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        // Project 1: matches all filters
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Villa Project',
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project1->id,
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
        
        // Project 2: doesn't match filters
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-002',
            'name' => 'Other Project',
            'status' => 'completed',
        ]);
        
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project2->id,
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
        
        // Test search filter
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/projects?search=villa');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        $this->assertEquals('PRJ-001', $data['items'][0]['project_code']);
        
        // Test client_id filter
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/reports/portfolio/projects?client_id={$client->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        
        // Test status filter
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/projects?status=active');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        
        // Test min_overrun_amount filter
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/projects?min_overrun_amount=300');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['items']);
        $this->assertEquals('PRJ-001', $data['items'][0]['project_code']);
    }

    /**
     * Test that portfolio endpoint respects sort
     */
    public function test_portfolio_endpoint_respects_sort(): void
    {
        // Create projects with different overrun amounts
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Project A',
        ]);
        
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project1->id,
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
        
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-002',
            'name' => 'Project B',
        ]);
        
        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project2->id,
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
        ])->getJson('/api/v1/app/reports/portfolio/projects?sort_by=overrun_amount_total&sort_direction=desc');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data['items']);
        $this->assertEquals('PRJ-001', $data['items'][0]['project_code']); // Higher overrun first
        $this->assertEquals(500.0, $data['items'][0]['overrun_amount_total']);
        
        // Test sort by project_code asc
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/portfolio/projects?sort_by=project_code&sort_direction=asc');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data['items']);
        $this->assertEquals('PRJ-001', $data['items'][0]['project_code']);
    }

    /**
     * Test that portfolio endpoint is tenant isolated
     */
    public function test_portfolio_endpoint_is_tenant_isolated(): void
    {
        // Create project in tenant A
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A-001',
            'name' => 'Tenant A Project',
        ]);
        
        $contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
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
        
        // Create project in tenant B
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'PRJ-B-001',
            'name' => 'Tenant B Project',
        ]);
        
        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
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
        ])->getJson('/api/v1/app/reports/portfolio/projects');
        
        $responseA->assertStatus(200);
        $dataA = $responseA->json('data');
        
        // User A should only see tenant A project
        $this->assertCount(1, $dataA['items']);
        $this->assertEquals('PRJ-A-001', $dataA['items'][0]['project_code']);
        
        // User B calls endpoint
        Sanctum::actingAs($this->userB);
        $responseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson('/api/v1/app/reports/portfolio/projects');
        
        $responseB->assertStatus(200);
        $dataB = $responseB->json('data');
        
        // User B should only see tenant B project
        $this->assertCount(1, $dataB['items']);
        $this->assertEquals('PRJ-B-001', $dataB['items'][0]['project_code']);
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
        ])->getJson('/api/v1/app/reports/portfolio/projects');
        
        $response->assertStatus(403);
    }

    /**
     * Test that portfolio endpoint respects client_id filter
     * 
     * Round 55: Drill-down Reports - Client → Project Portfolio
     */
    public function test_portfolio_endpoint_respects_client_id_filter(): void
    {
        // Create two clients in tenant A
        $clientC1 = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Client C1',
        ]);
        
        $clientC2 = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Client C2',
        ]);
        
        // Project P1 belongs to Client C1
        $projectP1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-P1',
            'name' => 'Project P1',
            'client_id' => $clientC1->id,
        ]);
        
        $contractP1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectP1->id,
            'code' => 'CT-P1',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractP1->id,
            'amount' => 1200.00, // overrun = 200
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Project P2 belongs to Client C2
        $projectP2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-P2',
            'name' => 'Project P2',
            'client_id' => $clientC2->id,
        ]);
        
        $contractP2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectP2->id,
            'code' => 'CT-P2',
            'total_value' => 2000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractP2->id,
            'amount' => 2500.00, // overrun = 500
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        Sanctum::actingAs($this->userA);
        
        // Test: Filter by client_id = C1 should only return P1
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/reports/portfolio/projects?client_id={$clientC1->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only contain projects from Client C1
        $this->assertCount(1, $data['items']);
        $this->assertEquals('PRJ-P1', $data['items'][0]['project_code']);
        $this->assertEquals($clientC1->id, $data['items'][0]['client']['id']);
        
        // Should NOT contain projects from Client C2
        $projectCodes = array_column($data['items'], 'project_code');
        $this->assertNotContains('PRJ-P2', $projectCodes);
        
        // Test: Filter by client_id = C2 should only return P2
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/reports/portfolio/projects?client_id={$clientC2->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only contain projects from Client C2
        $this->assertCount(1, $data['items']);
        $this->assertEquals('PRJ-P2', $data['items'][0]['project_code']);
        $this->assertEquals($clientC2->id, $data['items'][0]['client']['id']);
        
        // Should NOT contain projects from Client C1
        $projectCodes = array_column($data['items'], 'project_code');
        $this->assertNotContains('PRJ-P1', $projectCodes);
    }

    /**
     * Test that portfolio endpoint aggregates costs correctly
     */
    public function test_portfolio_endpoint_aggregates_costs_correctly(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);
        
        // Create multiple contracts for the project
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
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
            'project_id' => $project->id,
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
        ])->getJson('/api/v1/app/reports/portfolio/projects');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data['items']);
        $item = $data['items'][0];
        
        $this->assertEquals('PRJ-001', $item['project_code']);
        $this->assertEquals(2, $item['contracts_count']);
        $this->assertEquals(3000.00, $item['contracts_value_total']); // 1000 + 2000
        $this->assertEquals(3000.00, $item['budget_total']); // 1200 + 1800
        $this->assertEquals(3400.00, $item['actual_total']); // 1500 + 1900
        $this->assertEquals(500.00, $item['overrun_amount_total']); // Only contract1 has overrun (500)
        $this->assertEquals(1, $item['over_budget_contracts_count']); // contract1: 1200 > 1000
        $this->assertEquals(1, $item['overrun_contracts_count']); // contract1: 1500 > 1000
        $this->assertEquals('VND', $item['currency']);
    }
}

