<?php

namespace App\Services;

use App\Models\PerformanceMetric;
use App\Models\DashboardMetric;
use App\Models\DashboardMetricValue;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceMonitoringService
{
    /**
     * Log performance metric.
     */
    public function logMetric(string $name, float $value, string $unit, string $category, ?Tenant $tenant = null, ?array $metadata = null): void
    {
        $metric = PerformanceMetric::create([
            'metric_name' => $name,
            'metric_value' => $value,
            'metric_unit' => $unit,
            'category' => $category,
            'tenant_id' => $tenant?->id,
            'metadata' => $metadata,
        ]);

        // Log to Laravel log
        Log::info('Performance metric logged', [
            'metric_name' => $name,
            'metric_value' => $value,
            'metric_unit' => $unit,
            'category' => $category,
            'tenant_id' => $tenant?->id,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log page load time.
     */
    public function logPageLoadTime(float $loadTime, string $page, ?Tenant $tenant = null): void
    {
        $this->logMetric(
            'page_load_time',
            $loadTime,
            'ms',
            'page_performance',
            $tenant,
            ['page' => $page, 'timestamp' => now()->toISOString()]
        );

        // Check if load time exceeds threshold
        if ($loadTime > 500) {
            Log::warning('Page load time exceeds threshold', [
                'load_time' => $loadTime,
                'page' => $page,
                'threshold' => 500,
                'tenant_id' => $tenant?->id,
            ]);
        }
    }

    /**
     * Log API response time.
     */
    public function logApiResponseTime(float $responseTime, string $endpoint, ?Tenant $tenant = null): void
    {
        $this->logMetric(
            'api_response_time',
            $responseTime,
            'ms',
            'api_performance',
            $tenant,
            ['endpoint' => $endpoint, 'timestamp' => now()->toISOString()]
        );

        // Check if response time exceeds threshold
        if ($responseTime > 300) {
            Log::warning('API response time exceeds threshold', [
                'response_time' => $responseTime,
                'endpoint' => $endpoint,
                'threshold' => 300,
                'tenant_id' => $tenant?->id,
            ]);
        }
    }

    /**
     * Log database query time.
     */
    public function logDatabaseQueryTime(float $queryTime, string $query, ?Tenant $tenant = null): void
    {
        $this->logMetric(
            'database_query_time',
            $queryTime,
            'ms',
            'database_performance',
            $tenant,
            ['query' => $query, 'timestamp' => now()->toISOString()]
        );

        // Check if query time exceeds threshold
        if ($queryTime > 100) {
            Log::warning('Database query time exceeds threshold', [
                'query_time' => $queryTime,
                'query' => $query,
                'threshold' => 100,
                'tenant_id' => $tenant?->id,
            ]);
        }
    }

    /**
     * Log memory usage.
     */
    public function logMemoryUsage(?Tenant $tenant = null): void
    {
        $currentMemory = memory_get_usage(true) / 1024 / 1024; // MB
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB
        
        $this->logMetric(
            'memory_usage',
            $currentMemory,
            'MB',
            'system_performance',
            $tenant,
            ['peak_memory' => $peakMemory, 'timestamp' => now()->toISOString()]
        );

        // Check if memory usage exceeds threshold
        $memoryLimit = (int)ini_get('memory_limit') * 1024 * 1024; // Convert to bytes
        $usagePercentage = ($currentMemory * 1024 * 1024 / $memoryLimit) * 100;
        
        if ($usagePercentage > 80) {
            Log::warning('Memory usage exceeds threshold', [
                'current_memory' => $currentMemory,
                'peak_memory' => $peakMemory,
                'usage_percentage' => $usagePercentage,
                'threshold' => 80,
                'tenant_id' => $tenant?->id,
            ]);
        }
    }

    /**
     * Log cache performance.
     */
    public function logCachePerformance(float $operationTime, string $operation, ?Tenant $tenant = null): void
    {
        $this->logMetric(
            'cache_operation_time',
            $operationTime,
            'ms',
            'cache_performance',
            $tenant,
            ['operation' => $operation, 'timestamp' => now()->toISOString()]
        );

        // Check if cache operation time exceeds threshold
        if ($operationTime > 10) {
            Log::warning('Cache operation time exceeds threshold', [
                'operation_time' => $operationTime,
                'operation' => $operation,
                'threshold' => 10,
                'tenant_id' => $tenant?->id,
            ]);
        }
    }

    /**
     * Get performance summary for tenant.
     */
    public function getPerformanceSummary(Tenant $tenant): array
    {
        $metrics = PerformanceMetric::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        return [
            'total_metrics' => $metrics->count(),
            'average_page_load_time' => $metrics->where('metric_name', 'page_load_time')->avg('metric_value'),
            'average_api_response_time' => $metrics->where('metric_name', 'api_response_time')->avg('metric_value'),
            'average_database_query_time' => $metrics->where('metric_name', 'database_query_time')->avg('metric_value'),
            'average_memory_usage' => $metrics->where('metric_name', 'memory_usage')->avg('metric_value'),
            'average_cache_operation_time' => $metrics->where('metric_name', 'cache_operation_time')->avg('metric_value'),
            'warnings_count' => $this->getWarningsCount($tenant),
        ];
    }

    /**
     * Get warnings count for tenant.
     */
    private function getWarningsCount(Tenant $tenant): int
    {
        $metrics = PerformanceMetric::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $warnings = 0;
        
        foreach ($metrics as $metric) {
            switch ($metric->metric_name) {
                case 'page_load_time':
                    if ($metric->metric_value > 500) $warnings++;
                    break;
                case 'api_response_time':
                    if ($metric->metric_value > 300) $warnings++;
                    break;
                case 'database_query_time':
                    if ($metric->metric_value > 100) $warnings++;
                    break;
                case 'cache_operation_time':
                    if ($metric->metric_value > 10) $warnings++;
                    break;
            }
        }

        return $warnings;
    }

    /**
     * Create dashboard metric.
     */
    public function createDashboardMetric(string $name, string $description, string $unit, string $category, ?Tenant $tenant = null): DashboardMetric
    {
        return DashboardMetric::create([
            'name' => $name,
            'description' => $description,
            'unit' => $unit,
            'category' => $category,
            'tenant_id' => $tenant?->id,
            'is_active' => true,
        ]);
    }

    /**
     * Record dashboard metric value.
     */
    public function recordDashboardMetricValue(DashboardMetric $metric, float $value, ?Tenant $tenant = null, ?array $metadata = null): DashboardMetricValue
    {
        return DashboardMetricValue::create([
            'metric_id' => $metric->id,
            'tenant_id' => $tenant?->id,
            'value' => $value,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Get dashboard metrics for tenant.
     */
    public function getDashboardMetrics(Tenant $tenant): array
    {
        return DashboardMetric::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['values' => function ($query) {
                $query->orderBy('recorded_at', 'desc')->limit(10);
            }])
            ->get()
            ->toArray();
    }
}