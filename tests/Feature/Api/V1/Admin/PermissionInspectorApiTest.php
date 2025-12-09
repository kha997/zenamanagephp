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
 * Permission Inspector API Test
 * 
 * Round 236: Permission Inspector
 * 
 * Tests the admin endpoint for inspecting user permissions:
 * - GET /api/v1/admin/permissions/inspect
 * 
 * @group admin
 * @group permission-inspector
 * @group rbac
 */
class PermissionInspectorApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $adminUser;
    private User $regularUser;
    private User $targetUser;
    private User $otherTenantUser;
    private User $userWithoutRoles;
    private Role $role1;
    private Role $role2;
    private Permission $permission1;
    private Permission $permission2;
    private Permission $permission3;

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
        
        // Create admin user (super_admin bypasses permission checks)
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'super_admin',
            'email' => 'admin@test.com',
        ]);
        
        // Create regular user without admin permissions
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'email' => 'member@test.com',
        ]);
        
        // Create target user for inspection
        $this->targetUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'email' => 'target@test.com',
        ]);
        
        // Create user from other tenant
        $this->otherTenantUser = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'role' => 'member',
            'email' => 'other@test.com',
        ]);
        
        // Create user without roles
        $this->userWithoutRoles = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'email' => 'noroles@test.com',
        ]);
        
        // Create roles
        $this->role1 = Role::factory()->create([
            'name' => 'project_manager',
            'scope' => 'custom',
        ]);
        
        $this->role2 = Role::factory()->create([
            'name' => 'custom_role',
            'scope' => 'custom',
        ]);
        
        // Create permissions
        $this->permission1 = Permission::factory()->create([
            'code' => 'projects.cost.view',
            'module' => 'projects',
            'action' => 'cost.view',
        ]);
        
        $this->permission2 = Permission::factory()->create([
            'code' => 'projects.cost.edit',
            'module' => 'projects',
            'action' => 'cost.edit',
        ]);
        
        $this->permission3 = Permission::factory()->create([
            'code' => 'documents.view',
            'module' => 'documents',
            'action' => 'view',
        ]);
        
        // Assign permissions to roles
        $this->role1->permissions()->attach([
            $this->permission1->id,
            $this->permission2->id,
        ]);
        
        $this->role2->permissions()->attach([
            $this->permission3->id,
        ]);
        
        // Assign roles to target user
        $this->targetUser->roles()->attach([
            $this->role1->id,
            $this->role2->id,
        ]);
    }

    /**
     * Test can inspect user permissions
     */
    public function test_can_inspect_user_permissions(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->targetUser->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'roles' => [
                        '*' => [
                            'name',
                            'permissions',
                        ],
                    ],
                    'permissions' => [
                        '*' => [
                            'key',
                            'granted',
                            'sources',
                        ],
                    ],
                    'missing_permissions',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->targetUser->id, $data['user']['id']);
        $this->assertCount(2, $data['roles']);
    }

    /**
     * Test shows roles and permission sources
     */
    public function test_shows_roles_and_permission_sources(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->targetUser->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Check roles
        $roleNames = array_column($data['roles'], 'name');
        $this->assertContains('project_manager', $roleNames);
        $this->assertContains('custom_role', $roleNames);
        
        // Check permissions have sources
        $permissions = $data['permissions'];
        $costViewPerm = collect($permissions)->firstWhere('key', 'projects.cost.view');
        $this->assertNotNull($costViewPerm);
        $this->assertTrue($costViewPerm['granted']);
        $this->assertContains('project_manager', $costViewPerm['sources']);
    }

    /**
     * Test identifies missing permissions
     */
    public function test_identifies_missing_permissions(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->targetUser->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should have missing permissions (permissions from config that user doesn't have)
        $this->assertIsArray($data['missing_permissions']);
        
        // Check that some permissions are marked as not granted
        $permissions = $data['permissions'];
        $grantedCount = collect($permissions)->where('granted', true)->count();
        $notGrantedCount = collect($permissions)->where('granted', false)->count();
        
        $this->assertGreaterThan(0, $grantedCount);
        $this->assertGreaterThan(0, $notGrantedCount);
    }

    /**
     * Test filters permissions by module
     */
    public function test_filters_permissions_by_module(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        // Filter by cost
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->targetUser->id}&filter=cost");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $permissions = $data['permissions'];
        foreach ($permissions as $perm) {
            $this->assertStringStartsWith('projects.cost.', $perm['key']);
        }
        
        // Filter by document
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->targetUser->id}&filter=document");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $permissions = $data['permissions'];
        foreach ($permissions as $perm) {
            $this->assertStringStartsWith('documents.', $perm['key']);
        }
    }

    /**
     * Test requires permission
     */
    public function test_requires_permission(): void
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->targetUser->id}");
        
        // Should be blocked by ability:admin middleware first
        $response->assertStatus(403);
        
        // Check if it's blocked by admin middleware or permission check
        $errorId = $response->json('error.id');
        $this->assertContains($errorId, ['ADMIN_REQUIRED', 'PERMISSION_DENIED']);
    }

    /**
     * Test respects tenant isolation
     */
    public function test_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        // Try to inspect user from other tenant
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->otherTenantUser->id}");
        
        // Should be blocked by tenant isolation
        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'id' => 'TENANT_ISOLATION_VIOLATION',
                ],
            ]);
    }

    /**
     * Test user not found
     */
    public function test_user_not_found(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $fakeUserId = 'fake-user-id-' . uniqid();
        
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$fakeUserId}");
        
        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'id' => 'USER_NOT_FOUND',
                ],
            ]);
    }

    /**
     * Test user without roles returns empty sources
     */
    public function test_user_without_roles_returns_empty_sources(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->userWithoutRoles->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(0, $data['roles']);
        
        // All permissions should be not granted
        $permissions = $data['permissions'];
        foreach ($permissions as $perm) {
            $this->assertFalse($perm['granted']);
            $this->assertEmpty($perm['sources']);
        }
    }

    /**
     * Test validation requires user_id
     */
    public function test_validation_requires_user_id(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson('/api/v1/admin/permissions/inspect');
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test validation for filter values
     */
    public function test_validation_for_filter_values(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/v1/admin/permissions/inspect?user_id={$this->targetUser->id}&filter=invalid");
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filter']);
    }
}
