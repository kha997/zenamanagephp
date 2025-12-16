<?php declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AuditLogService
 * 
 * Round 235: Audit Log Framework
 * 
 * Provides centralized audit logging functionality for the system.
 * All services should use this service to record audit entries.
 */
class AuditLogService
{
    private TenancyService $tenancyService;

    public function __construct(TenancyService $tenancyService)
    {
        $this->tenancyService = $tenancyService;
    }

    /**
     * Record an audit log entry
     * 
     * @param string|null $tenantId Tenant ID (resolved from TenancyService if null)
     * @param string|null $userId User ID (resolved from Auth if null)
     * @param string $action Action name (e.g., 'role.created', 'co.approved')
     * @param string|null $entityType Entity type (e.g., 'Role', 'User', 'Contract')
     * @param string|null $entityId Entity ID (ULID)
     * @param string|null $projectId Project ID (ULID, for project-related actions)
     * @param array|null $before State before change (subset of fields, not full dump)
     * @param array|null $after State after change (subset of fields, not full dump)
     * @param string|null $ipAddress IP address (resolved from Request if null)
     * @param string|null $userAgent User agent (resolved from Request if null)
     * @return AuditLog
     */
    public function record(
        ?string $tenantId,
        ?string $userId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $projectId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AuditLog {
        try {
            // Resolve tenant ID from TenancyService if null
            if ($tenantId === null) {
                $user = Auth::user();
                if ($user) {
                    $tenant = $this->tenancyService->resolveActiveTenant($user);
                    $tenantId = $tenant?->id;
                }
            }

            // Resolve user ID from Auth if null
            if ($userId === null) {
                $user = Auth::user();
                $userId = $user?->id;
            }

            // Resolve IP address and user agent from Request if null
            if ($ipAddress === null || $userAgent === null) {
                $request = request();
                if ($request) {
                    $ipAddress = $ipAddress ?? $request->ip();
                    $userAgent = $userAgent ?? $request->userAgent();
                }
            }

            $auditLog = AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'project_id' => $projectId,
                'payload_before' => $before,
                'payload_after' => $after,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $auditLog;
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            Log::error('Failed to create audit log entry', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a dummy model to avoid breaking callers
            return new AuditLog();
        }
    }
}
