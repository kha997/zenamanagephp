<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Expenses API cross-tenant isolation
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * Tests that contract expenses endpoints properly enforce tenant isolation.
 * 
 * @group contracts
 * @group contract-expenses
 * @group tenant-isolation
 */
class ContractExpensesIsolationTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Contract $contractA;
    private Contract $contractB;
    private Contract $contractA2;
    private ContractExpense $expenseA;
    private ContractExpense $expenseB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(99999);
        $this->setDomainName('contract-expenses-isolation');
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
        
        // Create user A in tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create user B in tenant B
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create contract A in tenant A
        $this->contractA = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-A-001',
            'name' => 'Tenant A Contract',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create contract A2 in tenant A (for testing contract scoping)
        $this->contractA2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'CT-A-002',
            'name' => 'Tenant A Contract 2',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);
        
        // Create contract B in tenant B
        $this->contractB = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'CT-B-001',
            'name' => 'Tenant B Contract',
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);

        // Create expense A in contract A (tenant A)
        $this->expenseA = ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $this->contractA->id,
            'name' => 'Tenant A Expense',
            'amount' => 10000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create expense B in contract B (tenant B)
        $this->expenseB = ContractExpense::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'contract_id' => $this->contractB->id,
            'name' => 'Tenant B Expense',
            'amount' => 20000.00,
            'created_by_id' => $this->userB->id,
            'updated_by_id' => $this->userB->id,
        ]);
    }

    /**
     * Test that user tenant A cannot view expenses of contract in another tenant
     */
    public function test_user_cannot_view_expenses_of_contract_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to GET tenant B's contract expenses
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contractB->id}/expenses");

        // Should return 404 (not found) or 403 (forbidden) - depends on implementation
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User from tenant A should not be able to access tenant B contract expenses'
        );
    }

    /**
     * Test that user tenant A cannot modify expense in another tenant
     */
    public function test_user_cannot_modify_expense_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to DELETE tenant B's expense
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contractB->id}/expenses/{$this->expenseB->id}");

        // Should return 404 (not found) or 403 (forbidden)
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User from tenant A should not be able to delete tenant B expense'
        );

        // Verify expense B still exists
        $this->assertDatabaseHas('contract_expenses', [
            'id' => $this->expenseB->id,
            'tenant_id' => $this->tenantB->id,
        ]);

        // User A should NOT be able to UPDATE tenant B's expense
        $updateResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contractB->id}/expenses/{$this->expenseB->id}", [
            'name' => 'Hacked Expense',
        ]);

        $this->assertContains(
            $updateResponse->status(),
            [403, 404],
            'User from tenant A should not be able to update tenant B expense'
        );
    }

    /**
     * Test that expenses query is scoped to contract and tenant
     */
    public function test_expenses_query_is_scoped_to_contract_and_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Create an expense for contract A2 in tenant A
        $expenseA2 = ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $this->contractA2->id,
            'name' => 'Tenant A Contract A2 Expense',
            'amount' => 15000.00,
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // User A should only see expenses from contract A in tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contractA->id}/expenses");

        $response->assertStatus(200);
        $expenses = $response->json('data') ?? [];
        
        // Verify all returned expenses belong to contract A in tenant A
        foreach ($expenses as $expense) {
            $expenseTenantId = $expense['tenant_id'] ?? null;
            $expenseContractId = $expense['contract_id'] ?? null;
            
            $this->assertEquals(
                $this->tenantA->id,
                $expenseTenantId,
                'Expenses should only be from tenant A'
            );
            
            $this->assertEquals(
                $this->contractA->id,
                $expenseContractId,
                'Expenses should only be from contract A'
            );
        }
        
        // Verify tenant B's expense is not included
        $expenseIds = array_column($expenses, 'id');
        $this->assertNotContains($this->expenseB->id, $expenseIds, 'Tenant B expense should not be visible');
        
        // Verify contract A2's expense is not included
        $this->assertNotContains($expenseA2->id, $expenseIds, 'Contract A2 expense should not be visible in contract A query');
    }

    /**
     * Test that user cannot create expense for contract in another tenant
     */
    public function test_user_cannot_create_expense_for_contract_in_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // User A should NOT be able to create expense for tenant B's contract
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson("/api/v1/app/contracts/{$this->contractB->id}/expenses", [
            'name' => 'Hacked Expense',
            'amount' => 5000.00,
        ]);

        // Should return 404 (not found) or 403 (forbidden)
        $this->assertContains(
            $response->status(),
            [403, 404],
            'User should not be able to create expense for contract in different tenant'
        );
    }

    /**
     * Test that soft-deleted expense cannot be resolved via route binding
     * 
     * Round 46: Hardening & Polish - Soft delete route binding
     */
    public function test_soft_deleted_expense_cannot_be_resolved_via_route_binding(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Soft delete expense A
        $this->expenseA->delete();

        // Verify expense is soft-deleted
        $this->assertSoftDeleted('contract_expenses', [
            'id' => $this->expenseA->id,
        ]);

        // Try to access the expense via route binding (update endpoint)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/contracts/{$this->contractA->id}/expenses/{$this->expenseA->id}", [
            'name' => 'Updated Expense',
        ]);

        // Should return 404 (not found) because route binding returns null for soft-deleted records
        $response->assertStatus(404);

        // Try to delete the already soft-deleted expense
        $deleteResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/contracts/{$this->contractA->id}/expenses/{$this->expenseA->id}");

        // Should return 404 (not found)
        $deleteResponse->assertStatus(404);
    }
}

