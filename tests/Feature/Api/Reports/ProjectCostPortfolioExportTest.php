<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Project Cost Portfolio Export API
 * 
 * Round 66: Project Cost Portfolio Export
 * 
 * Tests that project cost portfolio export endpoint returns CSV file with proper
 * tenant isolation and filters.
 * 
 * @group reports
 * @group projects
 * @group portfolio
 */
class ProjectCostPortfolioExportTest extends TestCase
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
        $this->setDomainSeed(78901);
        
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
     * Test that export endpoint returns CSV file
     */
    public function test_export_endpoint_returns_csv_file(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
            'client_id' => $client->id,
        ]);
        
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CT-001',
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'amount' => 1200.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/reports/portfolio/projects/export');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', function ($value) {
            return str_contains($value, 'attachment') && str_contains($value, '.csv');
        });
        
        $content = $response->getContent();
        $this->assertStringContainsString('ClientName', $content);
        $this->assertStringContainsString('ProjectCode', $content);
        $this->assertStringContainsString('ProjectName', $content);
        $this->assertStringContainsString('PRJ-001', $content);
        $this->assertStringContainsString('Test Project', $content);
    }

    /**
     * Test that export endpoint respects filters
     */
    public function test_export_endpoint_respects_filters(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        // Project 1: matches filter
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Project 1',
            'status' => 'active',
            'client_id' => $client->id,
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
        
        // Project 2: doesn't match filter
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-002',
            'name' => 'Project 2',
            'status' => 'completed',
            'client_id' => $client->id,
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
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/reports/portfolio/projects/export?' . http_build_query([
            'status' => 'active',
            'min_overrun_amount' => 400,
        ]));
        
        $response->assertStatus(200);
        $content = $response->getContent();
        
        $this->assertStringContainsString('PRJ-001', $content);
        $this->assertStringNotContainsString('PRJ-002', $content);
    }

    /**
     * Test that export endpoint is tenant isolated
     */
    public function test_export_endpoint_is_tenant_isolated(): void
    {
        $clientA = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Client A',
        ]);
        
        $clientB = Client::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Client B',
        ]);
        
        // Create project in tenant A
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A-001',
            'name' => 'Tenant A Project',
            'client_id' => $clientA->id,
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
            'amount' => 1200.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create project in tenant B
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'PRJ-B-001',
            'name' => 'Tenant B Project',
            'client_id' => $clientB->id,
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
        ])->get('/api/v1/app/reports/portfolio/projects/export');
        
        $responseA->assertStatus(200);
        $contentA = $responseA->getContent();
        
        // User A should only see tenant A project
        $this->assertStringContainsString('PRJ-A-001', $contentA);
        $this->assertStringNotContainsString('PRJ-B-001', $contentA);
    }

    /**
     * Test that export endpoint requires view_reports permission
     */
    public function test_export_endpoint_requires_view_reports_permission(): void
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
        ])->get('/api/v1/app/reports/portfolio/projects/export');
        
        $response->assertStatus(403);
    }

    /**
     * Test that export CSV has correct headers and data format
     */
    public function test_export_csv_has_correct_format(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
            'client_id' => $client->id,
        ]);
        
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CT-001',
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
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/reports/portfolio/projects/export');
        
        $response->assertStatus(200);
        $content = $response->getContent();
        
        // Check for BOM (UTF-8)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        
        // Check headers
        $this->assertStringContainsString('ClientName', $content);
        $this->assertStringContainsString('ProjectCode', $content);
        $this->assertStringContainsString('ProjectName', $content);
        $this->assertStringContainsString('ContractsCount', $content);
        $this->assertStringContainsString('ContractsValueTotal', $content);
        $this->assertStringContainsString('BudgetTotal', $content);
        $this->assertStringContainsString('ActualTotal', $content);
        $this->assertStringContainsString('OverrunAmountTotal', $content);
        $this->assertStringContainsString('Currency', $content);
        
        // Check data row
        $this->assertStringContainsString('PRJ-001', $content);
        $this->assertStringContainsString('Test Project', $content);
    }

    /**
     * Test that export respects sort parameters
     */
    public function test_export_respects_sort_parameters(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);
        
        // Project 1: overrun = 500
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Project 1',
            'client_id' => $client->id,
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
        
        // Project 2: overrun = 200
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-002',
            'name' => 'Project 2',
            'client_id' => $client->id,
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
        
        // Test ascending sort
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/reports/portfolio/projects/export?' . http_build_query([
            'sort_by' => 'overrun_amount_total',
            'sort_direction' => 'asc',
        ]));
        
        $response->assertStatus(200);
        $content = $response->getContent();
        
        // Parse CSV to check order
        $lines = explode("\n", trim($content));
        // Skip BOM and header
        $dataLines = array_slice($lines, 2);
        $dataLines = array_filter($dataLines, fn($line) => !empty(trim($line)));
        
        // Should have 2 data rows
        $this->assertGreaterThanOrEqual(2, count($dataLines));
        
        // First row should be PRJ-002 (overrun = 200), second should be PRJ-001 (overrun = 500)
        $firstRow = str_getcsv($dataLines[0]);
        $this->assertStringContainsString('PRJ-002', $firstRow[1] ?? '');
    }
}

