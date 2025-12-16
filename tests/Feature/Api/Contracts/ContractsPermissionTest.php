<?php declare(strict_types=1);

namespace Tests\Feature\Api\Contracts;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Contracts API tenant permission enforcement
 * 
 * Round 33: MVP Contract Backend
 * 
 * Tests that contracts endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET requests) and mutation endpoints (POST/PUT/PATCH/DELETE).
 * 
 * @group contracts
 * @group tenant-permissions
 */
class ContractsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(88888);
        $this->setDomainName('contracts-permission');
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
    }

    /**
     * Test that all 4 standard roles (owner/admin/member/viewer) can GET contracts endpoints
     * 
     * All standard roles have tenant.view_contracts from config, so should all pass.
     */
    public function test_all_standard_roles_can_get_contracts_endpoints(): void
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

            // All standard roles should be able to GET contracts list
            $listResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/contracts');

            $listResponse->assertStatus(200, "Role {$role} should be able to GET contracts list (has tenant.view_contracts)");

            // All standard roles should be able to GET specific contract
            $showResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson("/api/v1/app/contracts/{$this->contract->id}");

            $showResponse->assertStatus(200, "Role {$role} should be able to GET contract detail (has tenant.view_contracts)");
        }
    }

    /**
     * Test that user without tenant.view_contracts cannot GET contracts endpoints
     * 
     * Negative test: role 'guest' is not defined in config/permissions.php tenant_roles,
     * so user will have no permissions and should get 403.
     */
    public function test_user_without_view_contracts_cannot_access_contracts_endpoints(): void
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

        // Guest should NOT be able to GET contracts list
        $listResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/contracts');

        $listResponse->assertStatus(403);
        $listResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET specific contract
        $showResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/contracts/{$this->contract->id}");

        $showResponse->assertStatus(403);
        $showResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that owner and admin can manage contracts
     * 
     * Owner and admin have tenant.manage_contracts permission, so should be able to
     * create, update, delete contracts.
     */
    public function test_owner_and_admin_can_manage_contracts(): void
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

            // Test POST /contracts (create) - requires idempotency key
            $createResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-create-' . uniqid(),
            ])->postJson('/api/v1/app/contracts', [
                'code' => 'CT-NEW-' . uniqid(),
                'name' => 'New Contract',
                'total_value' => 10000.00,
                'currency' => 'USD',
            ]);

            // Should succeed (201 or 200 depending on controller implementation)
            $this->assertContains(
                $createResponse->status(),
                [200, 201],
                "Role {$role} should be able to POST contracts (has tenant.manage_contracts)"
            );

            // Get the created contract ID if successful
            $createdContractId = $createResponse->json('data.id') ?? $this->contract->id;

            // Test PUT /contracts/{id} (update) - requires idempotency key
            $updateResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-update-' . uniqid(),
            ])->putJson("/api/v1/app/contracts/{$createdContractId}", [
                'name' => 'Updated Contract',
                'status' => 'completed',
            ]);

            $this->assertContains(
                $updateResponse->status(),
                [200, 204],
                "Role {$role} should be able to PUT contracts (has tenant.manage_contracts)"
            );

            // Test PATCH /contracts/{id} (partial update) - requires idempotency key
            $patchResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-patch-' . uniqid(),
            ])->patchJson("/api/v1/app/contracts/{$createdContractId}", [
                'name' => 'Patched Contract',
                'status' => 'active',
            ]);

            $this->assertContains(
                $patchResponse->status(),
                [200, 204],
                "Role {$role} should be able to PATCH contracts (has tenant.manage_contracts)"
            );

            // Test DELETE /contracts/{id} (delete)
            // Use a separate contract to avoid affecting other tests
            $contractToDelete = Contract::factory()->create([
                'tenant_id' => $this->tenant->id,
                'code' => 'CT-DELETE-' . uniqid(),
                'name' => 'Contract to Delete',
                'status' => 'draft',
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
            ]);

            $deleteResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->deleteJson("/api/v1/app/contracts/{$contractToDelete->id}");

            $this->assertContains(
                $deleteResponse->status(),
                [200, 204],
                "Role {$role} should be able to DELETE contracts (has tenant.manage_contracts)"
            );
        }
    }

    /**
     * Test that member and viewer cannot manage contracts
     * 
     * Member and viewer do NOT have tenant.manage_contracts permission, so should get 403
     * when trying to create, update, delete contracts.
     */
    public function test_member_and_viewer_cannot_manage_contracts(): void
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

            // Test POST /contracts (create) - should fail with 403
            $createResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-create-' . uniqid(),
            ])->postJson('/api/v1/app/contracts', [
                'code' => 'CT-NEW-' . uniqid(),
                'name' => 'New Contract',
                'total_value' => 10000.00,
            ]);

            $createResponse->assertStatus(403);
            $createResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to POST contracts (no tenant.manage_contracts)");

            // Test PUT /contracts/{id} (update) - should fail with 403
            $updateResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-update-' . uniqid(),
            ])->putJson("/api/v1/app/contracts/{$this->contract->id}", [
                'name' => 'Updated Contract',
            ]);

            $updateResponse->assertStatus(403);
            $updateResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to PUT contracts (no tenant.manage_contracts)");

            // Test PATCH /contracts/{id} (partial update) - should fail with 403
            $patchResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-patch-' . uniqid(),
            ])->patchJson("/api/v1/app/contracts/{$this->contract->id}", [
                'name' => 'Patched Contract',
            ]);

            $patchResponse->assertStatus(403);
            $patchResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to PATCH contracts (no tenant.manage_contracts)");

            // Test DELETE /contracts/{id} (delete) - should fail with 403
            $deleteResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->deleteJson("/api/v1/app/contracts/{$this->contract->id}");

            $deleteResponse->assertStatus(403);
            $deleteResponse->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Role {$role} should NOT be able to DELETE contracts (no tenant.manage_contracts)");
        }
    }
}

