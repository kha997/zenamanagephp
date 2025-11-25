<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractPayment;
use App\Models\ContractExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Tests for Contract Export API
 * 
 * Round 47: Cost Overruns Dashboard + Export
 * 
 * Tests that contract exports work correctly with tenant isolation and filters.
 * 
 * @group contracts
 * @group export
 */
class ContractExportTest extends TestCase
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
        $this->setDomainName('contract-export');
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
        
        // Create user A with view_contracts permission
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        // Create user B
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
     * Test that contracts export respects tenant and filters
     */
    public function test_contracts_export_respects_tenant_and_filters(): void
    {
        // Create contracts in tenant A
        $contractA1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-A-001',
            'name' => 'Tenant A Contract 1',
            'status' => Contract::STATUS_ACTIVE,
            'total_value' => 1000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        $contractA2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-A-002',
            'name' => 'Tenant A Contract 2',
            'status' => Contract::STATUS_DRAFT,
            'total_value' => 2000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create contract in tenant B
        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'CT-B-001',
            'name' => 'Tenant B Contract',
            'total_value' => 3000.00,
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
        
        // Call export endpoint for tenant A
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'text/csv',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/contracts/export');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $csvContent = $response->getContent();
        $lines = str_getcsv($csvContent, "\n");
        
        // Check header
        $header = str_getcsv($lines[0]);
        $this->assertContains('Contract Code', $header);
        $this->assertContains('Contract Name', $header);
        $this->assertContains('Status', $header);
        
        // Check that only tenant A contracts are included
        $this->assertStringContainsString('CT-A-001', $csvContent);
        $this->assertStringContainsString('CT-A-002', $csvContent);
        $this->assertStringNotContainsString('CT-B-001', $csvContent);
        
        // Test with status filter
        $responseFiltered = $this->withHeaders([
            'Accept' => 'text/csv',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get('/api/v1/app/contracts/export?status=active');
        
        $responseFiltered->assertStatus(200);
        $csvContentFiltered = $responseFiltered->getContent();
        
        // Should only contain active contract
        $this->assertStringContainsString('CT-A-001', $csvContentFiltered);
        $this->assertStringNotContainsString('CT-A-002', $csvContentFiltered);
    }

    /**
     * Test that contract cost schedule export includes budget, payments, and expenses
     */
    public function test_contract_cost_schedule_export_includes_budget_payments_expenses(): void
    {
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-EXPORT-001',
            'name' => 'Export Test Contract',
            'total_value' => 10000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create active budget line
        $budgetLine1 = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'code' => 'BL-001',
            'name' => 'Active Budget Line',
            'total_amount' => 5000.00,
            'status' => 'approved',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create cancelled budget line (should be excluded)
        $budgetLine2 = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'code' => 'BL-002',
            'name' => 'Cancelled Budget Line',
            'total_amount' => 2000.00,
            'status' => 'cancelled',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create payment
        $payment = ContractPayment::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'code' => 'PAY-001',
            'name' => 'Payment 1',
            'amount' => 3000.00,
            'status' => 'planned',
            'due_date' => Carbon::now()->addMonth(),
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create active expense
        $expense1 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'code' => 'EXP-001',
            'name' => 'Active Expense',
            'amount' => 1500.00,
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create cancelled expense (should be excluded)
        $expense2 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'code' => 'EXP-002',
            'name' => 'Cancelled Expense',
            'amount' => 500.00,
            'status' => 'cancelled',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Call export endpoint
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'text/csv',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get("/api/v1/app/contracts/{$contract->id}/cost-export");
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $csvContent = $response->getContent();
        
        // Check that active budget line is included
        $this->assertStringContainsString('budget_line', $csvContent);
        $this->assertStringContainsString('BL-001', $csvContent);
        $this->assertStringContainsString('Active Budget Line', $csvContent);
        
        // Check that cancelled budget line is excluded
        $this->assertStringNotContainsString('BL-002', $csvContent);
        
        // Check that payment is included
        $this->assertStringContainsString('payment', $csvContent);
        $this->assertStringContainsString('PAY-001', $csvContent);
        
        // Check that active expense is included
        $this->assertStringContainsString('expense', $csvContent);
        $this->assertStringContainsString('EXP-001', $csvContent);
        $this->assertStringContainsString('Active Expense', $csvContent);
        
        // Check that cancelled expense is excluded
        $this->assertStringNotContainsString('EXP-002', $csvContent);
    }

    /**
     * Test that export requires view_contracts permission
     */
    public function test_contract_export_requires_view_contracts_permission(): void
    {
        // Create user without view_contracts permission
        $userNoPermission = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $userNoPermission->tenants()->attach($this->tenantA->id, [
            'role' => 'member', // member role typically doesn't have view_contracts
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userNoPermission);
        $token = $userNoPermission->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'text/csv',
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/app/contracts/export');
        
        // Should return 403
        $response->assertStatus(403);
    }

    /**
     * Test that contract cost schedule export is tenant isolated
     */
    public function test_contract_cost_schedule_export_is_tenant_isolated(): void
    {
        // Create contract in tenant B
        $contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'CT-B-001',
            'name' => 'Tenant B Contract',
            'total_value' => 5000.00,
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
        
        // User A tries to export tenant B contract
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'text/csv',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->get("/api/v1/app/contracts/{$contractB->id}/cost-export");
        
        // Should return 404 (contract not found for tenant A)
        $response->assertStatus(404);
    }
}

