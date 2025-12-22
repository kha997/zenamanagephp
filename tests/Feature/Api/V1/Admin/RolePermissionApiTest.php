<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

/**
 * Role Permission API Test
 * 
 * Round 233: Admin UI for Roles & Permissions
 * 
 * Tests the admin endpoints for managing roles and permissions:
 * - GET /api/v1/admin/roles
 * - GET /api/v1/admin/permissions
 * - PUT /api/v1/admin/roles/{role}/permissions
 * 
 * @group admin
 * @group permissions
 * @group rbac
 */
class RolePermissionApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;
    private User $regularUser;
    private Role $testRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create admin user with users.manage_permissions
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
        
        // Create a test role
        $this->testRole = Role::factory()->create([
            'name' => 'Test Role',
            'scope' => 'system',
        ]);
    }

    /**
     * Test admin user can list roles
     */
    public function test_admin_can_list_roles(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson('/api/v1/admin/roles');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'scope',
                        'permissions',
                    ],
                ],
            ]);
        
        // Verify test role is in the list
        $data = $response->json('data');
        $roleIds = array_column($data, 'id');
        $this->assertContains($this->testRole->id, $roleIds);
    }

    /**
     * Test admin user can get permissions catalog
     */
    public function test_admin_can_get_permissions_catalog(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson('/api/v1/admin/permissions');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'groups' => [
                        '*' => [
                            'key',
                            'label',
                            'permissions' => [
                                '*' => [
                                    'key',
                                    'label',
                                    'description',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        
        // Verify groups exist
        $groups = $response->json('data.groups');
        $this->assertNotEmpty($groups);
        
        // Verify at least one group has permissions
        $hasPermissions = false;
        foreach ($groups as $group) {
            if (!empty($group['permissions'])) {
                $hasPermissions = true;
                break;
            }
        }
        $this->assertTrue($hasPermissions, 'At least one group should have permissions');
    }

    /**
     * Test admin user can update role permissions
     */
    public function test_admin_can_update_role_permissions(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        // Create some permissions in DB
        $permission1 = Permission::firstOrCreate(
            ['code' => 'projects.cost.view'],
            [
                'code' => 'projects.cost.view',
                'module' => 'projects',
                'action' => 'view',
                'description' => 'View cost data',
            ]
        );
        
        $permission2 = Permission::firstOrCreate(
            ['code' => 'projects.cost.edit'],
            [
                'code' => 'projects.cost.edit',
                'module' => 'projects',
                'action' => 'edit',
                'description' => 'Edit cost data',
            ]
        );
        
        $response = $this->putJson("/api/v1/admin/roles/{$this->testRole->id}/permissions", [
            'permissions' => ['projects.cost.view', 'projects.cost.edit'],
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'permissions',
                ],
            ]);
        
        // Verify permissions were synced
        $this->testRole->refresh();
        // Use relationship query directly to avoid attribute collision
        $permissionCodes = $this->testRole->permissions()->pluck('code')->toArray();
        
        $this->assertContains('projects.cost.view', $permissionCodes);
        $this->assertContains('projects.cost.edit', $permissionCodes);
    }

    /**
     * Test updating with invalid permissions returns 422
     */
    public function test_update_with_invalid_permissions_returns_422(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->putJson("/api/v1/admin/roles/{$this->testRole->id}/permissions", [
            'permissions' => ['invalid.permission.key'],
        ]);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'error' => [
                    'id',
                ],
            ]);
    }

    /**
     * Test updating non-existent role returns 404
     */
    public function test_update_nonexistent_role_returns_404(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $fakeRoleId = 'fake-role-id-' . uniqid();
        
        $response = $this->putJson("/api/v1/admin/roles/{$fakeRoleId}/permissions", [
            'permissions' => ['projects.cost.view'],
        ]);
        
        $response->assertStatus(404);
    }

    /**
     * Test non-admin user cannot access admin endpoints
     */
    public function test_regular_user_cannot_access_admin_endpoints(): void
    {
        Sanctum::actingAs($this->regularUser);
        
        // Try to list roles
        $response = $this->getJson('/api/v1/admin/roles');
        $response->assertStatus(403);
        
        // Try to get permissions
        $response = $this->getJson('/api/v1/admin/permissions');
        $response->assertStatus(403);
        
        // Try to update permissions
        $response = $this->putJson("/api/v1/admin/roles/{$this->testRole->id}/permissions", [
            'permissions' => ['projects.cost.view'],
        ]);
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot access admin endpoints
     */
    public function test_unauthenticated_user_cannot_access_admin_endpoints(): void
    {
        // Try to list roles
        $response = $this->getJson('/api/v1/admin/roles');
        $response->assertStatus(401);
        
        // Try to get permissions
        $response = $this->getJson('/api/v1/admin/permissions');
        $response->assertStatus(401);
        
        // Try to update permissions
        $response = $this->putJson("/api/v1/admin/roles/{$this->testRole->id}/permissions", [
            'permissions' => ['projects.cost.view'],
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test super_admin can access admin endpoints
     */
    public function test_super_admin_can_access_admin_endpoints(): void
    {
        $superAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'super_admin',
            'email' => 'superadmin@test.com',
        ]);
        
        Sanctum::actingAs($superAdmin);
        
        $response = $this->getJson('/api/v1/admin/roles');
        $response->assertStatus(200);
        
        $response = $this->getJson('/api/v1/admin/permissions');
        $response->assertStatus(200);
    }
}
