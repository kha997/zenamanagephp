<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TenantAuditService
{
    /**
     * Log tenant admin action
     */
    public static function logAction(string $action, array $data = []): void
    {
        $auditData = [
            'action' => $action,
            'tenant_id' => TenantContext::getTenantId(),
            'user_id' => TenantContext::getUserId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'x_request_id' => request()->header('X-Request-Id'),
            'timestamp' => now()->toISOString(),
            'data' => $data
        ];

        // Log to application log
        Log::info('Tenant admin action', $auditData);

        // Store in database for audit trail
        try {
            DB::table('tenant_audit_logs')->insert([
                'action' => $action,
                'tenant_id' => $auditData['tenant_id'],
                'user_id' => $auditData['user_id'],
                'ip_address' => $auditData['ip_address'],
                'user_agent' => $auditData['user_agent'],
                'x_request_id' => $auditData['x_request_id'],
                'data' => json_encode($data),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store tenant audit log', [
                'error' => $e->getMessage(),
                'audit_data' => $auditData
            ]);
        }
    }

    /**
     * Log tenant creation
     */
    public static function logTenantCreated(array $tenantData): void
    {
        self::logAction('tenant_created', [
            'tenant_name' => $tenantData['name'] ?? null,
            'tenant_domain' => $tenantData['domain'] ?? null,
            'tenant_plan' => $tenantData['plan'] ?? null
        ]);
    }

    /**
     * Log tenant update
     */
    public static function logTenantUpdated(string $tenantId, array $oldData, array $newData): void
    {
        self::logAction('tenant_updated', [
            'tenant_id' => $tenantId,
            'changes' => self::getChanges($oldData, $newData)
        ]);
    }

    /**
     * Log tenant deletion
     */
    public static function logTenantDeleted(string $tenantId, array $tenantData): void
    {
        self::logAction('tenant_deleted', [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenantData['name'] ?? null,
            'tenant_domain' => $tenantData['domain'] ?? null
        ]);
    }

    /**
     * Log tenant export
     */
    public static function logTenantExport(array $filters, int $recordCount): void
    {
        self::logAction('tenant_export', [
            'filters' => $filters,
            'record_count' => $recordCount,
            'export_format' => 'csv'
        ]);
    }

    /**
     * Log tenant status change
     */
    public static function logTenantStatusChange(string $tenantId, string $oldStatus, string $newStatus): void
    {
        self::logAction('tenant_status_changed', [
            'tenant_id' => $tenantId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);
    }

    /**
     * Log tenant plan change
     */
    public static function logTenantPlanChange(string $tenantId, string $oldPlan, string $newPlan): void
    {
        self::logAction('tenant_plan_changed', [
            'tenant_id' => $tenantId,
            'old_plan' => $oldPlan,
            'new_plan' => $newPlan
        ]);
    }

    /**
     * Get changes between old and new data
     */
    private static function getChanges(array $oldData, array $newData): array
    {
        $changes = [];
        
        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Get audit logs for a tenant
     */
    public static function getTenantAuditLogs(string $tenantId, int $limit = 100): array
    {
        return DB::table('tenant_audit_logs')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get audit logs for a user
     */
    public static function getUserAuditLogs(string $userId, int $limit = 100): array
    {
        return DB::table('tenant_audit_logs')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
