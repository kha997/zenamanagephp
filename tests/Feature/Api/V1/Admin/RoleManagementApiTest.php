<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

/**
 * Role Management API Test
 * 
 * Round 234: Admin RBAC - Roles CRUD + User-Role Assignment
 * 
 * Tests the admin endpoints for managing roles:
 * - GET /api/v1/admin/roles
 * - POST /api/v1/admin/roles
 * - PUT /api/v1/admin/roles/{role}
 * - DELETE /api/v1/admin/roles/{role}
 * 
 * @group admin
 * @group roles
 * @group rbac
 */
class RoleManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;
    private User $regularUser;
    private Role $testRole;
    private Role $systemRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create admin user with system.roles.manage permission
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
            'scope' => 'custom',
        ]);
        
        // Create a system role (admin)
        $this->systemRole = Role::factory()->create([
            'name' => 'admin',
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
                        'description',
                        'is_active',
                        'is_system',
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
     * Test admin user can create role
     */
    public function test_admin_can_create_role(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->postJson('/api/v1/admin/roles', [
            'name' => 'New Custom Role',
            'description' => 'A new custom role for testing',
            'scope' => 'custom',
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'scope',
                    'description',
                    'is_active',
                    'is_system',
                ],
            ]);
        
        $this->assertDatabaseHas('zena_roles', [
            'name' => 'New Custom Role',
            'scope' => 'custom',
        ]);
        
        // Verify sync to legacy roles table if it exists
        if (Schema::hasTable('roles')) {
            $roleData = $response->json('data');
            $this->assertDatabaseHas('roles', [
                'id' => $roleData['id'],
                'name' => 'New Custom Role',
            ]);
        }
    }

    /**
     * Test cannot create role with system role name
     */
    public function test_cannot_create_role_with_system_role_name(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->postJson('/api/v1/admin/roles', [
            'name' => 'admin', // System role name
            'description' => 'Trying to create system role',
        ]);
        
        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'id' => 'SYSTEM_ROLE_CONFLICT',
                ],
            ]);
    }

    /**
     * Test admin user can update role
     */
    public function test_admin_can_update_role(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->putJson("/api/v1/admin/roles/{$this->testRole->id}", [
            'name' => 'Updated Role Name',
            'description' => 'Updated description',
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Role Name',
                    'description' => 'Updated description',
                ],
            ]);
        
        $this->assertDatabaseHas('zena_roles', [
            'id' => $this->testRole->id,
            'name' => 'Updated Role Name',
        ]);
    }

    /**
     * Test cannot update system role
     */
    public function test_cannot_update_system_role(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->putJson("/api/v1/admin/roles/{$this->systemRole->id}", [
            'name' => 'Updated Admin',
            'description' => 'Trying to update system role',
        ]);
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'id' => 'SYSTEM_ROLE_PROTECTED',
                ],
            ]);
    }

    /**
     * Test admin user can delete role
     */
    public function test_admin_can_delete_role(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $roleToDelete = Role::factory()->create([
            'name' => 'Role To Delete',
            'scope' => 'custom',
        ]);
        
        $response = $this->deleteJson("/api/v1/admin/roles/{$roleToDelete->id}");
        
        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('zena_roles', [
            'id' => $roleToDelete->id,
        ]);
        
        // Verify sync to legacy roles table if it exists
        if (Schema::hasTable('roles')) {
            $this->assertDatabaseMissing('roles', [
                'id' => $roleToDelete->id,
            ]);
        }
    }

    /**
     * Test cannot delete system role
     */
    public function test_cannot_delete_system_role(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->deleteJson("/api/v1/admin/roles/{$this->systemRole->id}");
        
        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'id' => 'SYSTEM_ROLE_PROTECTED',
                ],
            ]);
    }

    /**
     * Test cannot delete role assigned to users
     */
    public function test_cannot_delete_role_assigned_to_users(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        // Assign role to user
        $this->adminUser->roles()->attach($this->testRole->id);
        
        $response = $this->deleteJson("/api/v1/admin/roles/{$this->testRole->id}");
        
        $response->assertStatus(409)
            ->assertJson([
                'error' => [
                    'id' => 'ROLE_IN_USE',
                ],
            ]);
    }

    /**
     * Test validation: unique role name
     */
    public function test_validation_unique_role_name(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->postJson('/api/v1/admin/roles', [
            'name' => $this->testRole->name, // Duplicate name
            'description' => 'Duplicate role',
        ]);
        
        $response->assertStatus(422);
        
        // Check that validation error exists (either in details.validation.name or error message)
        $responseData = $response->json();
        $this->assertTrue(
            isset($responseData['details']['validation']['name']) || 
            isset($responseData['error']['id'])
        );
    }

    /**
     * Test permission required
     */
    public function test_permission_required(): void
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/v1/admin/roles');
        $response->assertStatus(403);
        
        $response = $this->postJson('/api/v1/admin/roles', [
            'name' => 'New Role',
        ]);
        $response->assertStatus(403);
        
        $response = $this->putJson("/api/v1/admin/roles/{$this->testRole->id}", [
            'name' => 'Updated',
        ]);
        $response->assertStatus(403);
        
        $response = $this->deleteJson("/api/v1/admin/roles/{$this->testRole->id}");
        $response->assertStatus(403);
    }

    /**
     * Test sync with legacy zena_roles table
     */
    public function test_sync_with_legacy_roles_table(): void
    {
        if (!Schema::hasTable('roles')) {
            $this->markTestSkipped('Legacy roles table does not exist');
        }
        
        Sanctum::actingAs($this->adminUser);
        
        // Create role
        $response = $this->postJson('/api/v1/admin/roles', [
            'name' => 'Legacy Sync Test',
            'description' => 'Testing legacy sync',
            'scope' => 'custom',
        ]);
        
        $response->assertStatus(201);
        $roleData = $response->json('data');
        
        // Verify sync to legacy table
        $this->assertDatabaseHas('roles', [
            'id' => $roleData['id'],
            'name' => 'Legacy Sync Test',
        ]);
        
        // Update role
        $this->putJson("/api/v1/admin/roles/{$roleData['id']}", [
            'name' => 'Legacy Sync Updated',
        ]);
        
        // Verify update sync
        $this->assertDatabaseHas('roles', [
            'id' => $roleData['id'],
            'name' => 'Legacy Sync Updated',
        ]);
        
        // Delete role
        $this->deleteJson("/api/v1/admin/roles/{$roleData['id']}");
        
        // Verify delete sync
        $this->assertDatabaseMissing('roles', [
            'id' => $roleData['id'],
        ]);
    }
}
