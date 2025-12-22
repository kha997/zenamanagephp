<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Services\Contracts\ContractBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Budget Lines API basic behavior
 * 
 * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
 * 
 * Tests basic CRUD operations and business logic.
 * 
 * @group contracts
 * @group contract-budget-lines
 */
class ContractBudgetLinesBasicBehaviorTest extends TestCase
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
        $this->setDomainName('contract-budget-lines-basic');
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
            'total_value' => 100000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);
    }

    /**
     * Test that user can create, update and delete budget line within tenant
     */
    public function test_can_create_update_and_delete_budget_line_within_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create budget line with quantity + unit_price, not total_amount
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines", [
            'name' => 'First Budget Line',
            'quantity' => 10.0,
            'unit' => 'm3',
            'unit_price' => 3000.0,
            // total_amount not provided - should be auto-calculated
        ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJson([
            'success' => true,
        ]);

        $lineData = $createResponse->json('data');
        $lineId = $lineData['id'];

        // Assert DB has record with correct tenant, contract, created_by_id, and calculated total_amount
        $this->assertDatabaseHas('contract_budget_lines', [
            'id' => $lineId,
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'First Budget Line',
            'quantity' => 10.0,
            'unit_price' => 3000.0,
            'total_amount' => 30000.0, // 10 * 3000
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Update budget line
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines/{$lineId}", [
            'name' => 'Updated Budget Line',
            'total_amount' => 35000.0,
            'status' => 'approved',
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'success' => true,
        ]);

        // Assert updated_by_id is set correctly
        $this->assertDatabaseHas('contract_budget_lines', [
            'id' => $lineId,
            'name' => 'Updated Budget Line',
            'total_amount' => 35000.0,
            'status' => 'approved',
            'updated_by_id' => $this->user->id,
        ]);

        // Delete budget line
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines/{$lineId}");

        $deleteResponse->assertStatus(204);

        // Verify budget line is soft deleted
        $this->assertSoftDeleted('contract_budget_lines', [
            'id' => $lineId,
        ]);
    }

    /**
     * Test that budget line inherits contract currency by default
     */
    public function test_budget_line_inherits_contract_currency_by_default(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Create contract with VND currency
        $contractVND = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-VND-001',
            'name' => 'VND Contract',
            'currency' => 'VND',
            'total_value' => 50000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Create budget line without currency - should inherit from contract
        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-currency-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$contractVND->id}/budget-lines", [
            'name' => 'Budget Line Without Currency',
            'total_amount' => 10000.00,
            // currency not provided
        ]);

        $createResponse->assertStatus(201);
        $lineData = $createResponse->json('data');
        $lineId = $lineData['id'];

        // Assert budget line currency matches contract currency
        $this->assertDatabaseHas('contract_budget_lines', [
            'id' => $lineId,
            'currency' => 'VND', // Should inherit from contract
        ]);
    }

    /**
     * Test get budget summary for contract returns expected values
     */
    public function test_get_budget_summary_for_contract_returns_expected_values(): void
    {
        // Create 2-3 budget lines: planned/approved, not cancelled
        $line1 = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Budget Line 1',
            'status' => 'planned',
            'total_amount' => 30000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        $line2 = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Budget Line 2',
            'status' => 'approved',
            'total_amount' => 40000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // One line cancelled (should not be counted)
        $line3 = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Budget Line 3 Cancelled',
            'status' => 'cancelled',
            'total_amount' => 20000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Get summary via service
        $service = app(ContractBudgetService::class);
        $summary = $service->getBudgetSummaryForContract((string) $this->tenant->id, $this->contract);

        // Assert expected values
        $this->assertEquals(70000.00, $summary['budget_total'], 'Budget total should be sum of non-cancelled lines (30000 + 40000)');
        $this->assertEquals(100000.00, $summary['contract_value'], 'Contract value should match contract.total_value');
        $this->assertEquals(-30000.00, $summary['budget_vs_contract_diff'], 'Difference should be budget_total - contract_value (70000 - 100000 = -30000)');
        $this->assertEquals(2, $summary['active_line_count'], 'Active line count should be 2 (excluding cancelled)');
    }
}

