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
 * Tests for Contract Expenses API tenant permission enforcement
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * Tests that contract expenses endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET requests) and mutation endpoints (POST/PUT/PATCH/DELETE).
 * 
 * @group contracts
 * @group contract-expenses
 * @group tenant-permissions
 */
class ContractExpensesPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Contract $contract;
    private ContractExpense $expense;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(88888);
        $this->setDomainName('contract-expenses-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create a user for created_by
        $creator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        // Create contract
        $this->contract = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CT-001',
            'name' => 'Test Contract',
            'status' => 'active',
            'created_by_id' => $creator->id,
            'updated_by_id' => $creator->id,
        ]);

        // Create expense
        $this->expense = ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Test Expense',
            'amount' => 10000.00,
            'created_by_id' => $creator->id,
            'updated_by_id' => $creator->id,
        ]);
    }

    /**
     * Test that all 4 standard roles (owner/admin/member/viewer) can GET contract expenses endpoints
     * 
     * All standard roles have tenant.view_contracts from config, so should all pass.
     */
    public function test_all_standard_roles_can_view_contract_expenses(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);

            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;

            // All standard roles should be able to GET contract expenses list
            $listResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson("/api/v1/app/contracts/{$this->contract->id}/expenses");

            $listResponse->assertStatus(200, "Role {$role} should be able to GET contract expenses list (has tenant.view_contracts)");
        }
    }

    /**
     * Test that user without tenant.view_contracts cannot GET contract expenses endpoints
     */
    public function test_user_without_view_contracts_cannot_access_expenses_endpoints(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_contracts
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET contract expenses list
        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contract->id}/expenses");

        $listResponse->assertStatus(403);
        $listResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that owner and admin can manage contract expenses
     * 
     * Owner and admin have tenant.manage_contracts permission, so should be able to
     * create, update, delete contract expenses.
     */
    public function test_owner_and_admin_can_manage_contract_expenses(): void
    {
        $roles = ['owner', 'admin'];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);

            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;

            // Test POST /contracts/{contract}/expenses (create) - requires idempotency key
            $createResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-create-' . uniqid(),
            ])->postJson("/api/v1/app/contracts/{$this->contract->id}/expenses", [
                'name' => 'New Expense',
                'amount' => 5000.00,
            ]);

            // Should succeed (201 or 200 depending on controller implementation)
            $this->assertContains(
                $createResponse->status(),
                [200, 201],
                "Role {$role} should be able to POST contract expenses (has tenant.manage_contracts)"
            );

            // Get the created expense ID if successful
            $createdExpenseId = $createResponse->json('data.id') ?? $this->expense->id;

            // Test PUT /contracts/{contract}/expenses/{expense} (update) - requires idempotency key
            $updateResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-update-' . uniqid(),
            ])->putJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$createdExpenseId}", [
                'name' => 'Updated Expense',
                'amount' => 6000.00,
            ]);

            $this->assertContains(
                $updateResponse->status(),
                [200, 204],
                "Role {$role} should be able to PUT contract expenses (has tenant.manage_contracts)"
            );

            // Test PATCH /contracts/{contract}/expenses/{expense} (partial update) - requires idempotency key
            $patchResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-patch-' . uniqid(),
            ])->patchJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$createdExpenseId}", [
                'status' => 'approved',
            ]);

            $this->assertContains(
                $patchResponse->status(),
                [200, 204],
                "Role {$role} should be able to PATCH contract expenses (has tenant.manage_contracts)"
            );

            // Test DELETE /contracts/{contract}/expenses/{expense} (delete)
            // Use a separate expense to avoid affecting other tests
            $expenseToDelete = ContractExpense::factory()->create([
                'tenant_id' => $this->tenant->id,
                'contract_id' => $this->contract->id,
                'name' => 'Expense to Delete',
                'amount' => 1000.00,
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
            ]);

            $deleteResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->deleteJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$expenseToDelete->id}");

            $this->assertContains(
                $deleteResponse->status(),
                [200, 204],
                "Role {$role} should be able to DELETE contract expenses (has tenant.manage_contracts)"
            );
        }
    }

    /**
     * Test that member and viewer cannot manage contract expenses
     * 
     * Member and viewer do NOT have tenant.manage_contracts permission, so should get 403
     * when trying to create, update, delete contract expenses.
     */
    public function test_member_and_viewer_cannot_manage_contract_expenses(): void
    {
        $roles = ['member', 'viewer'];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);

            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;

            // Test POST /contracts/{contract}/expenses (create) - should fail with 403
            $createResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-create-' . uniqid(),
            ])->postJson("/api/v1/app/contracts/{$this->contract->id}/expenses", [
                'name' => 'New Expense',
                'amount' => 5000.00,
            ]);

            $createResponse->assertStatus(403);
            $createResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to POST contract expenses (no tenant.manage_contracts)");

            // Test PUT /contracts/{contract}/expenses/{expense} (update) - should fail with 403
            $updateResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-update-' . uniqid(),
            ])->putJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$this->expense->id}", [
                'name' => 'Updated Expense',
            ]);

            $updateResponse->assertStatus(403);
            $updateResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to PUT contract expenses (no tenant.manage_contracts)");

            // Test PATCH /contracts/{contract}/expenses/{expense} (partial update) - should fail with 403
            $patchResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-patch-' . uniqid(),
            ])->patchJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$this->expense->id}", [
                'status' => 'approved',
            ]);

            $patchResponse->assertStatus(403);
            $patchResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to PATCH contract expenses (no tenant.manage_contracts)");

            // Test DELETE /contracts/{contract}/expenses/{expense} (delete) - should fail with 403
            $deleteResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->deleteJson("/api/v1/app/contracts/{$this->contract->id}/expenses/{$this->expense->id}");

            $deleteResponse->assertStatus(403);
            $deleteResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to DELETE contract expenses (no tenant.manage_contracts)");
        }
    }

    /**
     * Test that guest cannot access contract expenses endpoints
     */
    public function test_guest_cannot_access_contract_expenses(): void
    {
        // Create user without any tenant association
        $user = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET contract expenses list
        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contract->id}/expenses");

        // Should return 401 or 403 (depends on middleware configuration)
        $this->assertContains($listResponse->status(), [401, 403]);
    }
}

