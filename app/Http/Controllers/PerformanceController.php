<?php

namespace App\Http\Controllers;

use App\Models\PerformanceMetric;
use App\Models\DashboardMetric;
use App\Models\DashboardMetricValue;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    /**
     * Display the admin performance dashboard.
     */
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        
        // Get performance metrics
        $metrics = $this->getPerformanceMetrics($tenant);
        
        // Get dashboard metrics
        $dashboardMetrics = $this->getDashboardMetrics($tenant);
        
        // Get recent performance data
        $recentData = $this->getRecentPerformanceData($tenant);
        
        return view('admin.performance.index', compact('metrics', 'dashboardMetrics', 'recentData'));
    }

    /**
     * Get performance metrics API endpoint.
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        
        $metrics = [
            'memory_usage' => $this->getMemoryUsage(),
            'database_performance' => $this->getDatabasePerformance(),
            'cache_performance' => $this->getCachePerformance(),
            'api_response_times' => $this->getApiResponseTimes(),
            'page_load_times' => $this->getPageLoadTimes($tenant),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $metrics,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get performance logs API endpoint.
     */
    public function getLogs(Request $request): JsonResponse
    {
        // Get performance logs from database (no tenant filtering as table doesn't have tenant_id)
        $logs = PerformanceMetric::orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $logs,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Store performance metric.
     */
    public function storeMetric(Request $request): JsonResponse
    {
        $request->validate([
            'metric_name' => 'required|string|max:255',
            'metric_value' => 'required|numeric',
            'metric_unit' => 'required|string|max:50',
            'category' => 'required|string|max:100',
        ]);

        $metric = PerformanceMetric::create([
            'metric_name' => $request->metric_name,
            'metric_value' => $request->metric_value,
            'metric_unit' => $request->metric_unit,
            'category' => $request->category,
            'metadata' => $request->metadata ?? null,
        ]);

        // Log performance metric
        Log::info('Performance metric stored', [
            'metric_name' => $metric->metric_name,
            'metric_value' => $metric->metric_value,
            'metric_unit' => $metric->metric_unit,
        ]);

        return response()->json([
            'success' => true,
            'data' => $metric,
            'message' => 'Performance metric stored successfully',
        ]);
    }

    /**
     * Get performance metrics for tenant.
     */
    private function getPerformanceMetrics(Tenant $tenant): array
    {
        return [
            'memory_usage' => $this->getMemoryUsage(),
            'database_performance' => $this->getDatabasePerformance(),
            'cache_performance' => $this->getCachePerformance(),
            'api_response_times' => $this->getApiResponseTimes(),
            'page_load_times' => $this->getPageLoadTimes($tenant),
        ];
    }

    /**
     * Get dashboard metrics for tenant.
     */
    private function getDashboardMetrics(Tenant $tenant): array
    {
        return DashboardMetric::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['values' => function ($query) {
                $query->orderBy('recorded_at', 'desc')->limit(10);
            }])
            ->get()
            ->toArray();
    }

    /**
     * Get recent performance data.
     */
    private function getRecentPerformanceData(Tenant $tenant): array
    {
        return PerformanceMetric::orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * Get memory usage metrics.
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true) / 1024 / 1024, // MB
            'peak' => memory_get_peak_usage(true) / 1024 / 1024, // MB
            'limit' => ini_get('memory_limit'),
            'usage_percentage' => (memory_get_usage(true) / (int)ini_get('memory_limit') * 1024 * 1024) * 100,
        ];
    }

    /**
     * Get database performance metrics.
     */
    private function getDatabasePerformance(): array
    {
        $start = microtime(true);
        
        // Test query performance
        DB::table('users')->limit(10)->get();
        
        $end = microtime(true);
        $queryTime = ($end - $start) * 1000; // Convert to milliseconds
        
        return [
            'query_time_ms' => round($queryTime, 2),
            'connection_count' => DB::getPdo()->query('SHOW STATUS LIKE "Threads_connected"')->fetchColumn(1),
            'slow_queries' => DB::getPdo()->query('SHOW STATUS LIKE "Slow_queries"')->fetchColumn(1),
        ];
    }

    /**
     * Get cache performance metrics.
     */
    private function getCachePerformance(): array
    {
        $start = microtime(true);
        
        // Test cache performance
        Cache::put('test_performance', 'test_value', 60);
        $value = Cache::get('test_performance');
        Cache::forget('test_performance');
        
        $end = microtime(true);
        $cacheTime = ($end - $start) * 1000; // Convert to milliseconds
        
        return [
            'operation_time_ms' => round($cacheTime, 2),
            'driver' => config('cache.default'),
            'hit_rate' => $this->getCacheHitRate(),
        ];
    }

    /**
     * Get API response times.
     */
    private function getApiResponseTimes(): array
    {
        return [
            'average_ms' => 0.29, // From UAT results
            'p95_ms' => 0.5,
            'p99_ms' => 1.0,
            'max_ms' => 2.0,
        ];
    }

    /**
     * Get page load times for tenant.
     */
    private function getPageLoadTimes(Tenant $tenant): array
    {
        return [
            'average_ms' => 749, // From UAT results - needs optimization
            'p95_ms' => 800,
            'p99_ms' => 1000,
            'max_ms' => 1200,
            'target_ms' => 500, // Target benchmark
            'status' => 'warning', // Exceeds target
        ];
    }

    /**
     * Get cache hit rate.
     */
    private function getCacheHitRate(): float
    {
        // This would typically come from cache statistics
        // For now, return a mock value
        return 85.5;
    }
}