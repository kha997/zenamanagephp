<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Test RBAC Roles và Permissions System
 * 
 * Kịch bản: Tạo roles → Assign permissions → Test user permissions → Test access control
 */
class RBACRolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $projectManager;
    private $designer;
    private $engineer;
    private $clientRep;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'trial',
            'is_active' => true,
        ]);

        // Tạo project
        $this->project = Project::create([
            'name' => 'Test Project',
            'code' => 'RBAC-TEST-001',
            'description' => 'Test Description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => null, // Sẽ được set sau khi tạo users
        ]);

        // Tạo Project Manager
        $this->projectManager = User::create([
            'name' => 'Project Manager',
            'email' => 'project.manager@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo Designer
        $this->designer = User::create([
            'name' => 'Designer',
            'email' => 'designer@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo Engineer
        $this->engineer = User::create([
            'name' => 'Engineer',
            'email' => 'engineer@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo Client Representative
        $this->clientRep = User::create([
            'name' => 'Client Representative',
            'email' => 'client.rep@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Cập nhật project với created_by
        $this->project->update(['created_by' => $this->projectManager->id]);
    }

    /**
     * Test tạo roles với different scopes
     */
    public function test_can_create_roles_with_different_scopes(): void
    {
        // Tạo system role
        $systemRole = Role::create([
            'name' => 'System Administrator',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'description' => 'Full system access',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Tạo custom role
        $customRole = Role::create([
            'name' => 'Senior Designer',
            'scope' => Role::SCOPE_CUSTOM,
            'allow_override' => false,
            'description' => 'Senior design role',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Tạo project role
        $projectRole = Role::create([
            'name' => 'Project Lead',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => true,
            'description' => 'Project leadership role',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Kiểm tra roles được tạo thành công
        $this->assertDatabaseHas('roles', [
            'id' => $systemRole->id,
            'name' => 'System Administrator',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $customRole->id,
            'name' => 'Senior Designer',
            'scope' => Role::SCOPE_CUSTOM,
            'allow_override' => false,
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $projectRole->id,
            'name' => 'Project Lead',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => true,
        ]);

        // Kiểm tra relationships
        $this->assertEquals($this->tenant->id, $systemRole->tenant_id);
        $this->assertEquals($this->tenant->id, $customRole->tenant_id);
        $this->assertEquals($this->tenant->id, $projectRole->tenant_id);
    }

    /**
     * Test tạo permissions với different modules và actions
     */
    public function test_can_create_permissions_with_modules_and_actions(): void
    {
        // Tạo permissions cho different modules
        $taskPermissions = [
            Permission::create([
                'code' => 'task.create',
                'module' => 'task',
                'action' => 'create',
                'description' => 'Create tasks',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'task.edit',
                'module' => 'task',
                'action' => 'edit',
                'description' => 'Edit tasks',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'task.delete',
                'module' => 'task',
                'action' => 'delete',
                'description' => 'Delete tasks',
                'is_active' => true,
            ]),
        ];

        $projectPermissions = [
            Permission::create([
                'code' => 'project.view',
                'module' => 'project',
                'action' => 'view',
                'description' => 'View projects',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'project.edit',
                'module' => 'project',
                'action' => 'edit',
                'description' => 'Edit projects',
                'is_active' => true,
            ]),
        ];

        $userPermissions = [
            Permission::create([
                'code' => 'user.view',
                'module' => 'user',
                'action' => 'view',
                'description' => 'View users',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'user.edit',
                'module' => 'user',
                'action' => 'edit',
                'description' => 'Edit users',
                'is_active' => true,
            ]),
        ];

        // Kiểm tra permissions được tạo thành công
        foreach ($taskPermissions as $permission) {
            $this->assertDatabaseHas('permissions', [
                'id' => $permission->id,
                'code' => $permission->code,
                'module' => 'task',
                'action' => $permission->action,
            ]);
        }

        foreach ($projectPermissions as $permission) {
            $this->assertDatabaseHas('permissions', [
                'id' => $permission->id,
                'code' => $permission->code,
                'module' => 'project',
                'action' => $permission->action,
            ]);
        }

        foreach ($userPermissions as $permission) {
            $this->assertDatabaseHas('permissions', [
                'id' => $permission->id,
                'code' => $permission->code,
                'module' => 'user',
                'action' => $permission->action,
            ]);
        }

        // Kiểm tra auto-generated codes
        $this->assertEquals('task.create', $taskPermissions[0]->code);
        $this->assertEquals('project.view', $projectPermissions[0]->code);
        $this->assertEquals('user.edit', $userPermissions[1]->code);
    }

    /**
     * Test assign permissions to roles
     */
    public function test_can_assign_permissions_to_roles(): void
    {
        // Tạo role
        $projectManagerRole = Role::create([
            'name' => 'Project Manager',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'description' => 'Project management role',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Tạo permissions
        $permissions = [
            Permission::create([
                'code' => 'project.view',
                'module' => 'project',
                'action' => 'view',
                'description' => 'View projects',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'project.edit',
                'module' => 'project',
                'action' => 'edit',
                'description' => 'Edit projects',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'task.create',
                'module' => 'task',
                'action' => 'create',
                'description' => 'Create tasks',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'task.edit',
                'module' => 'task',
                'action' => 'edit',
                'description' => 'Edit tasks',
                'is_active' => true,
            ]),
        ];

        // Assign permissions to role
        $projectManagerRole->permissions()->attach($permissions[0]->id, ['allow_override' => true]);
        $projectManagerRole->permissions()->attach($permissions[1]->id, ['allow_override' => true]);
        $projectManagerRole->permissions()->attach($permissions[2]->id, ['allow_override' => false]);
        $projectManagerRole->permissions()->attach($permissions[3]->id, ['allow_override' => false]);

        // Kiểm tra permissions được assign thành công
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $projectManagerRole->id,
            'permission_id' => $permissions[0]->id,
            'allow_override' => true,
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $projectManagerRole->id,
            'permission_id' => $permissions[1]->id,
            'allow_override' => true,
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $projectManagerRole->id,
            'permission_id' => $permissions[2]->id,
            'allow_override' => false,
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $projectManagerRole->id,
            'permission_id' => $permissions[3]->id,
            'allow_override' => false,
        ]);

        // Kiểm tra relationships
        $this->assertCount(4, $projectManagerRole->permissions);
        $this->assertTrue($projectManagerRole->permissions->contains($permissions[0]));
        $this->assertTrue($projectManagerRole->permissions->contains($permissions[1]));
        $this->assertTrue($projectManagerRole->permissions->contains($permissions[2]));
        $this->assertTrue($projectManagerRole->permissions->contains($permissions[3]));
    }

    /**
     * Test assign roles to users
     */
    public function test_can_assign_roles_to_users(): void
    {
        // Tạo roles
        $projectManagerRole = Role::create([
            'name' => 'Project Manager',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'description' => 'Project management role',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        $designerRole = Role::create([
            'name' => 'Designer',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => false,
            'description' => 'Design role',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Assign roles to users
        DB::table('user_roles')->insert([
            'user_id' => $this->projectManager->id,
            'role_id' => $projectManagerRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $this->designer->id,
            'role_id' => $designerRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kiểm tra roles được assign thành công
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->projectManager->id,
            'role_id' => $projectManagerRole->id,
        ]);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->designer->id,
            'role_id' => $designerRole->id,
        ]);
    }

    /**
     * Test permission checking logic
     */
    public function test_permission_checking_logic(): void
    {
        // Tạo role và permissions
        $projectManagerRole = Role::create([
            'name' => 'Project Manager',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'description' => 'Project management role',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        $viewProjectPermission = Permission::create([
            'code' => 'project.view',
            'module' => 'project',
            'action' => 'view',
            'description' => 'View projects',
            'is_active' => true,
        ]);

        $editProjectPermission = Permission::create([
            'code' => 'project.edit',
            'module' => 'project',
            'action' => 'edit',
            'description' => 'Edit projects',
            'is_active' => true,
        ]);

        $deleteProjectPermission = Permission::create([
            'code' => 'project.delete',
            'module' => 'project',
            'action' => 'delete',
            'description' => 'Delete projects',
            'is_active' => true,
        ]);

        // Assign permissions to role
        $projectManagerRole->permissions()->attach($viewProjectPermission->id, ['allow_override' => true]);
        $projectManagerRole->permissions()->attach($editProjectPermission->id, ['allow_override' => true]);
        // Không assign delete permission

        // Assign role to user
        DB::table('user_roles')->insert([
            'user_id' => $this->projectManager->id,
            'role_id' => $projectManagerRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Test permission checking (simulate)
        $userPermissions = $this->getUserPermissions($this->projectManager->id);

        // User should have view and edit permissions
        $this->assertTrue(in_array('project.view', $userPermissions));
        $this->assertTrue(in_array('project.edit', $userPermissions));
        
        // User should NOT have delete permission
        $this->assertFalse(in_array('project.delete', $userPermissions));
    }

    /**
     * Test role hierarchy và scope inheritance
     */
    public function test_role_hierarchy_and_scope_inheritance(): void
    {
        // Tạo system role với broad permissions
        $systemRole = Role::create([
            'name' => 'System Administrator',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'description' => 'Full system access',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Tạo project-specific role với limited permissions
        $projectRole = Role::create([
            'name' => 'Project Member',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => false,
            'description' => 'Project-specific access',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Tạo permissions
        $allPermissions = [
            Permission::create([
                'code' => 'project.view',
                'module' => 'project',
                'action' => 'view',
                'description' => 'View projects',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'project.edit',
                'module' => 'project',
                'action' => 'edit',
                'description' => 'Edit projects',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'user.view',
                'module' => 'user',
                'action' => 'view',
                'description' => 'View users',
                'is_active' => true,
            ]),
            Permission::create([
                'code' => 'user.edit',
                'module' => 'user',
                'action' => 'edit',
                'description' => 'Edit users',
                'is_active' => true,
            ]),
        ];

        // System role gets all permissions
        foreach ($allPermissions as $permission) {
            $systemRole->permissions()->attach($permission->id, ['allow_override' => true]);
        }

        // Project role gets only project permissions
        $projectRole->permissions()->attach($allPermissions[0]->id, ['allow_override' => false]); // project.view
        $projectRole->permissions()->attach($allPermissions[1]->id, ['allow_override' => false]); // project.edit

        // Assign roles to users
        DB::table('user_roles')->insert([
            'user_id' => $this->projectManager->id,
            'role_id' => $systemRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $this->designer->id,
            'role_id' => $projectRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Test permission inheritance
        $pmPermissions = $this->getUserPermissions($this->projectManager->id);
        $designerPermissions = $this->getUserPermissions($this->designer->id);

        // PM should have all permissions (system role)
        $this->assertTrue(in_array('project.view', $pmPermissions));
        $this->assertTrue(in_array('project.edit', $pmPermissions));
        $this->assertTrue(in_array('user.view', $pmPermissions));
        $this->assertTrue(in_array('user.edit', $pmPermissions));

        // Designer should have only project permissions (project role)
        $this->assertTrue(in_array('project.view', $designerPermissions));
        $this->assertTrue(in_array('project.edit', $designerPermissions));
        $this->assertFalse(in_array('user.view', $designerPermissions));
        $this->assertFalse(in_array('user.edit', $designerPermissions));
    }

    /**
     * Test RBAC workflow end-to-end
     */
    public function test_rbac_workflow_end_to_end(): void
    {
        // 1. Tạo roles
        $roles = [
            'admin' => Role::create([
                'name' => 'Administrator',
                'scope' => Role::SCOPE_SYSTEM,
                'allow_override' => true,
                'description' => 'System administrator',
                'is_active' => true,
                'tenant_id' => $this->tenant->id,
            ]),
            'pm' => Role::create([
                'name' => 'Project Manager',
                'scope' => Role::SCOPE_SYSTEM,
                'allow_override' => true,
                'description' => 'Project manager',
                'is_active' => true,
                'tenant_id' => $this->tenant->id,
            ]),
            'designer' => Role::create([
                'name' => 'Designer',
                'scope' => Role::SCOPE_SYSTEM,
                'allow_override' => false,
                'description' => 'Designer',
                'is_active' => true,
                'tenant_id' => $this->tenant->id,
            ]),
        ];

        // 2. Tạo permissions
        $permissions = [
            'project.view' => Permission::create([
                'code' => 'project.view',
                'module' => 'project',
                'action' => 'view',
                'description' => 'View projects',
                'is_active' => true,
            ]),
            'project.edit' => Permission::create([
                'code' => 'project.edit',
                'module' => 'project',
                'action' => 'edit',
                'description' => 'Edit projects',
                'is_active' => true,
            ]),
            'task.create' => Permission::create([
                'code' => 'task.create',
                'module' => 'task',
                'action' => 'create',
                'description' => 'Create tasks',
                'is_active' => true,
            ]),
            'task.edit' => Permission::create([
                'code' => 'task.edit',
                'module' => 'task',
                'action' => 'edit',
                'description' => 'Edit tasks',
                'is_active' => true,
            ]),
        ];

        // 3. Assign permissions to roles
        // Admin gets all permissions
        foreach ($permissions as $permission) {
            $roles['admin']->permissions()->attach($permission->id, ['allow_override' => true]);
        }

        // PM gets project and task permissions
        $roles['pm']->permissions()->attach($permissions['project.view']->id, ['allow_override' => true]);
        $roles['pm']->permissions()->attach($permissions['project.edit']->id, ['allow_override' => true]);
        $roles['pm']->permissions()->attach($permissions['task.create']->id, ['allow_override' => true]);
        $roles['pm']->permissions()->attach($permissions['task.edit']->id, ['allow_override' => true]);

        // Designer gets only task permissions
        $roles['designer']->permissions()->attach($permissions['task.create']->id, ['allow_override' => false]);
        $roles['designer']->permissions()->attach($permissions['task.edit']->id, ['allow_override' => false]);

        // 4. Assign roles to users
        DB::table('user_roles')->insert([
            'user_id' => $this->projectManager->id,
            'role_id' => $roles['pm']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $this->designer->id,
            'role_id' => $roles['designer']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Test complete workflow
        $pmPermissions = $this->getUserPermissions($this->projectManager->id);
        $designerPermissions = $this->getUserPermissions($this->designer->id);

        // PM should have project and task permissions
        $this->assertTrue(in_array('project.view', $pmPermissions));
        $this->assertTrue(in_array('project.edit', $pmPermissions));
        $this->assertTrue(in_array('task.create', $pmPermissions));
        $this->assertTrue(in_array('task.edit', $pmPermissions));

        // Designer should have only task permissions
        $this->assertFalse(in_array('project.view', $designerPermissions));
        $this->assertFalse(in_array('project.edit', $designerPermissions));
        $this->assertTrue(in_array('task.create', $designerPermissions));
        $this->assertTrue(in_array('task.edit', $designerPermissions));

        // Kiểm tra database integrity
        $this->assertDatabaseHas('roles', [
            'name' => 'Administrator',
            'scope' => Role::SCOPE_SYSTEM,
        ]);

        $this->assertDatabaseHas('permissions', [
            'code' => 'project.view',
            'module' => 'project',
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $roles['pm']->id,
            'permission_id' => $permissions['project.view']->id,
        ]);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $this->projectManager->id,
            'role_id' => $roles['pm']->id,
        ]);
    }

    /**
     * Helper method để get user permissions (simulate)
     */
    private function getUserPermissions(string $userId): array
    {
        $userRoles = DB::table('user_roles')
            ->where('user_id', $userId)
            ->pluck('role_id');

        $permissions = [];
        foreach ($userRoles as $roleId) {
            $rolePermissions = DB::table('role_permissions')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->where('role_permissions.role_id', $roleId)
                ->pluck('permissions.code')
                ->toArray();
            
            $permissions = array_merge($permissions, $rolePermissions);
        }

        return array_unique($permissions);
    }
}
