<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Service tối ưu hóa các truy vấn database phức tạp
 * Cung cấp các method để optimize N+1 queries, eager loading, và chunking
 */
class QueryOptimizationService
{
    /**
     * Optimize project queries với eager loading
     * 
     * @param Builder $query
     * @param array $relations
     * @return Builder
     */
    public function optimizeProjectQuery(Builder $query, array $relations = []): Builder
    {
        $defaultRelations = [
            'components' => function ($q) {
                $q->select(['id', 'project_id', 'parent_component_id', 'name', 'progress_percent'])
                  ->where('is_active', true);
            },
            'tasks' => function ($q) {
                $q->select(['id', 'project_id', 'component_id', 'name', 'status', 'start_date', 'end_date'])
                  ->where('is_hidden', false);
            },
            'tenant:id,name'
        ];

        $relations = array_merge($defaultRelations, $relations);
        
        return $query->with($relations)
                    ->select(['id', 'tenant_id', 'name', 'description', 'status', 'progress', 'start_date', 'end_date']);
    }

    /**
     * Optimize interaction logs queries với filtering
     * 
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function optimizeInteractionLogsQuery(Builder $query, array $filters = []): Builder
    {
        // Apply indexes-friendly filters first
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
            
            if ($filters['visibility'] === 'client') {
                $query->where('client_approved', true);
            }
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Eager load creator information
        $query->with('creator:id,name,email');

        return $query->select([
            'id', 'project_id', 'linked_task_id', 'type', 'description', 
            'tag_path', 'visibility', 'client_approved', 'created_by', 'created_at'
        ]);
    }

    /**
     * Optimize user dashboard queries
     * 
     * @param int $userId
     * @param int $tenantId
     * @return array
     */
    public function getUserDashboardData(int $userId, int $tenantId): array
    {
        // Use single query với subqueries thay vì multiple queries
        $stats = DB::select("
            SELECT 
                (SELECT COUNT(*) FROM projects WHERE tenant_id = ? AND status = 'active') as active_projects,
                (SELECT COUNT(*) FROM tasks t 
                 JOIN projects p ON t.project_id = p.id 
                 WHERE p.tenant_id = ? AND t.status = 'pending' AND t.is_hidden = 0) as pending_tasks,
                (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL) as unread_notifications,
                (SELECT COUNT(*) FROM change_requests cr
                 JOIN projects p ON cr.project_id = p.id
                 WHERE p.tenant_id = ? AND cr.status = 'awaiting_approval') as pending_change_requests
        ", [$tenantId, $tenantId, $userId, $tenantId]);

        return (array) $stats[0];
    }

    /**
     * Optimize component hierarchy queries
     * 
     * @param int $projectId
     * @return array
     */
    public function getComponentHierarchy(int $projectId): array
    {
        // Sử dụng recursive CTE cho MySQL 8.0+
        $components = DB::select("
            WITH RECURSIVE component_tree AS (
                SELECT id, parent_component_id, name, progress_percent, planned_cost, actual_cost, 0 as level
                FROM components 
                WHERE project_id = ? AND parent_component_id IS NULL
                
                UNION ALL
                
                SELECT c.id, c.parent_component_id, c.name, c.progress_percent, c.planned_cost, c.actual_cost, ct.level + 1
                FROM components c
                INNER JOIN component_tree ct ON c.parent_component_id = ct.id
                WHERE c.project_id = ?
            )
            SELECT * FROM component_tree ORDER BY level, name
        ", [$projectId, $projectId]);

        return $components;
    }

    /**
     * Batch process large datasets với chunking
     * 
     * @param Builder $query
     * @param callable $callback
     * @param int $chunkSize
     * @return void
     */
    public function processInChunks(Builder $query, callable $callback, int $chunkSize = 1000): void
    {
        $query->chunk($chunkSize, function ($records) use ($callback) {
            $callback($records);
        });
    }

    /**
     * Analyze slow queries và suggest optimizations
     * 
     * @return array
     */
    public function analyzeSlowQueries(): array
    {
        // Enable slow query log analysis
        $slowQueries = DB::select("
            SELECT 
                sql_text,
                exec_count,
                avg_timer_wait/1000000000 as avg_time_seconds,
                sum_timer_wait/1000000000 as total_time_seconds
            FROM performance_schema.events_statements_summary_by_digest 
            WHERE avg_timer_wait > 1000000000  -- queries taking more than 1 second
            ORDER BY avg_timer_wait DESC 
            LIMIT 10
        ");

        return $slowQueries;
    }
}