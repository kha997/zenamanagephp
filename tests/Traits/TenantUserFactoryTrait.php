<?php declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait TenantUserFactoryTrait
{
    protected function createTenantUser(Tenant $tenant, array $attributes = [], ?array $roles = null, array $permissions = []): User
    {
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $attributes));

        $this->assignApiRoles(
            $user,
            $roles ?? ['super_admin', 'Admin'],
            $permissions
        );

        return $user;
    }

    private function assignApiRoles(User $user, array $roles = [], array $permissions = []): void
    {
        $defaultPermissions = ['project.read', 'project.write'];
        $permissions = array_unique(array_merge($defaultPermissions, $permissions));

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                [
                    'scope' => 'system',
                    'description' => Str::title(str_replace('_', ' ', $roleName)),
                ]
            );

            $user->roles()->syncWithoutDetaching($role->id);
            $this->ensurePermissionAttached($role, $permissions);

            if (method_exists($user, 'systemRoles')) {
                $user->systemRoles()->syncWithoutDetaching($role->id);
            }
        }
    }

    private function ensurePermissionAttached(Role $role, array $permissionNames): void
    {
        foreach ($permissionNames as $permissionName) {
            $parts = explode('.', $permissionName);
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                [
                    'code' => $permissionName,
                    'module' => $parts[0] ?? $permissionName,
                    'action' => $parts[1] ?? '*',
                    'description' => ucfirst(str_replace('.', ' ', $permissionName)),
                ]
            );

            $role->permissions()->syncWithoutDetaching($permission->id);
        }
    }
}
