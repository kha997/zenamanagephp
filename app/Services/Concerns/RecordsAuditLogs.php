<?php declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

/**
 * RecordsAuditLogs Trait
 * 
 * Round 235: Audit Log Framework
 * 
 * Provides helper methods for services to record audit logs.
 * This trait should be used in service classes that need audit logging.
 */
trait RecordsAuditLogs
{
    /**
     * Record an audit log entry
     * 
     * @param string $action Action name (e.g., 'role.created', 'co.approved')
     * @param Model|null $entity Entity model instance (auto-detects type and ID)
     * @param array|null $before State before change
     * @param array|null $after State after change
     * @param string|null $projectId Project ID (ULID)
     * @return AuditLog
     */
    protected function audit(
        string $action,
        ?Model $entity = null,
        ?array $before = null,
        ?array $after = null,
        ?string $projectId = null
    ): AuditLog {
        $auditLogService = App::make(AuditLogService::class);

        $entityType = null;
        $entityId = null;
        $tenantId = null;

        if ($entity) {
            // Auto-detect entity type from model class name
            $entityType = class_basename($entity);
            $entityId = $entity->id ?? null;
            
            // Auto-detect tenant_id from entity if available
            if (isset($entity->tenant_id)) {
                $tenantId = $entity->tenant_id;
            }
            
            // Auto-detect project_id from entity if available and not provided
            if ($projectId === null && isset($entity->project_id)) {
                $projectId = $entity->project_id;
            }
        }

        return $auditLogService->record(
            tenantId: $tenantId,
            userId: null, // Will be resolved from Auth
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            projectId: $projectId,
            before: $before,
            after: $after,
            ipAddress: null, // Will be resolved from Request
            userAgent: null // Will be resolved from Request
        );
    }
}
