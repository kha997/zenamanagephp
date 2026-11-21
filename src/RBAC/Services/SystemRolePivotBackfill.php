<?php declare(strict_types=1);

namespace Src\RBAC\Services;

use Illuminate\Support\Facades\DB;

/**
 * UAT-RECOVERY-01 S2 — reconcile `system_user_roles` (assignment ledger, GAP-042) into `user_roles`
 * (the pivot read by User::hasPermission → rbac: gate).
 *
 * Safety model (never guess a privileged role):
 *  - plan() is READ-ONLY and classifies every active ledger row.
 *  - apply() inserts ONLY rows classified INSERT; it never updates or deletes anything.
 *  - Any row that is ambiguous, dangling, non-system, tenant-bound, or maps to a privileged role is
 *    reported as a CONFLICT and skipped (fail closed). Privileged roles need an explicit allow-list.
 *  - apply() returns a manifest of the exact rows inserted; rollback() deletes exactly those rows.
 *  - Idempotent: a second plan()/apply() finds the rows already present (DUPLICATE) and inserts nothing.
 *
 * This class is NOT invoked automatically (no migration, no seeder). Production execution is an
 * Owner-gated operation (see docs/audits/UAT-RECOVERY-01/).
 */
class SystemRolePivotBackfill
{
    public const INSERT = 'INSERT';
    public const DUPLICATE = 'DUPLICATE';
    public const CONFLICT = 'CONFLICT';

    /** Roles considered privileged: they hold role.assign or are named like the admin roles. */
    private const PRIVILEGED_PERMISSION = 'role.assign';

    /**
     * @param list<string> $allowPrivilegedRoleIds explicit Owner approval, by role id, for privileged roles
     * @return array{rows: list<array{user_id:string,role_id:string,action:string,reason:?string}>, summary: array<string,int>}
     */
    public function plan(array $allowPrivilegedRoleIds = []): array
    {
        $rows = [];
        $ledger = DB::table('system_user_roles')->whereNull('deleted_at')->orderBy('user_id')->orderBy('role_id')->get();
        $seen = [];

        foreach ($ledger as $row) {
            $key = $row->user_id . '|' . $row->role_id;
            if (isset($seen[$key])) {
                $rows[] = $this->entry($row, self::DUPLICATE, 'duplicate ledger row');
                continue;
            }
            $seen[$key] = true;

            [$action, $reason] = $this->classify($row, $allowPrivilegedRoleIds);
            $rows[] = $this->entry($row, $action, $reason);
        }

        $summary = [self::INSERT => 0, self::DUPLICATE => 0, self::CONFLICT => 0];
        foreach ($rows as $r) {
            $summary[$r['action']]++;
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @param list<string> $allowPrivilegedRoleIds
     * @return array{inserted: list<array{user_id:string,role_id:string}>, plan: array}
     */
    public function apply(array $allowPrivilegedRoleIds = []): array
    {
        $plan = $this->plan($allowPrivilegedRoleIds);
        $inserted = [];

        DB::transaction(function () use ($plan, &$inserted): void {
            foreach ($plan['rows'] as $r) {
                if ($r['action'] !== self::INSERT) {
                    continue;
                }
                $count = DB::table('user_roles')->insertOrIgnore([
                    'user_id' => $r['user_id'],
                    'role_id' => $r['role_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($count > 0) {
                    $inserted[] = ['user_id' => $r['user_id'], 'role_id' => $r['role_id']];
                } elseif (!DB::table('user_roles')->where('user_id', $r['user_id'])->where('role_id', $r['role_id'])->exists()) {
                    // INSERT IGNORE swallowed an error (e.g. FK) — never report success for a row that is absent.
                    throw new \RuntimeException("backfill row not persisted for user {$r['user_id']} role {$r['role_id']}");
                }
            }
        });

        return ['inserted' => $inserted, 'plan' => $plan];
    }

    /**
     * Deletes exactly the rows listed in a manifest produced by apply(). Rows not listed are never touched.
     *
     * @param list<array{user_id:string,role_id:string}> $inserted
     */
    public function rollback(array $inserted): int
    {
        $deleted = 0;
        DB::transaction(function () use ($inserted, &$deleted): void {
            foreach ($inserted as $r) {
                $deleted += DB::table('user_roles')
                    ->where('user_id', $r['user_id'])
                    ->where('role_id', $r['role_id'])
                    ->delete();
            }
        });

        return $deleted;
    }

    /** @return array{0:string,1:?string} */
    private function classify(object $row, array $allowPrivilegedRoleIds): array
    {
        $user = DB::table('users')->where('id', $row->user_id)->first(['id', 'tenant_id']);
        if ($user === null) {
            return [self::CONFLICT, 'USER_MISSING'];
        }
        if ($user->tenant_id === null || $user->tenant_id === '') {
            return [self::CONFLICT, 'USER_TENANT_UNKNOWN'];
        }

        $role = DB::table('roles')->where('id', $row->role_id)->first(['id', 'scope', 'tenant_id', 'is_active', 'name']);
        if ($role === null) {
            return [self::CONFLICT, 'ROLE_MISSING'];
        }
        if ($role->scope !== 'system' || $role->tenant_id !== null) {
            return [self::CONFLICT, 'ROLE_NOT_GLOBAL_SYSTEM'];
        }
        if ((int) $role->is_active !== 1) {
            return [self::CONFLICT, 'ROLE_INACTIVE'];
        }

        if ($this->isPrivileged($role) && !in_array($role->id, $allowPrivilegedRoleIds, true)) {
            return [self::CONFLICT, 'PRIVILEGED_ROLE_REQUIRES_EXPLICIT_APPROVAL'];
        }

        $exists = DB::table('user_roles')->where('user_id', $row->user_id)->where('role_id', $row->role_id)->exists();

        return $exists ? [self::DUPLICATE, 'already in user_roles'] : [self::INSERT, null];
    }

    private function isPrivileged(object $role): bool
    {
        if (strtolower((string) $role->name) === 'system admin') {
            return true;
        }

        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $role->id)
            ->where(function ($q): void {
                $q->where('permissions.name', self::PRIVILEGED_PERMISSION)->orWhere('permissions.code', self::PRIVILEGED_PERMISSION);
            })
            ->exists();
    }

    /** @return array{user_id:string,role_id:string,action:string,reason:?string} */
    private function entry(object $row, string $action, ?string $reason): array
    {
        return ['user_id' => (string) $row->user_id, 'role_id' => (string) $row->role_id, 'action' => $action, 'reason' => $reason];
    }
}
