<?php declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;

trait SeedsAdminRolesTrait
{
    /**
     * Ensure the system-level admin roles and their core permissions exist.
     *
     * @return array{
     *     super_admin_role: Role,
     *     org_admin_role: Role,
     *     permissions: array<string, Permission>
     * }
     */
    protected function seedAdminRolesAndPermissions(): array
    {
        $permissionDefinitions = [
            'admin.access' => [
                'module' => 'admin',
                'action' => 'access',
                'description' => 'Super Admin access',
            ],
            'admin.access.tenant' => [
                'module' => 'admin',
                'action' => 'access.tenant',
                'description' => 'Org Admin access',
            ],
            'admin.templates.manage' => [
                'module' => 'admin',
                'action' => 'templates.manage',
                'description' => 'Manage template sets',
            ],
        ];

        $permissions = [];
        foreach ($permissionDefinitions as $code => $attributes) {
            $permissions[$code] = Permission::updateOrCreate(
                ['code' => $code],
                $attributes
            );
        }

        $superAdminRole = Role::updateOrCreate(
            ['name' => 'super_admin'],
            [
                'scope' => Role::SCOPE_SYSTEM,
                'description' => 'Super admin role for tests',
                'is_active' => true,
            ]
        );
        $superAdminRole->permissions()
            ->syncWithoutDetaching([$permissions['admin.access']->id]);

        $orgAdminRole = Role::updateOrCreate(
            ['name' => 'org_admin'],
            [
                'scope' => Role::SCOPE_SYSTEM,
                'description' => 'Org admin role for tests',
                'is_active' => true,
            ]
        );
        $orgAdminRole->permissions()
            ->syncWithoutDetaching([
                $permissions['admin.access.tenant']->id,
                $permissions['admin.templates.manage']->id,
            ]);

        $this->clearPermissionCache();

        return [
            'super_admin_role' => $superAdminRole,
            'org_admin_role' => $orgAdminRole,
            'permissions' => $permissions,
        ];
    }

    protected function clearPermissionCache(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // in testing we can safely ignore cache flush failures
        }
    }
}
