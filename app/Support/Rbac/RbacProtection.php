<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Console\Commands\ProductionRbacCommand;
use App\Models\User;
use App\Support\PlatformPermissions;
use Database\Seeders\TreasuryRolePermissionSeeder;
use Database\Seeders\V1PermissionGapSeeder;
use Database\Seeders\ZenaAdminRolePermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * OWN-2026-024 (O-17h) H2b — Owner Gate 2 D5: the fail-closed boundary for privileged RBAC changes.
 *
 * Owner authority (canonical, never a role name alone) = an ACTIVE user who holds the Owner role (`super_admin`) AND the real
 * canonical grant `admin.company.manage` (checked through User::hasPermission, i.e. revocation-aware and NOT through the legacy
 * middleware short-circuit). In production `admin.company.manage` is held only by the Owner role, so no new permission is
 * introduced for this boundary.
 *
 * Protected codes may be attached to / removed from a role, and roles carrying them may be assigned, only by an actor with Owner
 * authority who ALSO holds every protected code involved (no escalation beyond one's own grants). platform.* is never handled
 * over HTTP at all (H2a). Reserved role names (every name a seeder alias list or production:rbac targets, plus the Platform
 * Operator role) can never be created or taken by a tenant role over HTTP.
 */
final class RbacProtection
{
    public const OWNER_ROLE = 'super_admin';

    public const OWNER_AUTHORITY_CODE = 'admin.company.manage';

    public const PLATFORM_OPERATOR_ROLE = 'Platform Operator';

    /** Code prefixes whose grants change who may change authorization. */
    private const PROTECTED_PREFIXES = ['role.', 'permission.', 'system.', 'platform.'];

    /** Exact codes that confer administrative authority over people, tenants or managers. */
    private const PROTECTED_CODES = ['admin.company.manage', 'auth.bulk.manage', 'user.manage_roles', 'project.assign_manager'];

    public static function isProtectedCode(?string $code): bool
    {
        if ($code === null) {
            return false;
        }
        $code = strtolower(trim($code));
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return in_array($code, self::PROTECTED_CODES, true);
    }

    /**
     * @param iterable<mixed> $codes
     * @return list<string>
     */
    public static function protectedCodesIn(iterable $codes): array
    {
        $out = [];
        foreach ($codes as $code) {
            if (is_string($code) && self::isProtectedCode($code)) {
                $out[] = $code;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> lower-cased role names no tenant role may use */
    public static function reservedRoleNames(): array
    {
        $names = array_merge(
            ZenaAdminRolePermissionSeeder::ADMIN_ROLE_NAMES,
            TreasuryRolePermissionSeeder::OWNER_ROLE_NAMES,
            TreasuryRolePermissionSeeder::ACCOUNTANT_ROLE_NAMES,
            TreasuryRolePermissionSeeder::ENGINEER_ROLE_NAMES,
            TreasuryRolePermissionSeeder::VIEWER_ROLE_NAMES,
            V1PermissionGapSeeder::ADMIN_ROLE_NAMES,
            V1PermissionGapSeeder::MANAGER_ROLE_NAMES,
            V1PermissionGapSeeder::ACCOUNTANT_ROLE_NAMES,
            V1PermissionGapSeeder::VIEWER_ROLE_NAMES,
            array_keys(ProductionRbacCommand::ROLES),
            ['project_member', 'project member', 'project_manager', 'project manager', 'owner', self::PLATFORM_OPERATOR_ROLE],
        );

        return array_values(array_unique(array_map(static fn (string $n): string => strtolower(trim($n)), $names)));
    }

    public static function isReservedRoleName(?string $name): bool
    {
        return $name !== null && in_array(strtolower(trim($name)), self::reservedRoleNames(), true);
    }

    /** Canonical Owner authority — see class doc. Never satisfied by the role name alone. */
    public static function hasOwnerAuthority(?User $user): bool
    {
        if ($user === null || !$user->is_active) {
            return false;
        }

        return $user->roles()->where('name', self::OWNER_ROLE)->exists()
            && $user->hasPermission(self::OWNER_AUTHORITY_CODE);
    }

    /**
     * May $actor attach / remove these codes on a role (or assign a role carrying them)?
     *
     * @param iterable<mixed> $codes
     */
    public static function actorMayHandleCodes(?User $actor, iterable $codes): bool
    {
        $protected = self::protectedCodesIn($codes);
        if ($protected === []) {
            return true;
        }
        if (PlatformPermissions::containsPlatformCode($protected) || !self::hasOwnerAuthority($actor)) {
            return false;
        }
        foreach ($protected as $code) {
            if (!$actor->hasPermission($code)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> protected codes the role currently holds */
    public static function protectedCodesOfRole(string $roleId): array
    {
        $codes = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->pluck('permissions.code')
            ->map(static fn ($c): string => (string) $c)
            ->all();

        return self::protectedCodesIn($codes);
    }

    /** Owner role, Platform Operator role, or any role carrying a protected code. */
    public static function isPrivilegedRole(string $roleId): bool
    {
        $name = self::roleName($roleId);

        return $name === self::OWNER_ROLE || $name === self::PLATFORM_OPERATOR_ROLE || self::protectedCodesOfRole($roleId) !== [];
    }

    /** The authenticated app user of the current request (null in the console or for a non-User guard). */
    public static function currentActor(): ?User
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function roleName(string $roleId): ?string
    {
        $name = DB::table('roles')->where('id', $roleId)->value('name');

        return $name === null ? null : (string) $name;
    }

    /**
     * Why $actor may NOT assign or remove $roleId for $targetUserId (null when allowed). Applies to privileged roles only: the
     * Owner role, the Platform Operator role, and any role carrying a protected code. Same-tenant, no self-promotion/self-removal,
     * Owner authority with every protected code, last-active-Owner protection on removal.
     */
    public static function privilegedRoleChangeDenial(?User $actor, string $targetUserId, string $roleId, bool $removing): ?string
    {
        // No self-promotion through tenant role management, whatever the role (Gate 2: "Deny self-promotion").
        if (!$removing && $actor !== null && (string) $actor->id === $targetUserId) {
            return 'self_assignment_denied';
        }
        $name = self::roleName($roleId);
        $protected = self::protectedCodesOfRole($roleId);
        $isOwnerRole = $name === self::OWNER_ROLE;
        if (!$isOwnerRole && $name !== self::PLATFORM_OPERATOR_ROLE && $protected === []) {
            return null; // not privileged
        }
        if ($name === self::PLATFORM_OPERATOR_ROLE || PlatformPermissions::containsPlatformCode($protected)) {
            return 'platform_role_server_only';
        }
        if (!self::hasOwnerAuthority($actor)) {
            return 'owner_authority_required';
        }
        /** @var User $actor */
        if ((string) $actor->id === $targetUserId) {
            return $removing ? 'self_removal_denied' : 'self_promotion_denied';
        }
        $target = User::query()->whereKey($targetUserId)->first(['id', 'tenant_id', 'is_active']);
        if ($target === null || (string) $target->tenant_id !== (string) $actor->tenant_id) {
            return 'cross_tenant_denied';
        }
        if (!self::actorMayHandleCodes($actor, $protected)) {
            return 'protected_codes_not_held';
        }
        if ($removing && $isOwnerRole && $target->is_active && self::otherActiveOwners((string) $target->tenant_id, $targetUserId) === 0) {
            return 'last_active_owner';
        }

        return null;
    }

    public static function otherActiveOwners(string $tenantId, string $exceptUserId): int
    {
        return DB::table('users')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.name', self::OWNER_ROLE)
            ->where('users.tenant_id', $tenantId)
            ->where('users.is_active', true)
            ->where('users.id', '!=', $exceptUserId)
            ->distinct()
            ->count('users.id');
    }
}
