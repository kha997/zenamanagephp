<?php declare(strict_types=1);

namespace Src\Foundation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service ghi log audit trail cho các hoạt động quan trọng
 * Theo dõi thay đổi dữ liệu và hành động của user
 */
class AuditService
{
    /**
     * Ghi log hoạt động của user
     */
    public function logActivity(
        string $userId,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $projectId = null
    ): bool {
        try {
            $auditData = [
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'project_id' => $projectId,
                'old_data' => $oldData ? json_encode($oldData) : null,
                'new_data' => $newData ? json_encode($newData) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
            
            DB::table('audit_logs')->insert($auditData);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Audit log failed', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Ghi log thay đổi dữ liệu model
     */
    public function logModelChange(
        string $userId,
        string $modelClass,
        string $modelId,
        string $action,
        array $changes = [],
        ?string $projectId = null
    ): bool {
        $entityType = class_basename($modelClass);
        
        $oldData = $changes['old'] ?? null;
        $newData = $changes['new'] ?? null;
        
        return $this->logActivity(
            $userId,
            $action,
            $entityType,
            $modelId,
            $oldData,
            $newData,
            $projectId
        );
    }
    
    /**
     * Ghi log login/logout
     */
    public function logAuthentication(string $userId, string $action, bool $success = true): bool
    {
        return $this->logActivity(
            $userId,
            $action,
            'Authentication',
            null,
            null,
            ['success' => $success, 'timestamp' => Carbon::now()->toISOString()]
        );
    }
    
    /**
     * Ghi log permission changes
     */
    public function logPermissionChange(
        string $actorId,
        string $targetUserId,
        string $action,
        array $permissions = [],
        ?string $projectId = null
    ): bool {
        return $this->logActivity(
            $actorId,
            $action,
            'Permission',
            $targetUserId,
            null,
            ['permissions' => $permissions],
            $projectId
        );
    }
    
    /**
     * Lấy audit trail cho entity cụ thể
     */
    public function getEntityAuditTrail(
        string $entityType,
        string $entityId,
        int $limit = 50
    ): array {
        try {
            $logs = DB::table('audit_logs')
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
            
            return array_map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'action' => $log->action,
                    'old_data' => $log->old_data ? json_decode($log->old_data, true) : null,
                    'new_data' => $log->new_data ? json_decode($log->new_data, true) : null,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at
                ];
            }, $logs);
        } catch (\Exception $e) {
            Log::error('Get audit trail failed', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Lấy hoạt động của user trong khoảng thời gian
     */
    public function getUserActivity(
        string $userId,
        Carbon $startDate,
        Carbon $endDate,
        int $limit = 100
    ): array {
        try {
            return DB::table('audit_logs')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Get user activity failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Thống kê hoạt động theo action
     */
    public function getActivityStats(Carbon $startDate, Carbon $endDate): array
    {
        try {
            return DB::table('audit_logs')
                ->select('action', DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Get activity stats failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Xóa audit logs cũ (cleanup)
     */
    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        try {
            $cutoffDate = Carbon::now()->subDays($daysToKeep);
            
            return DB::table('audit_logs')
                ->where('created_at', '<', $cutoffDate)
                ->delete();
        } catch (\Exception $e) {
            Log::error('Cleanup audit logs failed', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}