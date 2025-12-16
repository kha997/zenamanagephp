<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Services\Contracts\ContractExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Expenses API basic behavior
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * Tests basic CRUD operations and business logic.
 * 
 * @group contracts
 * @group contract-expenses
 */
class ContractExpensesBasicBehaviorTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $user;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(77777);
        $this->setDomainName('contract-expenses-basic');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create user with admin role
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        // Create contract
        $this->contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-TEST-001',
            'name' => 'Test Contract',
            'currency' => 'USD',
            'total_value' => 1000000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
    }

    /**
     * Test that user can create, update and delete expense within tenant
     */
    public function test_can_create_update_and_delete_expense_within_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create expense with quantity + unit_cost, not amount
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$this->contract->id}/expenses", [
            'name' => 'First Expense',
            'quantity' => 10.0,
            'unit_cost' => 150000.0,
            // amount not provided - should be auto-calculated
        ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJson([
            'success' => true,
        ]);

        $expenseData = $createResponse->json('data');
        $expenseId = $expenseData['id'];

        // Assert DB has record with correct tenant, contract, created_by_id, and calculated amount
        $this->assertDatabaseHas('contract_expenses', [
            'id' => $expenseId,
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'First Expense',
            'quantity' => 10.0,
            'unit_cost' => 150000.0,
            'amount' => 1500000.0, // 10 * 150000
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Update expense
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$expenseId}", [
            'name' => 'Updated Expense',
            'amount' => 1800000.0,
            'status' => 'approved',
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'success' => true,
        ]);

        // Assert updated_by_id is set correctly
        $this->assertDatabaseHas('contract_expenses', [
            'id' => $expenseId,
            'name' => 'Updated Expense',
            'amount' => 1800000.0,
            'status' => 'approved',
            'updated_by_id' => $this->user->id,
        ]);

        // Delete expense
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$expenseId}");

        $deleteResponse->assertStatus(204);

        // Verify expense is soft deleted
        $this->assertSoftDeleted('contract_expenses', [
            'id' => $expenseId,
        ]);
    }

    /**
     * Test that expense auto-computes amount from quantity and unit_cost on create
     */
    public function test_auto_computes_amount_from_quantity_and_unit_cost_on_create(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create expense with quantity and unit_cost, but no amount
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-auto-calc-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$this->contract->id}/expenses", [
            'name' => 'Auto Calculate Expense',
            'quantity' => 10.0,
            'unit_cost' => 150000.0,
            // amount not provided
        ]);

        $createResponse->assertStatus(201);
        $expenseData = $createResponse->json('data');
        $expenseId = $expenseData['id'];

        // Assert amount is auto-calculated
        $this->assertDatabaseHas('contract_expenses', [
            'id' => $expenseId,
            'amount' => 1500000.0, // 10 * 150000
        ]);
    }

    /**
     * Test that expense auto-computes amount on update when not provided
     */
    public function test_auto_computes_amount_on_update_when_not_provided(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create expense with quantity, unit_cost, and amount
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-update-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$this->contract->id}/expenses", [
            'name' => 'Update Test Expense',
            'quantity' => 5.0,
            'unit_cost' => 100000.0,
            'amount' => 500000.0,
        ]);

        $createResponse->assertStatus(201);
        $expenseId = $createResponse->json('data.id');

        // Update with new quantity and unit_cost, but no amount
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-calc-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$expenseId}", [
            'quantity' => 6.0,
            'unit_cost' => 120000.0,
            // amount not provided - should be auto-calculated
        ]);

        $updateResponse->assertStatus(200);

        // Assert amount is auto-calculated
        $this->assertDatabaseHas('contract_expenses', [
            'id' => $expenseId,
            'amount' => 720000.0, // 6 * 120000
        ]);
    }

    /**
     * Test that expense inherits currency from contract when missing
     */
    public function test_inherits_currency_from_contract_when_missing(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create contract with USD currency
        $contractUSD = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-USD-001',
            'name' => 'USD Contract',
            'currency' => 'USD',
            'total_value' => 50000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Create expense without currency - should inherit from contract
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-currency-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$contractUSD->id}/expenses", [
            'name' => 'Expense Without Currency',
            'amount' => 10000.00,
            // currency not provided
        ]);

        $createResponse->assertStatus(201);
        $expenseData = $createResponse->json('data');
        $expenseId = $expenseData['id'];

        // Assert expense currency matches contract currency
        $this->assertDatabaseHas('contract_expenses', [
            'id' => $expenseId,
            'currency' => 'USD', // Should inherit from contract
        ]);
    }

    /**
     * Test actual cost summary for contract returns expected values
     */
    public function test_actual_cost_summary_for_contract(): void
    {
        // Create expenses with different statuses
        $expense1 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Expense 1',
            'status' => 'recorded',
            'amount' => 400000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        $expense2 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Expense 2',
            'status' => 'approved',
            'amount' => 200000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // One expense cancelled (should not be counted)
        $expense3 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Expense 3 Cancelled',
            'status' => 'cancelled',
            'amount' => 300000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Get summary via service
        $service = app(ContractExpenseService::class);
        $summary = $service->getActualCostSummaryForContract((string) $this->tenant->id, $this->contract);

        // Assert expected values
        $this->assertEquals(600000.00, $summary['actual_total'], 'Actual total should be sum of non-cancelled expenses (400000 + 200000)');
        $this->assertEquals(1000000.00, $summary['contract_value'], 'Contract value should match contract.total_value');
        $this->assertEquals(400000.00, $summary['contract_vs_actual_diff'], 'Difference should be contract_value - actual_total (1000000 - 600000 = 400000)');
        $this->assertEquals(2, $summary['line_count'], 'Line count should be 2 (excluding cancelled)');
    }
}

