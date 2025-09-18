<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use App\Models\User;
use App\Models\Tenant;

/**
 * Class TestDatabaseSeeder
 * 
 * Seeds test database with essential data for testing
 * Creates roles, permissions, and test users
 * 
 * @package Database\Seeders
 */
class TestDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * @return void
     */
    public function run(): void
    {
        // Create test tenant
        $tenant = Tenant::firstOrCreate([
            'id' => 1,
            'name' => 'Test Tenant',
            'domain' => 'test.zena.local'
        ]);

        // Create system roles
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'scope' => 'system',
            'description' => 'System Administrator'
        ]);

        $pmRole = Role::firstOrCreate([
            'name' => 'project_manager',
            'scope' => 'system',
            'description' => 'Project Manager'
        ]);

        $userRole = Role::firstOrCreate([
            'name' => 'user',
            'scope' => 'system',
            'description' => 'Regular User'
        ]);

        // Create permissions
        $permissions = [
            'project.create', 'project.view', 'project.update', 'project.delete',
            'component.create', 'component.view', 'component.update', 'component.delete',
            'task.create', 'task.view', 'task.update', 'task.delete', 'task.assign',
            'user.manage', 'role.manage'
        ];

        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            Permission::firstOrCreate([
                'code' => $permissionCode,
                'module' => $parts[0],
                'action' => $parts[1],
                'description' => ucfirst(str_replace('.', ' ', $permissionCode))
            ]);
        }

        // Assign permissions to roles
        $adminPermissions = Permission::all();
        $adminRole->permissions()->sync($adminPermissions->pluck('id'));

        $pmPermissions = Permission::whereIn('code', [
            'project.view', 'project.update',
            'component.create', 'component.view', 'component.update',
            'task.create', 'task.view', 'task.update', 'task.assign'
        ])->get();
        $pmRole->permissions()->sync($pmPermissions->pluck('id'));

        $userPermissions = Permission::whereIn('code', [
            'project.view', 'task.view', 'task.update'
        ])->get();
        $userRole->permissions()->sync($userPermissions->pluck('id'));

        // Create test users
        $adminUser = User::firstOrCreate([
            'email' => 'admin@test.com'
        ], [
            'name' => 'Test Admin',
            'tenant_id' => $tenant->id,
            'password' => bcrypt('password')
        ]);
        $adminUser->systemRoles()->sync([$adminRole->id]);

        $pmUser = User::firstOrCreate([
            'email' => 'pm@test.com'
        ], [
            'name' => 'Test Project Manager',
            'tenant_id' => $tenant->id,
            'password' => bcrypt('password')
        ]);
        $pmUser->systemRoles()->sync([$pmRole->id]);

        $regularUser = User::firstOrCreate([
            'email' => 'user@test.com'
        ], [
            'name' => 'Test User',
            'tenant_id' => $tenant->id,
            'password' => bcrypt('password')
        ]);
        $regularUser->systemRoles()->sync([$userRole->id]);
    }
}