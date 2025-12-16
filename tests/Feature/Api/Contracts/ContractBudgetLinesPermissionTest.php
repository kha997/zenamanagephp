<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contract Budget Lines API tenant permission enforcement
 * 
 * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
 * 
 * Tests that contract budget lines endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET requests) and mutation endpoints (POST/PUT/PATCH/DELETE).
 * 
 * @group contracts
 * @group contract-budget-lines
 * @group tenant-permissions
 */
class ContractBudgetLinesPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Contract $contract;
    private ContractBudgetLine $budgetLine;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(88888);
        $this->setDomainName('contract-budget-lines-permission');
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

        // Create budget line
        $this->budgetLine = ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'name' => 'Test Budget Line',
            'total_amount' => 10000.00,
            'created_by_id' => $creator->id,
            'updated_by_id' => $creator->id,
        ]);
    }

    /**
     * Test that all 4 standard roles (owner/admin/member/viewer) can GET contract budget lines endpoints
     * 
     * All standard roles have tenant.view_contracts from config, so should all pass.
     */
    public function test_all_standard_roles_can_view_contract_budget_lines(): void
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

            // All standard roles should be able to GET contract budget lines list
            $listResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines");

            $listResponse->assertStatus(200, "Role {$role} should be able to GET contract budget lines list (has tenant.view_contracts)");
        }
    }

    /**
     * Test that user without tenant.view_contracts cannot GET contract budget lines endpoints
     */
    public function test_user_without_view_contracts_cannot_access_budget_lines_endpoints(): void
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

        // Guest should NOT be able to GET contract budget lines list
        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines");

        $listResponse->assertStatus(403);
        $listResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that owner and admin can manage contract budget lines
     * 
     * Owner and admin have tenant.manage_contracts permission, so should be able to
     * create, update, delete contract budget lines.
     */
    public function test_owner_and_admin_can_manage_contract_budget_lines(): void
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

            // Test POST /contracts/{contract}/budget-lines (create) - requires idempotency key
            $createResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-create-' . uniqid(),
            ])->postJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines", [
                'name' => 'New Budget Line',
                'total_amount' => 5000.00,
            ]);

            // Should succeed (201 or 200 depending on controller implementation)
            $this->assertContains(
                $createResponse->status(),
                [200, 201],
                "Role {$role} should be able to POST contract budget lines (has tenant.manage_contracts)"
            );

            // Get the created budget line ID if successful
            $createdLineId = $createResponse->json('data.id') ?? $this->budgetLine->id;

            // Test PUT /contracts/{contract}/budget-lines/{line} (update) - requires idempotency key
            $updateResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-update-' . uniqid(),
            ])->putJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines/{$createdLineId}", [
                'name' => 'Updated Budget Line',
                'total_amount' => 6000.00,
            ]);

            $this->assertContains(
                $updateResponse->status(),
                [200, 204],
                "Role {$role} should be able to PUT contract budget lines (has tenant.manage_contracts)"
            );

            // Test PATCH /contracts/{contract}/budget-lines/{line} (partial update) - requires idempotency key
            $patchResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-patch-' . uniqid(),
            ])->patchJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines/{$createdLineId}", [
                'status' => 'approved',
            ]);

            $this->assertContains(
                $patchResponse->status(),
                [200, 204],
                "Role {$role} should be able to PATCH contract budget lines (has tenant.manage_contracts)"
            );

            // Test DELETE /contracts/{contract}/budget-lines/{line} (delete)
            // Use a separate budget line to avoid affecting other tests
            $lineToDelete = ContractBudgetLine::factory()->create([
                'tenant_id' => $this->tenant->id,
                'contract_id' => $this->contract->id,
                'name' => 'Budget Line to Delete',
                'total_amount' => 1000.00,
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
            ]);

            $deleteResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->deleteJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines/{$lineToDelete->id}");

            $this->assertContains(
                $deleteResponse->status(),
                [200, 204],
                "Role {$role} should be able to DELETE contract budget lines (has tenant.manage_contracts)"
            );
        }
    }

    /**
     * Test that member and viewer cannot manage contract budget lines
     * 
     * Member and viewer do NOT have tenant.manage_contracts permission, so should get 403
     * when trying to create, update, delete contract budget lines.
     */
    public function test_member_and_viewer_cannot_manage_contract_budget_lines(): void
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

            // Test POST /contracts/{contract}/budget-lines (create) - should fail with 403
            $createResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-create-' . uniqid(),
            ])->postJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines", [
                'name' => 'New Budget Line',
                'total_amount' => 5000.00,
            ]);

            $createResponse->assertStatus(403);
            $createResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to POST contract budget lines (no tenant.manage_contracts)");

            // Test PUT /contracts/{contract}/budget-lines/{line} (update) - should fail with 403
            $updateResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-update-' . uniqid(),
            ])->putJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines/{$this->budgetLine->id}", [
                'name' => 'Updated Budget Line',
            ]);

            $updateResponse->assertStatus(403);
            $updateResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to PUT contract budget lines (no tenant.manage_contracts)");

            // Test PATCH /contracts/{contract}/budget-lines/{line} (partial update) - should fail with 403
            $patchResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-patch-' . uniqid(),
            ])->patchJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines/{$this->budgetLine->id}", [
                'status' => 'approved',
            ]);

            $patchResponse->assertStatus(403);
            $patchResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to PATCH contract budget lines (no tenant.manage_contracts)");

            // Test DELETE /contracts/{contract}/budget-lines/{line} (delete) - should fail with 403
            $deleteResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->deleteJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines/{$this->budgetLine->id}");

            $deleteResponse->assertStatus(403);
            $deleteResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to DELETE contract budget lines (no tenant.manage_contracts)");
        }
    }

    /**
     * Test that guest cannot access contract budget lines endpoints
     */
    public function test_guest_cannot_access_contract_budget_lines(): void
    {
        // Create user without any tenant association
        $user = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET contract budget lines list
        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contract->id}/budget-lines");

        // Should return 401 or 403 (depends on middleware configuration)
        $this->assertContains($listResponse->status(), [401, 403]);
    }
}

