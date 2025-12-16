<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Observability Alert Service
 * 
 * Monitors performance budgets and sends alerts when thresholds are exceeded.
 * 
 * Budgets:
 * - Page load: p95 < 500ms
 * - API response: p95 < 300ms
 * - Error rate: < 0.1%
 */
class ObservabilityAlertService
{
    /**
     * Get performance budgets from config file
     */
    private function getBudgets(): array
    {
        static $budgets = null;
        
        if ($budgets === null) {
            $budgetsPath = base_path('performance-budgets.json');
            if (file_exists($budgetsPath)) {
                $budgets = json_decode(file_get_contents($budgetsPath), true);
            } else {
                $budgets = [];
            }
        }
        
        return $budgets;
    }

    /**
     * Get budget for a specific route
     */
    private function getRouteBudget(string $route): array
    {
        $budgets = $this->getBudgets();
        
        // Check for exact route match
        if (isset($budgets['api']['routes'][$route])) {
            return $budgets['api']['routes'][$route];
        }
        
        // Check for page route
        if (isset($budgets['pages'][$route])) {
            return $budgets['pages'][$route];
        }
        
        // Check if it's an API route and use default
        if (str_starts_with($route, '/api/')) {
            return $budgets['api']['default'] ?? [
                'p95_latency_ms' => 300,
                'p99_latency_ms' => 500,
            ];
        }
        
        // Use page default
        return $budgets['pages']['default'] ?? [
            'p95_load_time_ms' => 500,
            'p99_load_time_ms' => 1000,
        ];
    }

    /**
     * Check if performance budgets are exceeded
     * 
     * @param string $route Route path
     * @param float $p95LatencyMs p95 latency in milliseconds
     * @param float $errorRate Error rate (0.0 to 1.0)
     * @param string|null $tenantId Tenant ID
     * @return array Alerts if budgets exceeded
     */
    public function checkBudgets(
        string $route,
        float $p95LatencyMs,
        float $errorRate = 0.0,
        ?string $tenantId = null
    ): array {
        $alerts = [];
        $budget = $this->getRouteBudget($route);
        
        // Get p95 budget (for API or pages)
        $p95Budget = $budget['p95_latency_ms'] ?? $budget['p95_load_time_ms'] ?? 300;
        
        // Check latency budget
        if ($p95LatencyMs > $p95Budget) {
            $alerts[] = [
                'level' => 'warning',
                'type' => str_starts_with($route, '/api/') 
                    ? 'api_latency_budget_exceeded' 
                    : 'page_latency_budget_exceeded',
                'message' => "Route {$route} exceeded latency budget: p95 = {$p95LatencyMs}ms (budget: {$p95Budget}ms)",
                'route' => $route,
                'p95_latency_ms' => $p95LatencyMs,
                'budget_ms' => $p95Budget,
                'tenant_id' => $tenantId,
            ];
        }
        
        // Check p99 budget if available
        $p99Budget = $budget['p99_latency_ms'] ?? $budget['p99_load_time_ms'] ?? null;
        if ($p99Budget !== null) {
            // Note: We'd need to calculate p99 from actual data
            // For now, we check p95 only
        }
        
        // Check error rate budget (< 0.1%)
        if ($errorRate > 0.001) {
            $alerts[] = [
                'level' => 'error',
                'type' => 'error_rate_budget_exceeded',
                'message' => "Route {$route} exceeded error rate budget: " . ($errorRate * 100) . "% (budget: 0.1%)",
                'route' => $route,
                'error_rate' => $errorRate,
                'budget_rate' => 0.001,
                'tenant_id' => $tenantId,
            ];
        }
        
        // Log alerts
        foreach ($alerts as $alert) {
            $this->logAlert($alert);
        }
        
        return $alerts;
    }
    
    /**
     * Log alert
     */
    private function logAlert(array $alert): void
    {
        $logLevel = $alert['level'] === 'error' ? 'error' : 'warning';
        
        Log::log($logLevel, 'Performance budget exceeded', [
            'type' => $alert['type'],
            'route' => $alert['route'],
            'tenant_id' => $alert['tenant_id'] ?? null,
            'traceId' => request()->header('X-Request-Id'),
            'details' => $alert,
        ]);
        
        // Store alert in cache for dashboard
        $cacheKey = \App\Services\TenantCacheService::key('observability', 'alerts:' . $alert['route'], $alert['tenant_id']);
        $existingAlerts = \App\Services\TenantCacheService::get('observability', 'alerts:' . $alert['route'], [], $alert['tenant_id']);
        $existingAlerts[] = $alert;
        
        // Keep only last 10 alerts per route
        if (count($existingAlerts) > 10) {
            $existingAlerts = array_slice($existingAlerts, -10);
        }
        
        \App\Services\TenantCacheService::put('observability', 'alerts:' . $alert['route'], $existingAlerts, 3600, $alert['tenant_id']);
    }
    
    /**
     * Get alerts for route
     * 
     * @param string $route
     * @param string|null $tenantId
     * @return array
     */
    public function getAlerts(string $route, ?string $tenantId = null): array
    {
        return \App\Services\TenantCacheService::get('observability', 'alerts:' . $route, [], $tenantId);
    }
    
    /**
     * Get all alerts for tenant
     * 
     * @param string|null $tenantId
     * @return array
     */
    public function getAllAlerts(?string $tenantId = null): array
    {
        // This would query all alerts from cache/storage
        // For now, return empty array
        return [];
    }
}

