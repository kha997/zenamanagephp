<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Rbac\RbacSeedGrants;
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
            // OWN-2026-024 H2b (D4): single seed grant path — additive, global roles only, never platform.*, never a revoked grant.
            RbacSeedGrants::grantIds((string) $role->id, $permissionIds);
        }
    }

    private function resolveAdminRoles(array $loweredNames)
    {
        $placeholders = implode(',', array_fill(0, count($loweredNames), '?'));

        // OWN-2026-024 H2b: global roles only — a tenant role named like an admin alias must never receive these grants.
        return Role::whereRaw("LOWER(name) IN ({$placeholders})", $loweredNames)->whereNull('tenant_id')->get();
    }
}
