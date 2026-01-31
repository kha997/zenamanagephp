<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ZenaAdminRolePermissionSeeder extends Seeder
{
    public const ADMIN_ROLE_NAMES = [
        'System Admin',
        'Admin',
        'super_admin',
        'system_admin',
    ];

    public function run(): void
    {
        $loweredNames = array_map('strtolower', self::ADMIN_ROLE_NAMES);
        if (empty($loweredNames)) {
            return;
        }

        $roles = $this->resolveAdminRoles($loweredNames);
        if ($roles->isEmpty()) {
            return;
        }

        $permissionCodes = array_column(ZenaPermissionsSeeder::CANONICAL_PERMISSIONS, 'code');
        $permissionIds = Permission::whereIn('code', $permissionCodes)->pluck('id')->all();

        if (empty($permissionIds)) {
            return;
        }

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    private function resolveAdminRoles(array $loweredNames)
    {
        $placeholders = implode(',', array_fill(0, count($loweredNames), '?'));

        return Role::whereRaw("LOWER(name) IN ({$placeholders})", $loweredNames)->get();
    }
}
