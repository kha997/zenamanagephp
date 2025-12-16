<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Users API permission enforcement
 * 
 * Tests that users endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET) and mutation endpoints (POST, PUT, PATCH, DELETE).
 * 
 * Round 27: Security / RBAC Hardening
 * 
 * @group tenant-users
 * @group tenant-permissions
 */
class TenantUsersPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Tenant $tenantB;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(66666);
        $this->setDomainName('tenant-users-permission');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B for isolation tests
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create a test user in tenant A
        $this->testUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->testUser->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
    }

    /**
     * Test that GET /api/v1/app/users requires tenant.view_members permission
     */
    public function test_get_users_requires_view_permission(): void
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
            ])->getJson('/api/v1/app/users');
            
            $response->assertStatus(200, "Role {$role} should be able to GET users (has tenant.view_members)");
            $response->assertJsonStructure([
                'ok',
                'data',
            ]);
        }
    }

    /**
     * Test that GET /api/v1/app/users/{id} requires tenant.view_members permission
     */
    public function test_get_user_requires_view_permission(): void
    {
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
        ])->getJson("/api/v1/app/users/{$this->testUser->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test that POST /api/v1/app/users requires tenant.manage_members permission
     */
    public function test_create_user_requires_manage_permission(): void
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
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-user-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/users', [
                'name' => 'New User ' . uniqid(),
                'email' => 'newuser' . uniqid() . '@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
            
            $response->assertStatus(201, "Role {$role} should be able to create user");
            $response->assertJsonStructure([
                'ok',
                'data',
            ]);
        }
    }

    /**
     * Test that POST /api/v1/app/users returns 403 without permission
     */
    public function test_create_user_returns_403_without_permission(): void
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
                'Idempotency-Key' => 'test-user-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to create user");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that PUT /api/v1/app/users/{id} requires tenant.manage_members permission
     */
    public function test_update_user_requires_manage_permission(): void
    {
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
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/users/{$this->testUser->id}", [
            'name' => 'Updated User Name',
            'email' => $this->testUser->email,
        ]);
        
        $response->assertStatus(200);
    }

    /**
     * Test that PUT /api/v1/app/users/{id} returns 403 without permission
     */
    public function test_update_user_returns_403_without_permission(): void
    {
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
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/users/{$this->testUser->id}", [
            'name' => 'Updated User Name',
        ]);
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that DELETE /api/v1/app/users/{id} requires tenant.manage_members permission
     */
    public function test_delete_user_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        $userToDelete = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        $userToDelete->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => false,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/users/{$userToDelete->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test tenant isolation - users from tenant A not visible in tenant B
     */
    public function test_tenant_isolation(): void
    {
        // Create user in tenant B
        $userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $userB->tenants()->attach($this->tenantB->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
        
        // Create user in tenant A
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
        
        // User A should only see users from tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/users');
        
        $response->assertStatus(200);
        $users = $response->json('data', []);
        
        // Verify user B is not in the list
        $userIds = array_column($users, 'id');
        $this->assertNotContains($userB->id, $userIds, 'Tenant B user should not be visible in tenant A');
        
        // Verify user A cannot access user B directly
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/users/{$userB->id}");
        
        // Should return 403 or 404
        $this->assertContains($response2->status(), [403, 404], 'Should not be able to access tenant B user');
    }

    /**
     * Test that tenant A cannot modify user of tenant B
     */
    public function test_cannot_modify_user_of_another_tenant(): void
    {
        // Create user in tenant B
        $userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
            'name' => 'Tenant B User',
        ]);
        
        $userB->tenants()->attach($this->tenantB->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
        
        // Create owner of tenant A
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
        
        // Attempt to update user of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->putJson("/api/v1/app/users/{$userB->id}", [
            'name' => 'Hacked Name',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B user');
        
        // Verify user B is unchanged
        $userB->refresh();
        $this->assertEquals('Tenant B User', $userB->name, 'User should not be modified');
    }
}

