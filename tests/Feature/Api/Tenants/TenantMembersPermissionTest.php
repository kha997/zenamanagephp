<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\UserTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Tenant Members API permission enforcement
 * 
 * Tests that tenant members endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET) and mutation endpoints (PATCH, DELETE).
 * 
 * Round 17: Tenant Members & Invitations (Backend API + RBAC)
 * 
 * @group tenant-members
 * @group tenant-permissions
 */
class TenantMembersPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(11111);
        $this->setDomainName('tenant-members-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create a member user for testing
        $this->memberUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->memberUser->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
    }

    /**
     * Test that GET /api/v1/app/tenant/members requires tenant.view_members permission
     * 
     * All standard roles (owner/admin/member/viewer) have tenant.view_members from config.
     */
    public function test_get_members_requires_view_permission(): void
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
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/tenant/members');
            
            $response->assertStatus(200, "Role {$role} should be able to GET members (has tenant.view_members)");
            $response->assertJsonStructure([
                'data' => [
                    'members' => [],
                ],
            ]);
        }
    }

    /**
     * Test that GET /api/v1/app/tenant/members returns 403 for guest role
     */
    public function test_get_members_returns_403_for_guest(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_members
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tenant/members');
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that PATCH /api/v1/app/tenant/members/{id} requires tenant.manage_members permission
     */
    public function test_update_member_role_requires_manage_permission(): void
    {
        // Test owner can update
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $owner->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($owner);
        $token = $owner->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->patchJson("/api/v1/app/tenant/members/{$this->memberUser->id}", [
            'role' => 'viewer',
        ]);
        
        $response->assertStatus(200);
        
        // Verify role was updated
        $pivot = UserTenant::where('user_id', $this->memberUser->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('viewer', $pivot->role);
    }

    /**
     * Test that PATCH /api/v1/app/tenant/members/{id} returns 403 without permission
     */
    public function test_update_member_role_returns_403_without_permission(): void
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
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->patchJson("/api/v1/app/tenant/members/{$this->memberUser->id}", [
                'role' => 'admin',
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to update member role");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that DELETE /api/v1/app/tenant/members/{id} requires tenant.manage_members permission
     */
    public function test_remove_member_requires_manage_permission(): void
    {
        // Create a user to remove
        $userToRemove = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userToRemove->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => false,
        ]);
        
        // Test admin can remove
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/members/{$userToRemove->id}");
        
        $response->assertStatus(204);
        
        // Verify membership was soft deleted
        $pivot = UserTenant::withTrashed()
            ->where('user_id', $userToRemove->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();
        
        $this->assertNotNull($pivot, 'Pivot should exist (soft deleted)');
        $this->assertNotNull($pivot->deleted_at, 'Pivot should be soft deleted');
    }

    /**
     * Test that DELETE /api/v1/app/tenant/members/{id} returns 403 without permission
     */
    public function test_remove_member_returns_403_without_permission(): void
    {
        $userToRemove = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userToRemove->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => false,
        ]);
        
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($viewer);
        $token = $viewer->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/members/{$userToRemove->id}");
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test last owner protection - cannot remove last owner
     */
    public function test_cannot_remove_last_owner(): void
    {
        // Create tenant with exactly one owner
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $owner->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($owner);
        $token = $owner->createToken('test-token')->plainTextToken;
        
        // Attempt to remove the owner
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/members/{$owner->id}");
        
        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_LAST_OWNER_PROTECTED',
        ]);
    }

    /**
     * Test last owner protection - cannot demote last owner
     */
    public function test_cannot_demote_last_owner(): void
    {
        // Create tenant with exactly one owner
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $owner->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($owner);
        $token = $owner->createToken('test-token')->plainTextToken;
        
        // Attempt to demote the owner to admin
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->patchJson("/api/v1/app/tenant/members/{$owner->id}", [
            'role' => 'admin',
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_LAST_OWNER_PROTECTED',
        ]);
    }

    /**
     * Test tenant isolation - members from tenant A not visible in tenant B
     */
    public function test_tenant_isolation(): void
    {
        // Create tenant B
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);
        
        // Create user in tenant B
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $userB->tenants()->attach($tenantB->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
        
        // Create user in tenant A (our test tenant)
        $userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;
        
        // User A should only see members from tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tenant/members');
        
        $response->assertStatus(200);
        $members = $response->json('data.members', []);
        
        // Verify user B is not in the list
        $memberIds = array_column($members, 'id');
        $this->assertNotContains($userB->id, $memberIds, 'Tenant B member should not be visible in tenant A');
    }

    /**
     * Test that tenant A cannot update member of tenant B
     */
    public function test_cannot_update_member_of_another_tenant(): void
    {
        // Create tenant B
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create user in tenant B
        $userMemberB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email_verified_at' => now(),
        ]);

        $userMemberB->tenants()->attach($tenantB->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        // Create owner of tenant A (our test tenant)
        $userOwnerA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $userOwnerA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        Sanctum::actingAs($userOwnerA);
        $token = $userOwnerA->createToken('test-token')->plainTextToken;

        // Attempt to update member of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->patchJson("/api/v1/app/tenant/members/{$userMemberB->id}", [
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'VALIDATION_FAILED',
        ]);

        // Verify pivot of tenant B is unchanged
        $pivotB = UserTenant::where('user_id', $userMemberB->id)
            ->where('tenant_id', $tenantB->id)
            ->whereNull('deleted_at')
            ->first();

        $this->assertNotNull($pivotB, 'Member should still exist in tenant B');
        $this->assertEquals('member', $pivotB->role, 'Role should not be changed');
    }

    /**
     * Test that tenant A cannot remove member of tenant B
     */
    public function test_cannot_remove_member_of_another_tenant(): void
    {
        // Create tenant B
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create user in tenant B
        $userMemberB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email_verified_at' => now(),
        ]);

        $userMemberB->tenants()->attach($tenantB->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        // Create owner of tenant A (our test tenant)
        $userOwnerA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $userOwnerA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        Sanctum::actingAs($userOwnerA);
        $token = $userOwnerA->createToken('test-token')->plainTextToken;

        // Attempt to remove member of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/members/{$userMemberB->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'VALIDATION_FAILED',
        ]);

        // Verify pivot of tenant B is still intact (not soft deleted)
        $pivotB = UserTenant::where('user_id', $userMemberB->id)
            ->where('tenant_id', $tenantB->id)
            ->whereNull('deleted_at')
            ->first();

        $this->assertNotNull($pivotB, 'Member should still exist in tenant B');
        $this->assertNull($pivotB->deleted_at, 'Member should not be soft deleted');
    }
}

