<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

/**
 * KPI Cache Service
 * 
 * Handles caching of KPI data for performance optimization
 */
class KpiCacheService
{
    private const CACHE_PREFIX = 'kpi:';
    private const DEFAULT_TTL = 3600; // 1 hour
    private const KPI_TTL = 300; // 5 minutes for real-time KPIs

    /**
     * Get cached KPI data or generate if not exists
     */
    public function getKpiData(string $tenantId, string $type = 'dashboard'): array
    {
        $cacheKey = $this->getCacheKey($tenantId, $type);
        
        return Cache::remember($cacheKey, self::KPI_TTL, function () use ($tenantId, $type) {
            return $this->generateKpiData($tenantId, $type);
        });
    }

    /**
     * Generate KPI data for tenant
     */
    private function generateKpiData(string $tenantId, string $type): array
    {
        try {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return $this->getDefaultKpiData();
            }

            switch ($type) {
                case 'dashboard':
                    return $this->generateDashboardKpis($tenant);
                case 'projects':
                    return $this->generateProjectKpis($tenant);
                case 'tasks':
                    return $this->generateTaskKpis($tenant);
                case 'team':
                    return $this->generateTeamKpis($tenant);
                default:
                    return $this->getDefaultKpiData();
            }
        } catch (\Exception $e) {
            Log::error('KPI generation failed', [
                'tenant_id' => $tenantId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return $this->getDefaultKpiData();
        }
    }

    /**
     * Generate dashboard KPIs
     */
    private function generateDashboardKpis(Tenant $tenant): array
    {
        $projects = Project::where('tenant_id', $tenant->id)->get();
        $tasks = Task::where('tenant_id', $tenant->id)->get();
        $users = User::where('tenant_id', $tenant->id)->get();

        return [
            'total_projects' => $projects->count(),
            'active_projects' => $projects->where('status', 'active')->count(),
            'completed_projects' => $projects->where('status', 'completed')->count(),
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'completed')->count(),
            'pending_tasks' => $tasks->where('status', 'pending')->count(),
            'total_team_members' => $users->count(),
            'active_team_members' => $users->where('is_active', true)->count(),
            'project_completion_rate' => $this->calculateCompletionRate($projects),
            'task_completion_rate' => $this->calculateCompletionRate($tasks),
            'generated_at' => now()->toISOString(),
            'tenant_id' => $tenant->id,
        ];
    }

    /**
     * Generate project KPIs
     */
    private function generateProjectKpis(Tenant $tenant): array
    {
        $projects = Project::where('tenant_id', $tenant->id)->get();

        return [
            'total_projects' => $projects->count(),
            'projects_by_status' => $projects->groupBy('status')->map->count(),
            'projects_by_priority' => $projects->groupBy('priority')->map->count(),
            'average_project_duration' => $this->calculateAverageDuration($projects),
            'projects_overdue' => $projects->filter(function ($project) {
                return $project->due_date && $project->due_date < now() && $project->status !== 'completed';
            })->count(),
            'generated_at' => now()->toISOString(),
            'tenant_id' => $tenant->id,
        ];
    }

    /**
     * Generate task KPIs
     */
    private function generateTaskKpis(Tenant $tenant): array
    {
        $tasks = Task::where('tenant_id', $tenant->id)->get();

        return [
            'total_tasks' => $tasks->count(),
            'tasks_by_status' => $tasks->groupBy('status')->map->count(),
            'tasks_by_priority' => $tasks->groupBy('priority')->map->count(),
            'tasks_by_assignee' => $tasks->groupBy('assignee_id')->map->count(),
            'average_task_duration' => $this->calculateAverageDuration($tasks),
            'tasks_overdue' => $tasks->filter(function ($task) {
                return $task->due_date && $task->due_date < now() && $task->status !== 'completed';
            })->count(),
            'generated_at' => now()->toISOString(),
            'tenant_id' => $tenant->id,
        ];
    }

    /**
     * Generate team KPIs
     */
    private function generateTeamKpis(Tenant $tenant): array
    {
        $users = User::where('tenant_id', $tenant->id)->get();

        return [
            'total_members' => $users->count(),
            'active_members' => $users->where('is_active', true)->count(),
            'members_by_role' => $users->groupBy('role')->map->count(),
            'recent_logins' => $users->where('last_login_at', '>=', now()->subDays(7))->count(),
            'members_with_tasks' => $users->filter(function ($user) {
                return Task::where('assignee_id', $user->id)->exists();
            })->count(),
            'generated_at' => now()->toISOString(),
            'tenant_id' => $tenant->id,
        ];
    }

    /**
     * Calculate completion rate
     */
    private function calculateCompletionRate($items): float
    {
        if ($items->isEmpty()) {
            return 0.0;
        }

        $completed = $items->where('status', 'completed')->count();
        return round(($completed / $items->count()) * 100, 2);
    }

    /**
     * Calculate average duration
     */
    private function calculateAverageDuration($items): float
    {
        $itemsWithDuration = $items->filter(function ($item) {
            return $item->created_at && $item->updated_at;
        });

        if ($itemsWithDuration->isEmpty()) {
            return 0.0;
        }

        $totalDuration = $itemsWithDuration->sum(function ($item) {
            return $item->created_at->diffInDays($item->updated_at);
        });

        return round($totalDuration / $itemsWithDuration->count(), 2);
    }

    /**
     * Get default KPI data
     */
    private function getDefaultKpiData(): array
    {
        return [
            'total_projects' => 0,
            'active_projects' => 0,
            'completed_projects' => 0,
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'pending_tasks' => 0,
            'total_team_members' => 0,
            'active_team_members' => 0,
            'project_completion_rate' => 0.0,
            'task_completion_rate' => 0.0,
            'generated_at' => now()->toISOString(),
            'tenant_id' => null,
        ];
    }

    /**
     * Get cache key
     */
    private function getCacheKey(string $tenantId, string $type): string
    {
        return self::CACHE_PREFIX . $tenantId . ':' . $type;
    }

    /**
     * Clear KPI cache for tenant
     */
    public function clearKpiCache(string $tenantId, string $type = null): void
    {
        if ($type) {
            $cacheKey = $this->getCacheKey($tenantId, $type);
            Cache::forget($cacheKey);
        } else {
            // Clear all KPI cache for tenant
            $patterns = [
                $this->getCacheKey($tenantId, 'dashboard'),
                $this->getCacheKey($tenantId, 'projects'),
                $this->getCacheKey($tenantId, 'tasks'),
                $this->getCacheKey($tenantId, 'team'),
            ];

            foreach ($patterns as $pattern) {
                Cache::forget($pattern);
            }
        }

        Log::info('KPI cache cleared', [
            'tenant_id' => $tenantId,
            'type' => $type ?? 'all'
        ]);
    }

    /**
     * Warm up KPI cache
     */
    public function warmUpCache(string $tenantId): void
    {
        $types = ['dashboard', 'projects', 'tasks', 'team'];
        
        foreach ($types as $type) {
            $this->getKpiData($tenantId, $type);
        }

        Log::info('KPI cache warmed up', ['tenant_id' => $tenantId]);
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cache_driver' => config('cache.default'),
            'redis_connected' => $this->isRedisConnected(),
            'cache_prefix' => self::CACHE_PREFIX,
            'default_ttl' => self::DEFAULT_TTL,
            'kpi_ttl' => self::KPI_TTL,
        ];
    }

    /**
     * Check if Redis is connected
     */
    private function isRedisConnected(): bool
    {
        try {
            Cache::store('redis')->get('test');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
