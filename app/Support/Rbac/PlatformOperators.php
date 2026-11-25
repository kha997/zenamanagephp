<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Models\User;
use App\Support\PlatformPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OWN-2026-024 (O-17h) H2b — Owner Gate 2 D6: server-only Platform Operator provisioning.
 *
 * Platform authority = (a) the global "Platform Operator" role holding exactly the platform.* codes AND (b) an ACTIVE ledger row
 * in platform_operators. Both are written only here, only from the console (PHP_SAPI cli), atomically with an audit row.
 * User::hasPermission() requires (b) for every platform.* code, so neither a role grant nor a role name over HTTP can ever
 * establish platform authority. No operator exists by default; migrations and seeders never create one.
 */
final class PlatformOperators
{
    public const AUDIT_GRANTED = 'platform.operator.granted';

    public const AUDIT_REVOKED = 'platform.operator.revoked';

    public static function isActive(string $userId): bool
    {
        if (!RbacSchema::hasPlatformOperators()) {
            return false; // fail closed before the H2b migration
        }

        return DB::table('platform_operators')->where('active_key', $userId)->exists();
    }

    public static function grant(User $user, string $reason, string $approvalReference): void
    {
        self::assertServerConsole();
        if (trim($reason) === '' || trim($approvalReference) === '') {
            throw new RuntimeException('A reason and an approval reference are required.');
        }
        RbacSchema::assertMigrated();
        if (!$user->is_active) {
            throw new RuntimeException('Only an active user can be provisioned.');
        }
        $actor = SecurityAudit::serverActor();

        DB::transaction(function () use ($user, $reason, $approvalReference, $actor): void {
            if (self::isActive((string) $user->id)) {
                throw new RuntimeException('This user is already an active Platform Operator.');
            }
            $roleId = self::ensureRole();
            $auditId = SecurityAudit::record((string) $user->id, self::tenantOf($user), self::AUDIT_GRANTED, 'user', (string) $user->id, $actor, [
                'role' => RbacProtection::PLATFORM_OPERATOR_ROLE,
                'permissions' => array_keys(PlatformPermissions::CATALOGUE),
                'reason' => $reason,
                'approval_reference' => $approvalReference,
            ]);
            DB::table('platform_operators')->insert([
                'id' => (string) Str::ulid(),
                'user_id' => (string) $user->id,
                'granted_by_actor' => $actor,
                'reason' => $reason,
                'approval_reference' => $approvalReference,
                'grant_audit_log_id' => $auditId,
                'granted_at' => now(),
                'active_key' => (string) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_roles')->insertOrIgnore(['user_id' => (string) $user->id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public static function revoke(User $user, string $reason): void
    {
        self::assertServerConsole();
        if (trim($reason) === '') {
            throw new RuntimeException('A reason is required.');
        }
        RbacSchema::assertMigrated();
        $actor = SecurityAudit::serverActor();

        DB::transaction(function () use ($user, $reason, $actor): void {
            $row = DB::table('platform_operators')->where('active_key', (string) $user->id)->lockForUpdate()->first(['id']);
            if ($row === null) {
                throw new RuntimeException('This user is not an active Platform Operator.');
            }
            $auditId = SecurityAudit::record((string) $user->id, self::tenantOf($user), self::AUDIT_REVOKED, 'user', (string) $user->id, $actor, [
                'role' => RbacProtection::PLATFORM_OPERATOR_ROLE,
                'reason' => $reason,
                'operator_record_id' => (string) $row->id,
            ]);
            DB::table('platform_operators')->where('id', $row->id)->update([
                'revoked_at' => now(),
                'revoked_by_actor' => $actor,
                'revoke_reason' => $reason,
                'revoke_audit_log_id' => $auditId,
                'active_key' => null,
                'updated_at' => now(),
            ]);
            $roleId = DB::table('roles')->where('name', RbacProtection::PLATFORM_OPERATOR_ROLE)->whereNull('tenant_id')->value('id');
            if ($roleId !== null) {
                DB::table('user_roles')->where('user_id', (string) $user->id)->where('role_id', (string) $roleId)->delete();
                DB::table('system_user_roles')->where('user_id', (string) $user->id)->where('role_id', (string) $roleId)->delete();
            }
        });
    }

    /** Creates (once) the global Platform Operator role holding exactly the platform.* codes. Console only. */
    private static function ensureRole(): string
    {
        $role = DB::table('roles')->where('name', RbacProtection::PLATFORM_OPERATOR_ROLE)->first(['id', 'tenant_id', 'scope']);
        if ($role !== null && ($role->tenant_id !== null || $role->scope !== 'system')) {
            throw new RuntimeException('A non-global role already uses the reserved Platform Operator name; refusing.');
        }
        $roleId = $role === null ? (string) Str::ulid() : (string) $role->id;
        if ($role === null) {
            DB::table('roles')->insert([
                'id' => $roleId,
                'name' => RbacProtection::PLATFORM_OPERATOR_ROLE,
                'scope' => 'system',
                'allow_override' => false,
                'is_active' => true,
                'description' => 'Server-provisioned platform operator (OWN-2026-024). Never assignable over HTTP.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $platformIds = DB::table('permissions')->where('code', 'like', PlatformPermissions::PREFIX . '%')->pluck('id')->all();
        if (count($platformIds) !== count(PlatformPermissions::CATALOGUE)) {
            throw new RuntimeException('The platform permission catalogue is incomplete; run production:rbac first.');
        }
        // Exactly the platform codes, nothing else.
        DB::table('role_permissions')->where('role_id', $roleId)->whereNotIn('permission_id', $platformIds)->delete();
        foreach ($platformIds as $pid) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => (string) $pid, 'created_at' => now(), 'updated_at' => now()] + RbacSchema::grantSourceAttributes('platform'));
        }

        return $roleId;
    }

    private static function tenantOf(User $user): ?string
    {
        $tenant = $user->getAttribute('tenant_id');

        return is_string($tenant) && $tenant !== '' ? $tenant : null;
    }

    private static function assertServerConsole(): void
    {
        if (PHP_SAPI !== 'cli' || !app()->runningInConsole()) {
            throw new RuntimeException('Platform Operators are provisioned from the server console only.');
        }
    }
}
