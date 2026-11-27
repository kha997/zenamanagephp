<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PlatformPermissions;
use App\Support\Rbac\PermissionRevocations;
use App\Support\Rbac\RbacProtection;
use App\Support\Rbac\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OWN-2026-024 (O-17h) H2b — Owner Gate 2 D4: durable revocation and explicit re-grant from the server console.
 *
 * Authority: shell access to the server AND an approving app user with canonical Owner authority (`--approved-by`, recorded as
 * the authorising actor and as audit_logs.user_id). Never reachable over HTTP. platform.* codes are refused here: platform
 * authority is managed only by platform:operator. Nothing is revoked automatically; this command does nothing unless invoked.
 */
class RbacPermissionRevocationCommand extends Command
{
    protected $signature = 'rbac:permission
        {action : revoke | regrant | list}
        {--role= : role name (global role) or role id}
        {--code= : permission code}
        {--reason= : why (required for revoke/regrant)}
        {--approval= : approval reference (ticket / Owner decision)}
        {--approved-by= : e-mail of the approving Owner (canonical Owner authority required)}
        {--yes : do not ask for confirmation}';

    protected $description = 'Durably revoke or explicitly re-grant a role permission (OWN-2026-024 D4) — server console only';

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        if ($action === 'list') {
            $rows = DB::table('role_permission_revocations')->whereNull('superseded_at')->orderBy('revoked_at')->get(['role_id', 'permission_code', 'revoked_by_actor', 'revoked_at', 'reason']);
            $this->table(['Role', 'Permission', 'Revoked by', 'At', 'Reason'], $rows->map(fn ($r): array => [RbacProtection::roleName((string) $r->role_id) ?? $r->role_id, $r->permission_code, $r->revoked_by_actor, $r->revoked_at, $r->reason])->all());

            return self::SUCCESS;
        }
        if (!in_array($action, ['revoke', 'regrant'], true)) {
            $this->error('Action must be revoke, regrant or list.');

            return self::INVALID;
        }

        $code = trim((string) $this->option('code'));
        $reason = trim((string) $this->option('reason'));
        $roleRef = trim((string) $this->option('role'));
        if ($code === '' || $reason === '' || $roleRef === '') {
            $this->error('--role, --code and --reason are required.');

            return self::INVALID;
        }
        if (PlatformPermissions::isPlatformCode($code)) {
            $this->error('platform.* permissions are managed only by platform:operator.');

            return self::INVALID;
        }

        /** @var User|null $approver */
        $approver = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $this->option('approved-by')))])->first();
        if (!RbacProtection::hasOwnerAuthority($approver)) {
            $this->error('--approved-by must name an active Owner holding the canonical owner authority.');

            return self::FAILURE;
        }
        /** @var User $approver */
        $role = DB::table('roles')->where('id', $roleRef)->first(['id', 'tenant_id', 'name'])
            ?? DB::table('roles')->where('name', $roleRef)->whereNull('tenant_id')->first(['id', 'tenant_id', 'name']);
        if ($role === null) {
            $this->error('Unknown role.');

            return self::FAILURE;
        }
        if ($role->tenant_id !== null && (string) $role->tenant_id !== (string) $approver->tenant_id) {
            $this->error('The approving Owner belongs to another company than this role.');

            return self::FAILURE;
        }
        if (!$this->option('yes') && !$this->confirm("{$action} {$code} on role {$role->name}?")) {
            return self::FAILURE;
        }

        $actor = SecurityAudit::serverActor() . ';approved-by:' . $approver->id;

        try {
            if ($action === 'revoke') {
                PermissionRevocations::revoke((string) $role->id, $code, $actor, null, (string) $approver->id, $reason, $this->option('approval') ?: null);
            } else {
                PermissionRevocations::regrant((string) $role->id, $code, $actor, (string) $approver->id, $reason);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Done: {$action} {$code} on {$role->name}.");

        return self::SUCCESS;
    }
}
