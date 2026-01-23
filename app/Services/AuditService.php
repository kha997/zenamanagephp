<?php

namespace App\Services;
use Illuminate\Support\Facades\Auth;


use App\Models\InteractionLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditService
{
    /**
     * Log user action
     */
    public function logAction(array $data): ?InteractionLog
    {
        if (!$this->interactionLogTableExists()) {
            Log::debug('Skipping interaction log write because the interaction_logs table is unavailable', [
                'connection' => config('database.default')
            ]);

            return null;
        }

        try {
            return InteractionLog::create([
                'id' => \Str::ulid(),
                'tenant_id' => $data['tenant_id'],
                'user_id' => $data['user_id'],
                'project_id' => $data['project_id'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'component_id' => $data['component_id'] ?? null,
                'type' => $data['type'],
                'content' => $data['content'],
                'metadata' => $data['metadata'] ?? [],
                'is_internal' => $data['is_internal'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log writing failed', [
                'error' => $e->getMessage(),
                'user_id' => $data['user_id'] ?? null,
                'tenant_id' => $data['tenant_id'] ?? null,
                'connection' => config('database.default')
            ]);
        }

        return null;
    }

    public function log(string $eventType, ?string $userId, ?string $tenantId, array $data = []): ?InteractionLog
    {
        return $this->logAction([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $eventType,
            'content' => $data['message'] ?? "Event: {$eventType}",
            'metadata' => $data,
            'is_internal' => $data['is_internal'] ?? true,
        ]);
    }

    private function interactionLogTableExists(): bool
    {
        try {
            return Schema::hasTable('interaction_logs');
        } catch (\Throwable $e) {
            Log::warning('Failed to verify interaction_logs table existence', [
                'error' => $e->getMessage(),
                'connection' => config('database.default')
            ]);

            return false;
        }
    }

    /**
     * Log CRUD operations
     */
    public function logCrudOperation(string $action, string $model, $modelId, array $data = []): void
    {
        $this->logAction([
            'tenant_id' => $data['tenant_id'] ?? Auth::user()?->tenant_id,
            'user_id' => $data['user_id'] ?? Auth::id(),
            'project_id' => $data['project_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'component_id' => $data['component_id'] ?? null,
            'type' => 'crud_operation',
            'content' => "{$action} {$model} with ID: {$modelId}",
            'metadata' => [
                'action' => $action,
                'model' => $model,
                'model_id' => $modelId,
                'data' => $data,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'is_internal' => true,
        ]);
    }

    /**
     * Log authentication events
     */
    public function logAuthEvent(string $event, array $data = []): void
    {
        $this->logAction([
            'tenant_id' => $data['tenant_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'type' => 'auth_event',
            'content' => "Authentication event: {$event}",
            'metadata' => [
                'event' => $event,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'data' => $data,
            ],
            'is_internal' => true,
        ]);
    }

    /**
     * Log file operations
     */
    public function logFileOperation(string $operation, string $filePath, array $data = []): void
    {
        $this->logAction([
            'tenant_id' => $data['tenant_id'] ?? Auth::user()?->tenant_id,
            'user_id' => $data['user_id'] ?? Auth::id(),
            'project_id' => $data['project_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'component_id' => $data['component_id'] ?? null,
            'type' => 'file_operation',
            'content' => "File {$operation}: {$filePath}",
            'metadata' => [
                'operation' => $operation,
                'file_path' => $filePath,
                'file_size' => $data['file_size'] ?? null,
                'mime_type' => $data['mime_type'] ?? null,
                'data' => $data,
            ],
            'is_internal' => false,
        ]);
    }

    /**
     * Log system events
     */
    public function logSystemEvent(string $event, array $data = []): void
    {
        $this->logAction([
            'tenant_id' => $data['tenant_id'] ?? null,
            'user_id' => null,
            'project_id' => $data['project_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'component_id' => $data['component_id'] ?? null,
            'type' => 'system_event',
            'content' => "System event: {$event}",
            'metadata' => [
                'event' => $event,
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ],
            'is_internal' => true,
        ]);
    }

    /**
     * Get audit trail for specific entity
     */
    public function getAuditTrail(string $entityType, string $entityId, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = InteractionLog::query();

        switch ($entityType) {
            case 'project':
                $query->where('project_id', $entityId);
                break;
            case 'task':
                $query->where('task_id', $entityId);
                break;
            case 'component':
                $query->where('component_id', $entityId);
                break;
            case 'user':
                $query->where('user_id', $entityId);
                break;
            default:
                throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
        }

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
        }

        if (isset($filters['is_internal'])) {
            $query->where('is_internal', $filters['is_internal']);
        }

        return $query->with(['user', 'project', 'task', 'component'])
                   ->orderBy('created_at', 'desc')
                   ->remember(300)
                   ->get();
    }

    /**
     * Get user activity summary
     */
    public function getUserActivitySummary(string $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $activities = InteractionLog::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->remember(300)
            ->get();

        $summary = [
            'total_actions' => $activities->count(),
            'actions_by_type' => $activities->groupBy('type')->map->count(),
            'actions_by_project' => $activities->whereNotNull('project_id')
                ->groupBy('project_id')
                ->map->count(),
            'daily_activity' => $activities->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            })->map->count(),
            'most_active_day' => $activities->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            })->sortDesc()->keys()->first(),
            'recent_actions' => $activities->take(10)->map(function($activity) {
                return [
                    'type' => $activity->type,
                    'content' => $activity->content,
                    'created_at' => $activity->created_at,
                    'project' => $activity->project?->name,
                ];
            }),
        ];

        return $summary;
    }

    /**
     * Get project activity summary
     */
    public function getProjectActivitySummary(string $projectId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $activities = InteractionLog::where('project_id', $projectId)
            ->where('created_at', '>=', $startDate)
            ->remember(300)
            ->get();

        $summary = [
            'total_actions' => $activities->count(),
            'actions_by_type' => $activities->groupBy('type')->map->count(),
            'actions_by_user' => $activities->whereNotNull('user_id')
                ->groupBy('user_id')
                ->map->count(),
            'daily_activity' => $activities->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            })->map->count(),
            'most_active_user' => $activities->whereNotNull('user_id')
                ->groupBy('user_id')
                ->sortDesc()
                ->keys()
                ->first(),
            'recent_actions' => $activities->take(10)->map(function($activity) {
                return [
                    'type' => $activity->type,
                    'content' => $activity->content,
                    'created_at' => $activity->created_at,
                    'user' => $activity->user?->name,
                ];
            }),
        ];

        return $summary;
    }

    /**
     * Get compliance report
     */
    public function getComplianceReport(string $tenantId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $activities = InteractionLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->remember(300)
            ->get();

        $report = [
            'period' => [
                'from' => $startDate->format('Y-m-d'),
                'to' => now()->format('Y-m-d'),
            ],
            'total_actions' => $activities->count(),
            'unique_users' => $activities->pluck('user_id')->unique()->count(),
            'actions_by_type' => $activities->groupBy('type')->map->count(),
            'actions_by_day' => $activities->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            })->map->count(),
            'top_users' => $activities->whereNotNull('user_id')
                ->groupBy('user_id')
                ->map->count()
                ->sortDesc()
                ->take(10),
            'security_events' => $activities->where('type', 'auth_event')->count(),
            'file_operations' => $activities->where('type', 'file_operation')->count(),
            'crud_operations' => $activities->where('type', 'crud_operation')->count(),
        ];

        return $report;
    }

    /**
     * Clean old audit logs
     */
    public function cleanOldLogs(int $daysToKeep = 365): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deletedCount = InteractionLog::where('created_at', '<', $cutoffDate)
            ->where('is_internal', true) // Only delete internal logs
            ->delete();

        Log::info("Cleaned {$deletedCount} old audit logs older than {$daysToKeep} days");
        
        return $deletedCount;
    }

    /**
     * Export audit trail
     */
    public function exportAuditTrail(string $entityType, string $entityId, string $format = 'json'): string
    {
        $auditTrail = $this->getAuditTrail($entityType, $entityId);
        
        switch ($format) {
            case 'json':
                return $auditTrail->toJson(JSON_PRETTY_PRINT);
            case 'csv':
                return $this->convertToCsv($auditTrail);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Convert audit trail to CSV
     */
    private function convertToCsv(\Illuminate\Database\Eloquent\Collection $auditTrail): string
    {
        $csv = "Date,User,Type,Content,Project,Task,Component,Metadata\n";
        
        foreach ($auditTrail as $log) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log->created_at->format('Y-m-d H:i:s'),
                $log->user?->name ?? 'System',
                $log->type,
                '"' . str_replace('"', '""', $log->content) . '"',
                $log->project?->name ?? '',
                $log->task?->name ?? '',
                $log->component?->name ?? '',
                '"' . str_replace('"', '""', json_encode($log->metadata)) . '"'
            );
        }
        
        return $csv;
    }

    /**
     * Get audit statistics
     */
    public function getAuditStatistics(string $tenantId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $stats = [
            'total_logs' => InteractionLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->count(),
            'user_logs' => InteractionLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->where('is_internal', false)
                ->count(),
            'system_logs' => InteractionLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->where('is_internal', true)
                ->count(),
            'logs_by_type' => InteractionLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type'),
            'logs_by_day' => InteractionLog::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->pluck('count', 'date'),
        ];

        return $stats;
    }
}
