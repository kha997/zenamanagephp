<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Support\PlatformPermissions;
use Illuminate\Support\Facades\DB;

/**
 * OWN-2026-024 (O-17h) H2b — the single grant path of every RBAC seeder and production:rbac (Owner Gate 2, D4).
 *
 *  - additive only: never detaches, never rewrites an existing pivot row (a 'manual' grant keeps its source);
 *  - never attaches a platform.* code (platform authority is server-provisioned only, D6);
 *  - never restores a deliberately revoked grant (active role_permission_revocations row);
 *  - resolves seeder alias role NAMES to GLOBAL roles only (tenant_id IS NULL): a tenant-created role that happens to be called
 *    "Admin" or "System Admin" can never receive the alias grants.
 */
final class RbacSeedGrants
{
    /**
     * @param list<string> $roleNames
     * @param list<string> $permissionCodes
     */
    public static function grantCodesToGlobalRolesNamed(array $roleNames, array $permissionCodes): void
    {
        $ids = DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        if ($ids === []) {
            return;
        }
        foreach (self::globalRoleIdsNamed($roleNames) as $roleId) {
            self::grantIds($roleId, $ids);
        }
    }

    /**
     * @param list<string> $roleNames
     * @return list<string>
     */
    public static function globalRoleIdsNamed(array $roleNames): array
    {
        $lowered = array_values(array_unique(array_map(static fn (string $n): string => strtolower($n), $roleNames)));
        if ($lowered === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($lowered), '?'));

        return DB::table('roles')
            ->whereRaw("LOWER(name) IN ({$placeholders})", $lowered)
            ->whereNull('tenant_id')
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    /** @param iterable<mixed> $permissionIds */
    public static function grantIds(string $roleId, iterable $permissionIds): int
    {
        $wanted = [];
        foreach ($permissionIds as $id) {
            $wanted[(string) $id] = true;
        }
        if ($wanted === []) {
            return 0;
        }
        $platformIds = DB::table('permissions')->where('code', 'like', PlatformPermissions::PREFIX . '%')->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $revoked = PermissionRevocations::revokedPermissionIds($roleId);
        $existing = DB::table('role_permissions')->where('role_id', $roleId)->pluck('permission_id')->map(static fn ($id): string => (string) $id)->all();

        $rows = [];
        foreach (array_keys($wanted) as $id) {
            if (in_array($id, $platformIds, true) || in_array($id, $revoked, true) || in_array($id, $existing, true)) {
                continue;
            }
            $rows[] = ['role_id' => $roleId, 'permission_id' => $id, 'created_at' => now(), 'updated_at' => now()] + RbacSchema::grantSourceAttributes('seed');
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('role_permissions')->insertOrIgnore($chunk);
        }

        return count($rows);
    }
}
