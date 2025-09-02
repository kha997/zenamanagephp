<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Component;
use Src\InteractionLogs\Models\InteractionLog;
use Src\DocumentManagement\Models\Document;
use Src\ChangeRequest\Models\ChangeRequest;

/**
 * Service tối ưu hóa các truy vấn database
 * Giải quyết N+1 queries và cải thiện performance
 */
class QueryOptimizationService
{
    /**
     * Lấy projects với eager loading tối ưu
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOptimizedProjects(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $cacheKey = 'projects_' . md5(serialize($filters) . $perPage);
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $perPage) {
            $query = Project::with([
                'tenant:id,name',
                'rootComponents' => function ($query) {
                    $query->select('id', 'project_id', 'name', 'progress_percent', 'actual_cost')
                          ->where('parent_component_id', null);
                },
                'tasks' => function ($query) {
                    $query->select('id', 'project_id', 'name', 'status', 'progress_percent')
                          ->where('is_hidden', false)
                          ->orderBy('priority', 'desc');
                }
            ]);

            // Apply filters efficiently
            if (!empty($filters['tenant_id'])) {
                $query->where('tenant_id', $filters['tenant_id']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['visibility'])) {
                $query->where('visibility', $filters['visibility']);
            }

            if (!empty($filters['progress_min'])) {
                $query->where('progress', '>=', $filters['progress_min']);
            }

            if (!empty($filters['progress_max'])) {
                $query->where('progress', '<=', $filters['progress_max']);
            }

            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'LIKE', "%{$filters['search']}%")
                      ->orWhere('description', 'LIKE', "%{$filters['search']}%");
                });
            }

            return $query->orderBy('updated_at', 'desc')
                        ->paginate($perPage);
        });
    }

    /**
     * Lấy tasks với relationships tối ưu
     *
     * @param string $projectId
     * @param array $filters
     * @return Collection
     */
    public function getOptimizedTasks(string $projectId, array $filters = []): Collection
    {
        $cacheKey = "tasks_{$projectId}_" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 180, function () use ($projectId, $filters) {
            $query = Task::with([
                'project:id,name,status',
                'component:id,name,progress_percent',
                'assignments' => function ($query) {
                    $query->with('user:id,name,email')
                          ->select('id', 'task_id', 'user_id', 'role', 'split_percentage');
                }
            ])
            ->where('project_id', $projectId)
            ->where('is_hidden', false);

            // Apply filters
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }

            if (!empty($filters['component_id'])) {
                $query->where('component_id', $filters['component_id']);
            }

            return $query->orderBy('priority', 'desc')
                        ->orderBy('start_date', 'asc')
                        ->get();
        });
    }

    /**
     * Lấy interaction logs với pagination tối ưu
     *
     * @param string $projectId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOptimizedInteractionLogs(string $projectId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = InteractionLog::with([
            'project:id,name',
            'linkedTask:id,name,status',
            'creator:id,name,email'
        ])
        ->where('project_id', $projectId);

        // Apply filters
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (!empty($filters['client_approved'])) {
            $query->where('client_approved', $filters['client_approved']);
        }

        if (!empty($filters['tag_path'])) {
            $query->where('tag_path', 'LIKE', "%{$filters['tag_path']}%");
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Lấy dashboard statistics với single query
     *
     * @param string $tenantId
     * @return array
     */
    public function getDashboardStats(string $tenantId): array
    {
        $cacheKey = "dashboard_stats_{$tenantId}";
        
        return Cache::remember($cacheKey, 600, function () use ($tenantId) {
            // Single query để lấy project statistics
            $projectStats = DB::table('projects')
                ->where('tenant_id', $tenantId)
                ->selectRaw('
                    COUNT(*) as total_projects,
                    SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_projects,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_projects,
                    AVG(progress) as avg_progress,
                    SUM(planned_cost) as total_planned_cost,
                    SUM(actual_cost) as total_actual_cost
                ')
                ->first();

            // Single query để lấy task statistics
            $taskStats = DB::table('tasks')
                ->join('projects', 'tasks.project_id', '=', 'projects.id')
                ->where('projects.tenant_id', $tenantId)
                ->where('tasks.is_hidden', false)
                ->selectRaw('
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN tasks.status = "completed" THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN tasks.status = "in_progress" THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN tasks.status = "pending" THEN 1 ELSE 0 END) as pending_tasks,
                    AVG(tasks.progress_percent) as avg_task_progress
                ')
                ->first();

            // Single query để lấy recent activities
            $recentActivities = DB::table('interaction_logs')
                ->join('projects', 'interaction_logs.project_id', '=', 'projects.id')
                ->join('users', 'interaction_logs.created_by', '=', 'users.id')
                ->where('projects.tenant_id', $tenantId)
                ->select(
                    'interaction_logs.id',
                    'interaction_logs.type',
                    'interaction_logs.description',
                    'projects.name as project_name',
                    'users.name as user_name',
                    'interaction_logs.created_at'
                )
                ->orderBy('interaction_logs.created_at', 'desc')
                ->limit(10)
                ->get();

            return [
                'projects' => $projectStats,
                'tasks' => $taskStats,
                'recent_activities' => $recentActivities
            ];
        });
    }

    /**
     * Bulk update progress cho components
     *
     * @param array $componentUpdates
     * @return bool
     */
    public function bulkUpdateComponentProgress(array $componentUpdates): bool
    {
        try {
            DB::beginTransaction();

            foreach ($componentUpdates as $update) {
                DB::table('components')
                    ->where('id', $update['id'])
                    ->update([
                        'progress_percent' => $update['progress_percent'],
                        'actual_cost' => $update['actual_cost'],
                        'updated_at' => now()
                    ]);
            }

            DB::commit();
            
            // Clear related caches
            $this->clearProjectCaches();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Clear project-related caches
     *
     * @return void
     */
    public function clearProjectCaches(): void
    {
        // Xóa cache theo pattern vì file driver không hỗ trợ tagging
        $patterns = [
            'projects_*',
            'tasks_*', 
            'dashboard_stats_*',
            'components_*'
        ];
        
        foreach ($patterns as $pattern) {
            $this->clearCacheByPattern($pattern);
        }
    }
    
    /**
     * Clear cache by pattern
     *
     * @param string $pattern
     * @return void
     */
    private function clearCacheByPattern(string $pattern): void
    {
        try {
            // Với file cache, chúng ta cần xóa toàn bộ cache
            // hoặc implement logic phức tạp hơn để scan files
            Cache::flush();
        } catch (\Exception $e) {
            // Log error nhưng không throw để không làm gián đoạn process
            \Log::warning('Failed to clear cache pattern: ' . $pattern, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Analyze slow queries
     *
     * @return array
     */
    public function analyzeSlowQueries(): array
    {
        // Enable slow query log temporarily
        DB::statement('SET GLOBAL slow_query_log = "ON"');
        DB::statement('SET GLOBAL long_query_time = 1');
        
        // Get slow query statistics
        $slowQueries = DB::select('SHOW STATUS LIKE "Slow_queries"');
        
        return [
            'slow_queries_count' => $slowQueries[0]->Value ?? 0,
            'recommendations' => [
                'Add missing indexes',
                'Optimize WHERE clauses',
                'Use LIMIT for large datasets',
                'Consider query caching'
            ]
        ];
    }
}