<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetricsCollector;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    private $metricsCollector;

    public function __construct(MetricsCollector $metricsCollector)
    {
        $this->metricsCollector = $metricsCollector;
    }

    /**
     * Get all metrics in JSON format
     */
    public function index(Request $request)
    {
        try {
            $metrics = $this->metricsCollector->collectAll();
            
            return response()->json([
                'status' => 'success',
                'timestamp' => now()->toISOString(),
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to collect metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get metrics in Prometheus format
     */
    public function prometheus(Request $request)
    {
        try {
            $prometheusMetrics = $this->metricsCollector->exportPrometheusFormat();
            
            return response($prometheusMetrics)
                ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export Prometheus metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get health check metrics
     */
    public function health(Request $request)
    {
        try {
            $metrics = $this->metricsCollector->collectAll();
            
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'checks' => [
                    'database' => $this->checkDatabaseHealth($metrics['database']),
                    'cache' => $this->checkCacheHealth($metrics['cache']),
                    'queue' => $this->checkQueueHealth($metrics['queue']),
                    'system' => $this->checkSystemHealth($metrics['system']),
                ]
            ];

            // Determine overall health
            $allHealthy = collect($health['checks'])->every(function ($check) {
                return $check['status'] === 'healthy';
            });

            if (!$allHealthy) {
                $health['status'] = 'degraded';
            }

            return response()->json($health);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific metric by key
     */
    public function show(Request $request, string $metric)
    {
        try {
            $metrics = $this->metricsCollector->collectAll();
            
            $value = data_get($metrics, $metric);
            
            if ($value === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Metric not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'timestamp' => now()->toISOString(),
                'metric' => $metric,
                'value' => $value
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get metric',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function checkDatabaseHealth(array $dbMetrics): array
    {
        $status = 'healthy';
        $issues = [];

        if ($dbMetrics['connection_status'] !== 'connected') {
            $status = 'unhealthy';
            $issues[] = 'Database connection failed';
        }

        if (isset($dbMetrics['connection_count']) && $dbMetrics['connection_count'] > 100) {
            $status = 'degraded';
            $issues[] = 'High connection count';
        }

        if (isset($dbMetrics['slow_queries']) && $dbMetrics['slow_queries'] > 10) {
            $status = 'degraded';
            $issues[] = 'High number of slow queries';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'details' => $dbMetrics
        ];
    }

    private function checkCacheHealth(array $cacheMetrics): array
    {
        $status = 'healthy';
        $issues = [];

        if ($cacheMetrics['status'] !== 'connected') {
            $status = 'unhealthy';
            $issues[] = 'Cache connection failed';
        }

        if (isset($cacheMetrics['hit_rate']) && $cacheMetrics['hit_rate'] < 80) {
            $status = 'degraded';
            $issues[] = 'Low cache hit rate';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'details' => $cacheMetrics
        ];
    }

    private function checkQueueHealth(array $queueMetrics): array
    {
        $status = 'healthy';
        $issues = [];

        if (isset($queueMetrics['pending_jobs']) && $queueMetrics['pending_jobs'] > 1000) {
            $status = 'degraded';
            $issues[] = 'High number of pending jobs';
        }

        if (isset($queueMetrics['failed_jobs']) && $queueMetrics['failed_jobs'] > 50) {
            $status = 'degraded';
            $issues[] = 'High number of failed jobs';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'details' => $queueMetrics
        ];
    }

    private function checkSystemHealth(array $systemMetrics): array
    {
        $status = 'healthy';
        $issues = [];

        if ($systemMetrics['cpu_usage'] > 80) {
            $status = 'degraded';
            $issues[] = 'High CPU usage';
        }

        if ($systemMetrics['memory_usage']['percentage'] > 85) {
            $status = 'degraded';
            $issues[] = 'High memory usage';
        }

        if ($systemMetrics['disk_usage']['percentage'] > 90) {
            $status = 'degraded';
            $issues[] = 'High disk usage';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'details' => $systemMetrics
        ];
    }
}
