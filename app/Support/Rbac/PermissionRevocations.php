<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OWN-2026-024 (O-17h) H2b — Owner Gate 2 D4: durable, deliberate permission revocations.
 *
 * Grant kinds (Gate 1.2):
 *  - seed-managed baseline grant  -> role_permissions.grant_source = 'seed'  (written only through RbacSeedGrants)
 *  - explicitly approved manual grant -> grant_source = 'manual'            (HTTP role sync by an authorised actor, or regrant())
 *  - platform-only grant          -> grant_source = 'platform'               (PlatformOperators, server CLI only)
 *  - deliberately revoked grant   -> an ACTIVE row in role_permission_revocations (superseded_at NULL)
 *  - NULL grant_source            -> granted before H2b (origin unknown; treated as seed-or-manual, never as platform)
 *
 * Enforcement is three-layered: (1) revoke() detaches the pivot row; (2) every seeder grant path filters active revocations
 * (RbacSeedGrants), so a re-seed never restores one; (3) authorization time — User::hasPermission() ignores any (role, code)
 * pair with an active revocation, so even a pivot row re-added by an unknown path does not authorize. A revocation is cleared only
 * by regrant() (or an authorised HTTP sync that re-adds the code), which supersedes the record in the same transaction.
 */
final class PermissionRevocations
{
    public const AUDIT_REVOKED = 'rbac.permission.revoked';

    public const AUDIT_REGRANTED = 'rbac.permission.regranted';

