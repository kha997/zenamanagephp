<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    public function __construct(
        private MonitoringService $monitoringService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('ability:tenant');
    }

    /**
     * Get system health status
     */
    public function health(): JsonResponse
    {
        try {
            $health = $this->monitoringService->getSystemHealth();

            return response()->json([
                'status' => 'success',
                'data' => $health,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get system health', [
                'error' => $e->getMessage(),
                'tenant_id' => Auth::user()?->tenant_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get system health',
                'error' => [
                    'id' => 'monitoring_health_error',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get API metrics
     */
    public function apiMetrics(): JsonResponse
    {
        try {
            $metrics = $this->monitoringService->getApiMetrics();

            return response()->json([
                'status' => 'success',
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get API metrics', [
                'error' => $e->getMessage(),
                'tenant_id' => Auth::user()?->tenant_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get API metrics',
                'error' => [
                    'id' => 'monitoring_api_metrics_error',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get database metrics
     */
    public function databaseMetrics(): JsonResponse
    {
        try {
            $metrics = $this->monitoringService->getDatabaseMetrics();

            return response()->json([
                'status' => 'success',
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get database metrics', [
                'error' => $e->getMessage(),
                'tenant_id' => Auth::user()?->tenant_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get database metrics',
                'error' => [
                    'id' => 'monitoring_database_metrics_error',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get queue metrics
     */
    public function queueMetrics(): JsonResponse
    {
        try {
            $metrics = $this->monitoringService->getQueueMetrics();

            return response()->json([
                'status' => 'success',
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get queue metrics', [
                'error' => $e->getMessage(),
                'tenant_id' => Auth::user()?->tenant_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get queue metrics',
                'error' => [
                    'id' => 'monitoring_queue_metrics_error',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get monitoring dashboard data
     */
    public function dashboard(): JsonResponse
    {
        try {
            $data = [
                'api_metrics' => $this->monitoringService->getApiMetrics(),
                'database_metrics' => $this->monitoringService->getDatabaseMetrics(),
                'queue_metrics' => $this->monitoringService->getQueueMetrics(),
                'system_health' => $this->monitoringService->getSystemHealth(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get monitoring dashboard', [
                'error' => $e->getMessage(),
                'tenant_id' => Auth::user()?->tenant_id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get monitoring dashboard',
                'error' => [
                    'id' => 'monitoring_dashboard_error',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}