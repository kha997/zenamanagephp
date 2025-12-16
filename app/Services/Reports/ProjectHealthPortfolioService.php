<?php declare(strict_types=1);

namespace App\Services\Reports;

use App\Events\ProjectHealthPortfolioGenerated;
use App\Models\Project;
use App\Services\Projects\ProjectOverviewService;
use Illuminate\Support\Facades\Cache;

/**
 * Project Health Portfolio Service
 * 
 * Round 74: Project Health Portfolio API
 * 
 * Provides health summary for all projects of a tenant.
 * Reuses ProjectOverviewService::buildOverview() to get health data for each project.
 */
class ProjectHealthPortfolioService
{
    public function __construct(
        private readonly ProjectOverviewService $projectOverviewService,
    ) {
    }

    /**
     * Get health for all projects of a tenant
     * 
     * Round 83: Project Health Observability & Perf Baseline
     * Round 85: Project Health Portfolio Caching
     * 
     * Emits ProjectHealthPortfolioGenerated event with performance metrics.
     * When caching is enabled, event is only dispatched on cache rebuilds (not on cache hits).
     * 
     * @param string|int $tenantId Tenant ID
     * @return array<int, array{project: array, health: array}>
     */
    public function getProjectHealthForTenant(string|int $tenantId): array
    {
        $cacheEnabled = (bool) config('reports.project_health.cache_enabled', false);
        $ttlSeconds = (int) config('reports.project_health.cache_ttl_seconds', 60);

        // If caching is disabled or TTL is invalid, use old behavior
        if (!$cacheEnabled || $ttlSeconds <= 0) {
            return $this->buildPortfolioForTenant($tenantId);
        }

        $cacheKey = $this->makeCacheKeyForTenant($tenantId);

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($tenantId) {
            return $this->buildPortfolioForTenant($tenantId);
        });
    }

    /**
     * Build the portfolio for a tenant and emit metrics.
     * 
     * Round 85: Project Health Portfolio Caching
     * 
     * This method encapsulates the actual portfolio building logic and event dispatch.
     * It is called directly when caching is disabled, or via Cache::remember when caching is enabled.
     * 
     * @param string|int $tenantId Tenant ID
     * @return array<int, array{project: array, health: array}>
     */
    private function buildPortfolioForTenant(string|int $tenantId): array
    {
        $startedAt = microtime(true);
        
        $projects = Project::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        $portfolio = $projects
            ->map(function (Project $project) use ($tenantId): array {
                // Reuse existing overview logic
                $overview = $this->projectOverviewService->buildOverview($tenantId, (string) $project->id);

                return [
                    'project' => $overview['project'],
                    'health' => $overview['health'],
                ];
            })
            ->values()
            ->all();

        // Calculate duration and project count
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $projectCount = is_countable($portfolio) ? count($portfolio) : 0;

        // Emit event with performance metrics
        // Note: When caching is enabled, this event is only dispatched on cache rebuilds
        event(new ProjectHealthPortfolioGenerated(
            tenantId: $tenantId,
            projectCount: $projectCount,
            durationMs: $durationMs,
        ));

        return $portfolio;
    }

    /**
     * Generate cache key for a tenant.
     * 
     * Round 85: Project Health Portfolio Caching
     * 
     * @param string|int $tenantId Tenant ID
     * @return string Cache key
     */
    private function makeCacheKeyForTenant(string|int $tenantId): string
    {
        return 'project_health_portfolio:' . (int) $tenantId;
    }
}

