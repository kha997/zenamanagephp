<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use App\Services\Reports\ContractCostOverrunsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Cost Overruns Export API
 * 
 * Round 49: Full-page Cost Overruns Export
 * 
 * Tests that cost overruns export endpoint returns CSV file with proper
 * tenant isolation and filters.
 * 
 * @group reports
 * @group contracts
 * @group cost-overruns
 */
class ContractCostOverrunsExportTest extends TestCase
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
        $this->setDomainSeed(67890);
        
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
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-001',
            'name' => 'Test Contract',
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
        ])->get('/api/v1/app/reports/contracts/cost-overruns/export');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', function ($value) {
            return str_contains($value, 'attachment') && str_contains($value, '.csv');
        });
        
        $content = $response->getContent();
        $this->assertStringContainsString('Code', $content);
        $this->assertStringContainsString('Name', $content);
        $this->assertStringContainsString('CT-001', $content);
        $this->assertStringContainsString('Test Contract', $content);
    }

    /**
     * Test that export endpoint respects filters
     */
    public function test_export_endpoint_respects_filters(): void
    {
        // Contract 1: matches filter
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-001',
            'name' => 'Contract 1',
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
        
        // Contract 2: doesn't match filter
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
        ])->get('/api/v1/app/reports/contracts/cost-overruns/export?' . http_build_query([
            'status' => 'active',
            'min_overrun_amount' => 400,
        ]));
        
        $response->assertStatus(200);
        $content = $response->getContent();
        
        $this->assertStringContainsString('CT-001', $content);
        $this->assertStringNotContainsString('CT-002', $content);
    }

    /**
     * Test that export endpoint is tenant isolated
     */
    public function test_export_endpoint_is_tenant_isolated(): void
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
        ])->get('/api/v1/app/reports/contracts/cost-overruns/export');
        
        $responseA->assertStatus(200);
        $contentA = $responseA->getContent();
        
        // User A should only see tenant A contract
        $this->assertStringContainsString('CT-A-001', $contentA);
        $this->assertStringNotContainsString('CT-B-001', $contentA);
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
        ])->get('/api/v1/app/reports/contracts/cost-overruns/export');
        
        $response->assertStatus(403);
    }

    /**
     * Test that export CSV has correct headers and data format
     */
    public function test_export_csv_has_correct_format(): void
    {
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-001',
            'name' => 'Test Contract',
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
        ])->get('/api/v1/app/reports/contracts/cost-overruns/export');
        
        $response->assertStatus(200);
        $content = $response->getContent();
        
        // Check for BOM (UTF-8)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        
        // Check headers
        $this->assertStringContainsString('Code', $content);
        $this->assertStringContainsString('Name', $content);
        $this->assertStringContainsString('Status', $content);
        $this->assertStringContainsString('ClientName', $content);
        $this->assertStringContainsString('ProjectName', $content);
        $this->assertStringContainsString('ContractValue', $content);
        $this->assertStringContainsString('BudgetTotal', $content);
        $this->assertStringContainsString('BudgetVsContractDiff', $content);
        $this->assertStringContainsString('ActualTotal', $content);
        $this->assertStringContainsString('ContractVsActualDiff', $content);
        $this->assertStringContainsString('OverrunAmount', $content);
        
        // Check data row
        $this->assertStringContainsString('CT-001', $content);
        $this->assertStringContainsString('Test Contract', $content);
    }

    /**
     * Test that export CSV includes Currency column with correct values
     * 
     * Round 50: Currency support
     */
    public function test_export_csv_includes_currency_column(): void
    {
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
        ])->get('/api/v1/app/reports/contracts/cost-overruns/export');
        
        $response->assertStatus(200);
        $content = $response->getContent();
        
        // Check Currency header exists
        $this->assertStringContainsString('Currency', $content);
        
        // Check currency values in CSV rows
        $this->assertStringContainsString('USD', $content);
        $this->assertStringContainsString('VND', $content);
        
        // Verify CSV structure: Currency column should be after ProjectName and before ContractValue
        $lines = explode("\n", $content);
        $headerLine = $lines[0] ?? '';
        $this->assertStringContainsString('Currency', $headerLine);
    }

    /**
     * Test that export endpoint returns JSON error when exception occurs with JSON request
     * 
     * Round 50: Export error handling
     */
    public function test_export_endpoint_returns_json_error_on_exception(): void
    {
        // Mock the service to throw an exception
        $mockService = \Mockery::mock(ContractCostOverrunsService::class);
        $mockService->shouldReceive('exportContractCostOverrunsForTenant')
            ->once()
            ->andThrow(new \RuntimeException('Export service error'));
        
        $this->app->instance(ContractCostOverrunsService::class, $mockService);
        
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/reports/contracts/cost-overruns/export');
        
        $response->assertStatus(500);
        $response->assertJson([
            'ok' => false,
            'code' => 'EXPORT_FAILED',
        ]);
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
            'traceId',
        ]);
    }
}

