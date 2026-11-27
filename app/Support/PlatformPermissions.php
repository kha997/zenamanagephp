<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * OWN-2026-024 (O-17h) H2a — the dedicated host/platform permission namespace (Owner Gate 2, D1 + D6).
 *
 * Platform authority acts on the whole host (every tenant): it is never part of a tenant role. These codes are created by
 * PlatformPermissionCatalogueSeeder and granted to NOBODY: no seeder, alias list (ADMIN_ROLE_NAMES / OWNER_ROLE_NAMES) or bulk
 * "all permissions" grant may hand them out, and no HTTP role-management path may create, attach or assign them. Provisioning a
 * Platform Operator is a separate, server-only step (H2b). Until someone is provisioned every platform route fails closed —
 * except that the legacy `super_admin` middleware short-circuit still admits that role until H3 (H2a alone is NOT the fix).
 */
final class PlatformPermissions
{
    public const PREFIX = 'platform.';

    /** Read-only host information: cache/realtime/performance statistics, launch checks, legacy admin console pages. */
    public const OPS_VIEW = 'platform.ops.view';

    /** Mutating, shared: invalidate / warm the cache shared by every tenant. */
    public const CACHE_MANAGE = 'platform.cache.manage';

    /** Mutating, shared: publish realtime broadcasts / system notifications to connected users. */
    public const REALTIME_BROADCAST = 'platform.realtime.broadcast';

    /** Destructive: whole-database maintenance, log and telemetry clean-up, performance "optimize" actions. */
    public const MAINTENANCE_EXECUTE = 'platform.maintenance.execute';

    /** Sensitive export: a full dump of every tenant's data onto the host. */
    public const BACKUP_EXECUTE = 'platform.backup.execute';

    /** @var array<string, array{action: string, description: string}> */
    public const CATALOGUE = [
        self::OPS_VIEW => ['action' => 'ops.view', 'description' => 'Platform: view host operation status (read-only)'],
        self::CACHE_MANAGE => ['action' => 'cache.manage', 'description' => 'Platform: invalidate or warm the shared cache (all tenants)'],
        self::REALTIME_BROADCAST => ['action' => 'realtime.broadcast', 'description' => 'Platform: publish realtime broadcasts and system notifications'],
        self::MAINTENANCE_EXECUTE => ['action' => 'maintenance.execute', 'description' => 'Platform: run destructive host maintenance (database, logs)'],
        self::BACKUP_EXECUTE => ['action' => 'backup.execute', 'description' => 'Platform: create a full database backup of every tenant'],
    ];

    public static function isPlatformCode(?string $code): bool
    {
        return $code !== null && str_starts_with(strtolower(trim($code)), self::PREFIX);
    }

    /** A permission module name that would generate a platform code (module.action). */
    public static function isPlatformModule(?string $module): bool
    {
        return $module !== null && strtolower(trim($module)) === rtrim(self::PREFIX, '.');
    }

    /** @param iterable<mixed> $codes */
    public static function containsPlatformCode(iterable $codes): bool
    {
        foreach ($codes as $code) {
            if (is_string($code) && self::isPlatformCode($code)) {
                return true;
            }
        }

        return false;
    }

    /** True when the role currently holds any platform code (such a role is never assignable or editable over HTTP). */
    public static function roleHoldsPlatformPermission(string $roleId): bool
    {
        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.code', 'like', self::PREFIX . '%')
            ->exists();
    }

    /**
     * Restricts a permissions query to non-platform codes — for every bulk "grant all permissions" seeder path.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    public static function excludeFrom(Builder $query): Builder
    {
        return $query->where('code', 'not like', self::PREFIX . '%');
    }
}
