<?php declare(strict_types=1);

namespace Src\Common\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use App\Models\AuditLog;
use Symfony\Component\Uid\Ulid;

/**
 * Audit Service - Quản lý audit trail cho hệ thống
 */
class AuditService
{
    /**
     * Sensitive fields that should be filtered
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'api_key',
        'secret',
        'token',
        'credit_card',
        'ssn',
        'social_security_number',
        'bank_account',
        'private_key',
        'access_token',
        'refresh_token'
    ];

    /**
     * Log user action
     */
    public function logAction(
        string|Ulid $userId,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $projectId = null,
        ?string $tenantId = null
    ): AuditLog {
        // Normalize user ID to string before further processing
        $userId = (string) $userId;

        // Filter sensitive data
        $oldValues = $this->filterSensitiveData($oldValues);
        $newValues = $this->filterSensitiveData($newValues);

        // Get current user's tenant if not provided
        try {
            if (!$tenantId && Auth::check()) {
                $tenantId = Auth::user()->tenant_id;
            }
        } catch (\Exception $e) {
            // Ignore auth errors in testing context
        }

        // Get IP and User Agent if not provided
        try {
            if (!$ipAddress) {
                $ipAddress = Request::ip();
            }
            if (!$userAgent) {
                $userAgent = Request::userAgent();
            }
        } catch (\Exception $e) {
            // Ignore request errors in testing context
        }

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'project_id' => $projectId,
            'tenant_id' => $tenantId,
            'old_data' => $oldValues ? json_encode($oldValues) : null,
            'new_data' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Get audit trail for specific entity
     */
    public function getAuditTrail(string $entityType, string $entityId, ?string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = AuditLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Get audit trail for user
     */
    public function getUserAuditTrail(string $userId, ?string $tenantId = null, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        $query = AuditLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Get audit trail for project
     */
    public function getProjectAuditTrail(string $projectId, ?string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = AuditLog::where('project_id', $projectId)
            ->orderBy('created_at', 'desc');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Filter sensitive data from arrays
     */
    public function filterSensitiveData(?array $data): ?array
    {
        if (!$data) {
            return null;
        }

        $filtered = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::SENSITIVE_FIELDS)) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Cleanup old audit logs based on retention policy
     */
    public function cleanupOldLogs(int $retentionYears = 2): int
    {
        $cutoffDate = now()->subYears($retentionYears);
        
        return AuditLog::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get audit statistics
     */
    public function getAuditStatistics(?string $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = AuditLog::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $totalLogs = $query->count();
        $uniqueUsers = $query->distinct('user_id')->count('user_id');
        $uniqueEntities = $query->distinct('entity_type')->count('entity_type');

        $topActions = $query->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        $topUsers = $query->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_logs' => $totalLogs,
            'unique_users' => $uniqueUsers,
            'unique_entities' => $uniqueEntities,
            'top_actions' => $topActions,
            'top_users' => $topUsers,
        ];
    }

    /**
     * Export audit logs
     */
    public function exportAuditLogs(?string $tenantId = null, ?string $startDate = null, ?string $endDate = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = AuditLog::with(['user:id,name,email']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