    /** @return list<string> permission ids with an active revocation on this role */
    public static function revokedPermissionIds(string $roleId): array
    {
        if (!RbacSchema::hasRevocations()) {
            return [];
        }

        return DB::table('role_permission_revocations')
            ->where('role_id', $roleId)
            ->whereNull('superseded_at')
            ->pluck('permission_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    public static function isRevoked(string $roleId, string $permissionCode): bool
    {
        if (!RbacSchema::hasRevocations()) {
            return false; // pre-migration: no revocation can exist yet; revoke()/regrant() themselves refuse (RbacSchema::assertMigrated)
        }

        return DB::table('role_permission_revocations')
            ->where('active_key', self::activeKey($roleId, $permissionCode))
            ->exists();
    }

    /**
     * Revoke $permissionCode from $roleId durably. Atomic: revocation row + pivot detach + audit row, or nothing.
     *
     * @param string $actor e.g. "user:<id>" or SecurityAudit::serverActor()
     * @param string $auditUserId app user recorded in audit_logs.user_id (the authorising user)
     */
    public static function revoke(string $roleId, string $permissionCode, string $actor, ?string $actorUserId, string $auditUserId, string $reason, ?string $approvalReference): string
    {
        if (trim($reason) === '') {
            throw new RuntimeException('A revocation needs a reason.');
        }
        RbacSchema::assertMigrated();

        return DB::transaction(function () use ($roleId, $permissionCode, $actor, $actorUserId, $auditUserId, $reason, $approvalReference): string {
            $role = DB::table('roles')->where('id', $roleId)->lockForUpdate()->first(['id', 'tenant_id', 'name']);
            $permission = DB::table('permissions')->where('code', $permissionCode)->first(['id', 'code']);
            if ($role === null || $permission === null) {
                throw new RuntimeException('Unknown role or permission.');
            }
            if (self::isRevoked($roleId, $permissionCode)) {
                throw new RuntimeException("{$permissionCode} is already revoked for this role.");
            }
            // Lock-out protection (like the last active Owner): the canonical Owner authority itself cannot be durably revoked
            // from the Owner role — otherwise no one could ever approve its re-grant, and seeding would not restore it.
            if ((string) $role->name === RbacProtection::OWNER_ROLE && strtolower($permissionCode) === RbacProtection::OWNER_AUTHORITY_CODE) {
                throw new RuntimeException('The Owner authority grant cannot be revoked from the Owner role (lock-out protection).');
            }

            $wasGranted = DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permission->id)->exists();
            $id = (string) Str::ulid();
            $auditId = SecurityAudit::record($auditUserId, $role->tenant_id === null ? null : (string) $role->tenant_id, self::AUDIT_REVOKED, 'role', $roleId, $actor, [
                'role' => (string) $role->name,
                'permission' => $permissionCode,
                'was_granted' => $wasGranted,
                'reason' => $reason,
                'approval_reference' => $approvalReference,
                'revocation_id' => $id,
            ]);
            // The UNIQUE active_key makes a concurrent duplicate fail here and roll everything back.
            DB::table('role_permission_revocations')->insert([
                'id' => $id,
                'role_id' => $roleId,
                'permission_id' => (string) $permission->id,
                'permission_code' => $permissionCode,
                'tenant_id' => $role->tenant_id,
                'revoked_by_user_id' => $actorUserId,
                'revoked_by_actor' => $actor,
                'reason' => $reason,
                'approval_reference' => $approvalReference,
                'audit_log_id' => $auditId,
                'revoked_at' => now(),
                'active_key' => self::activeKey($roleId, $permissionCode),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permission->id)->delete();

            return $id;
        });
    }

    /**
     * Explicit re-grant: supersedes the active revocation and attaches the grant as 'manual', atomically with its audit row.
     * Authority is the CALLER's responsibility (server CLI with an approving Owner, or an authorised HTTP sync).
     */
    public static function regrant(string $roleId, string $permissionCode, string $actor, string $auditUserId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuntimeException('A re-grant needs a reason.');
        }
        RbacSchema::assertMigrated();

        DB::transaction(function () use ($roleId, $permissionCode, $actor, $auditUserId, $reason): void {
            $role = DB::table('roles')->where('id', $roleId)->lockForUpdate()->first(['id', 'tenant_id', 'name']);
            $permission = DB::table('permissions')->where('code', $permissionCode)->first(['id']);
            if ($role === null || $permission === null) {
                throw new RuntimeException('Unknown role or permission.');
            }
            self::supersedeInsideTransaction((string) $role->id, $role->tenant_id === null ? null : (string) $role->tenant_id, (string) $role->name, $permissionCode, $actor, $auditUserId, $reason);
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => (string) $permission->id,
                'created_at' => now(),
                'updated_at' => now(),
            ] + RbacSchema::grantSourceAttributes('manual'));
        });
    }

    /** Must be called inside an open transaction. Returns true when an active revocation was superseded. */
    public static function supersedeInsideTransaction(string $roleId, ?string $tenantId, string $roleName, string $permissionCode, string $actor, string $auditUserId, string $reason): bool
    {
        if (!RbacSchema::hasRevocations()) {
            return false; // pre-migration: nothing to supersede
        }
        $row = DB::table('role_permission_revocations')->where('active_key', self::activeKey($roleId, $permissionCode))->lockForUpdate()->first(['id']);
        if ($row === null) {
            return false;
        }
        $auditId = SecurityAudit::record($auditUserId, $tenantId, self::AUDIT_REGRANTED, 'role', $roleId, $actor, [
            'role' => $roleName,
            'permission' => $permissionCode,
            'reason' => $reason,
            'superseded_revocation_id' => (string) $row->id,
        ]);
        DB::table('role_permission_revocations')->where('id', $row->id)->update([
            'superseded_at' => now(),
            'superseded_by_actor' => $actor,
            'superseded_reason' => $reason,
            'superseded_audit_log_id' => $auditId,
            'active_key' => null,
            'updated_at' => now(),
        ]);

        return true;
    }

    public static function activeKey(string $roleId, string $permissionCode): string
    {
        return $roleId . '|' . strtolower(trim($permissionCode));
    }

    public static function actorLabel(?User $user): string
    {
        return $user === null ? SecurityAudit::serverActor() : 'user:' . $user->id;
    }
}
