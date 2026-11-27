<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Rbac\PermissionRevocations;
use App\Support\Rbac\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * OWN-2026-024 (O-17h) H2b — Owner Gate 4 safeguards for destructive host maintenance and the full database export.
 *
 * Runs AFTER the route's rbac:platform.* permission gate (authorization is not decided here; until H3 the legacy super_admin
 * short-circuit still passes that gate, which this guard does not change). Per request:
 *  1. kill switch (config platform_operations.destructive_operations_enabled, default OFF) -> 503, nothing runs;
 *  2. explicit confirmation: `confirm_operation` must equal this route's operation id -> 428 otherwise, nothing runs;
 *  3. an audit row is written BEFORE the operation (if it cannot be written, nothing runs) and another one after it with the
 *     outcome status — so no destructive operation ever runs unaudited;
 *  4. a file download response is never returned (full backups stay on the host): replaced by a 500 refusal.
 */
final class GuardedPlatformOperation
{
    public function handle(Request $request, Closure $next, string $operation): Response
    {
        if (!(bool) config('platform_operations.destructive_operations_enabled', false)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PLATFORM_OPERATION_DISABLED', 'message' => 'Destructive platform operations are disabled (kill switch).'],
            ], 503);
        }
        if ((string) $request->input('confirm_operation') !== $operation) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PLATFORM_OPERATION_CONFIRMATION_REQUIRED', 'message' => "Send confirm_operation={$operation} to run this operation."],
            ], 428);
        }

        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['success' => false, 'error' => ['code' => 'E401', 'message' => 'Unauthenticated.']], 401);
        }
        $actor = PermissionRevocations::actorLabel($user);
        $rawTenant = $user->getAttribute('tenant_id');
        $tenantId = is_string($rawTenant) && $rawTenant !== '' ? $rawTenant : null;

        try {
            SecurityAudit::record((string) $user->id, $tenantId, 'platform.operation.requested', 'platform_operation', $operation, $actor, ['operation' => $operation]);
        } catch (Throwable) {
            return response()->json(['success' => false, 'error' => ['code' => 'PLATFORM_OPERATION_AUDIT_FAILED', 'message' => 'Audit unavailable; operation not run.']], 500);
        }

        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            $response = response()->json(['success' => false, 'error' => ['code' => 'PLATFORM_DOWNLOAD_REFUSED', 'message' => 'Platform operations never return files over HTTP.']], 500);
        }
        SecurityAudit::record((string) $user->id, $tenantId, 'platform.operation.completed', 'platform_operation', $operation, $actor, [
            'operation' => $operation,
            'status_code' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
