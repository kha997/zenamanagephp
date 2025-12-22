<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\RoleProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

/**
 * Role Profile API Test
 * 
 * Round 244: Role Access Profiles
 * 
 * Tests the admin endpoints for managing role profiles:
 * - GET /api/v1/admin/role-profiles
 * - POST /api/v1/admin/role-profiles
 * - PUT /api/v1/admin/role-profiles/{profile}
 * - DELETE /api/v1/admin/role-profiles/{profile}
 * - PUT /api/v1/admin/users/{user}/assign-profile
 * 
 * @group admin
 * @group role-profiles
 * @group rbac
 */
class RoleProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $adminUser;
    private User $regularUser;
    private User $targetUser;
    private Role $role1;
    private Role $role2;
    private Role $role3;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenants
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        $this->otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);
        
        // Create admin user with system.role_profiles.manage permission
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);
        
        // Create regular user without admin permissions
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'email' => 'member@test.com',
        ]);
        
        // Create target user for profile assignment
        $this->targetUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'email' => 'target@test.com',
        ]);
        
        // Create test roles
        $this->role1 = Role::factory()->create([
            'name' => 'Project Manager',
            'scope' => 'system',
        ]);
        
        $this->role2 = Role::factory()->create([
            'name' => 'Cost Controller',
            'scope' => 'system',
        ]);
        
        $this->role3 = Role::factory()->create([
            'name' => 'Document Controller',
            'scope' => 'system',
        ]);
    }

    /**
     * Test admin user can list profiles
     */
    public function test_admin_can_list_profiles(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        // Create test profiles
        $profile1 = RoleProfile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Project Manager Profile',
            'roles' => [$this->role1->id],
        ]);
        
        $profile2 = RoleProfile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cost Controller Profile',
            'roles' => [$this->role2->id],
        ]);
        
        $response = $this->getJson('/api/v1/admin/role-profiles');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'roles',
                        'role_ids',
                        'is_active',
                        'tenant_id',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        $profileIds = array_column($data, 'id');
        $this->assertContains($profile1->id, $profileIds);
        $this->assertContains($profile2->id, $profileIds);
    }

    /**
     * Test admin user can create profile
     */
    public function test_admin_can_create_profile(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->postJson('/api/v1/admin/role-profiles', [
            'name' => 'New Profile',
            'description' => 'A new profile for testing',
            'roles' => [$this->role1->id, $this->role2->id],
            'is_active' => true,
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'roles',
                    'role_ids',
                    'is_active',
                ],
            ]);
        
        $this->assertDatabaseHas('role_profiles', [
            'name' => 'New Profile',
            'tenant_id' => $this->tenant->id,
        ]);
        
        $profileData = $response->json('data');
        $this->assertCount(2, $profileData['roles']);
    }

    /**
     * Test admin user can update profile
     */
    public function test_admin_can_update_profile(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $profile = RoleProfile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Profile',
            'roles' => [$this->role1->id],
        ]);
        
        $response = $this->putJson("/api/v1/admin/role-profiles/{$profile->id}", [
            'name' => 'Updated Profile',
            'description' => 'Updated description',
            'roles' => [$this->role1->id, $this->role2->id, $this->role3->id],
        ]);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('role_profiles', [
            'id' => $profile->id,
            'name' => 'Updated Profile',
            'description' => 'Updated description',
        ]);
        
        $profile->refresh();
        $this->assertCount(3, $profile->roles);
    }

    /**
     * Test admin user can delete profile
     */
    public function test_admin_can_delete_profile(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $profile = RoleProfile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Profile to Delete',
            'roles' => [$this->role1->id],
        ]);
        
        $response = $this->deleteJson("/api/v1/admin/role-profiles/{$profile->id}");
        
        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('role_profiles', [
            'id' => $profile->id,
        ]);
    }

    /**
     * Test admin user can assign profile to user
     */
    public function test_admin_can_assign_profile_to_user(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $profile = RoleProfile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Profile',
            'roles' => [$this->role1->id, $this->role2->id],
        ]);
        
        // Initially user has no roles
        $this->assertCount(0, $this->targetUser->roles);
        
        $response = $this->putJson("/api/v1/admin/users/{$this->targetUser->id}/assign-profile", [
            'profile_id' => $profile->id,
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'roles',
                ],
            ]);
        
        // Reload user with roles
        $this->targetUser->refresh();
        $this->targetUser->load('roles');
        
        // User should now have the profile's roles
        $this->assertGreaterThanOrEqual(2, $this->targetUser->roles->count());
        $roleIds = $this->targetUser->roles->pluck('id')->toArray();
        $this->assertContains($this->role1->id, $roleIds);
        $this->assertContains($this->role2->id, $roleIds);
    }

    /**
     * Test validation: roles must exist
     */
    public function test_validation_roles_must_exist(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->postJson('/api/v1/admin/role-profiles', [
            'name' => 'Invalid Profile',
            'roles' => ['non-existent-role-id'],
        ]);
        
        $response->assertStatus(422);
    }

    /**
     * Test validation: name must be unique per tenant
     */
    public function test_validation_name_must_be_unique_per_tenant(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        RoleProfile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Existing Profile',
            'roles' => [$this->role1->id],
        ]);
        
        $response = $this->postJson('/api/v1/admin/role-profiles', [
            'name' => 'Existing Profile',
            'roles' => [$this->role1->id],
        ]);
        
        $response->assertStatus(422);
    }

    /**
     * Test requires permission
     */
    public function test_requires_permission(): void
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/v1/admin/role-profiles');
        
        $response->assertStatus(403);
    }

    /**
     * Test respects tenant isolation
     */
    public function test_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        // Create profile in other tenant
        $otherProfile = RoleProfile::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Tenant Profile',
            'roles' => [$this->role1->id],
        ]);
        
        // Admin from this tenant should not see other tenant's profile
        $response = $this->getJson('/api/v1/admin/role-profiles');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $profileIds = array_column($data, 'id');
        $this->assertNotContains($otherProfile->id, $profileIds);
        
        // Admin from this tenant should not be able to access other tenant's profile
        $response = $this->getJson("/api/v1/admin/role-profiles/{$otherProfile->id}");
        $response->assertStatus(404);
    }

    /**
     * Test profile assignment adds roles without removing existing ones
     */
    public function test_profile_assignment_adds_roles_without_removing_existing(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        // Give user an existing role
        $this->targetUser->roles()->attach($this->role3->id);
        
        $profile = RoleProfile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Profile',
            'roles' => [$this->role1->id, $this->role2->id],
        ]);
        
        $response = $this->putJson("/api/v1/admin/users/{$this->targetUser->id}/assign-profile", [
            'profile_id' => $profile->id,
        ]);
        
        $response->assertStatus(200);
        
        // Reload user with roles
        $this->targetUser->refresh();
        $this->targetUser->load('roles');
        
        // User should have all roles: existing + profile roles
        $roleIds = $this->targetUser->roles->pluck('id')->toArray();
        $this->assertContains($this->role1->id, $roleIds);
        $this->assertContains($this->role2->id, $roleIds);
        $this->assertContains($this->role3->id, $roleIds); // Existing role should remain
    }
}
