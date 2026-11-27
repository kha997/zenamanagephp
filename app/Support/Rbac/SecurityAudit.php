<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use Illuminate\Support\Facades\DB;

/**
 * OWN-2026-024 (O-17h) H2b — strict security audit rows for privileged RBAC changes (grant, revoke, Owner appointment,
 * platform operator provisioning, guarded platform operations).
 *
 * Always called INSIDE the caller's DB transaction, so a failed operation leaves neither the change nor its audit row. A failed
 * audit insert throws and rolls the whole operation back (fail closed). audit_logs.user_id must reference an app user: for a
 * server-CLI actor (no app user) the caller passes the subject user and the real actor is recorded in new_data.actor — the same
 * convention StaffAccountService uses for production:user.
 */
final class SecurityAudit
{
    /**
     * @param array<string, mixed> $newData
     * @param array<string, mixed>|null $oldData
     */
    public static function record(
        string $userId,
        ?string $tenantId,
        string $action,
        string $entityType,
        string $entityId,
        string $actor,
        array $newData,
        ?array $oldData = null,
    ): int {
        $request = app()->runningInConsole() ? null : request();

        return (int) DB::table('audit_logs')->insertGetId([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_data' => $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE),
            'new_data' => json_encode($newData + ['actor' => $actor], JSON_UNESCAPED_UNICODE),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'route' => $request?->path(),
            'method' => $request?->method(),
            'meta' => json_encode(['work_id' => 'OWN-2026-024', 'actor' => $actor]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function serverActor(): string
    {
        $osUser = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown')
            : (getenv('USER') ?: 'unknown');

        return 'server-cli:' . $osUser;
    }
}
