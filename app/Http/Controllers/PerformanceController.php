<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PerformanceMonitoringService;
use App\Services\MemoryMonitoringService;
use App\Services\NetworkMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceController extends Controller
{
    protected PerformanceMonitoringService $performanceService;
    protected MemoryMonitoringService $memoryService;
    protected NetworkMonitoringService $networkService;

    public function __construct(
        PerformanceMonitoringService $performanceService,
        MemoryMonitoringService $memoryService,
        NetworkMonitoringService $networkService
    ) {
        $this->performanceService = $performanceService;
        $this->memoryService = $memoryService;
        $this->networkService = $networkService;
    }

    /**
     * Get performance dashboard data
     */
    public function getDashboard(): JsonResponse
    {
        try {
            $data = [
                'performance' => $this->performanceService->getPerformanceStats(),
                'memory' => $this->memoryService->getMemoryStats(),
                'network' => $this->networkService->getNetworkStats(),
                'recommendations' => [
                    'performance' => $this->performanceService->getPerformanceRecommendations(),
                    'memory' => $this->memoryService->getMemoryRecommendations(),
                    'network' => $this->networkService->getNetworkRecommendations(),
                ],
                'thresholds' => [
                    'performance' => $this->performanceService->getPerformanceThresholds(),
                    'memory' => $this->memoryService->getMemoryThresholds(),
                    'network' => $this->networkService->getNetworkThresholds(),
                ],
                'timestamp' => now(),
            ];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Failed to get performance dashboard', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to get performance dashboard'], 500);
        }
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): JsonResponse
    {
        try {
            $stats = $this->performanceService->getPerformanceStats();
            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            Log::error('Failed to get performance stats', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to get performance stats'], 500);
        }
    }

    /**
     * Get memory statistics
     */
    public function getMemoryStats(): JsonResponse
    {
        try {
            $stats = $this->memoryService->getMemoryStats();
            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            Log::error('Failed to get memory stats', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to get memory stats'], 500);
        }
    }

    /**
     * Get network statistics
     */
    public function getNetworkStats(): JsonResponse
    {
        try {
            $stats = $this->networkService->getNetworkStats();
            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            Log::error('Failed to get network stats', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to get network stats'], 500);
        }
    }

    /**
     * Get performance recommendations
     */
    public function getRecommendations(): JsonResponse
    {
        try {
            $recommendations = [
                'performance' => $this->performanceService->getPerformanceRecommendations(),
                'memory' => $this->memoryService->getMemoryRecommendations(),
                'network' => $this->networkService->getNetworkRecommendations(),
            ];

            return response()->json(['success' => true, 'data' => $recommendations]);
        } catch (\Exception $e) {
            Log::error('Failed to get performance recommendations', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to get performance recommendations'], 500);
        }
    }

    /**
     * Get performance thresholds
     */
    public function getThresholds(): JsonResponse
    {
        try {
            $thresholds = [
                'performance' => $this->performanceService->getPerformanceThresholds(),
                'memory' => $this->memoryService->getMemoryThresholds(),
                'network' => $this->networkService->getNetworkThresholds(),
            ];

            return response()->json(['success' => true, 'data' => $thresholds]);
        } catch (\Exception $e) {
            Log::error('Failed to get performance thresholds', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to get performance thresholds'], 500);
        }
    }

    /**
     * Set performance thresholds
     */
    public function setThresholds(Request $request): JsonResponse
    {
        try {
        $request->validate([
                'performance' => 'nullable|array',
                'memory' => 'nullable|array',
                'network' => 'nullable|array',
            ]);

            if ($request->has('performance')) {
                $this->performanceService->setPerformanceThresholds($request->input('performance'));
            }

            if ($request->has('memory')) {
                $this->memoryService->setMemoryThresholds($request->input('memory'));
            }

            if ($request->has('network')) {
                $this->networkService->setNetworkThresholds($request->input('network'));
            }

            return response()->json(['success' => true, 'message' => 'Thresholds updated successfully']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to set performance thresholds', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to set performance thresholds'], 500);
        }
    }

    /**
     * Record page load time
     */
    public function recordPageLoadTime(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'route' => 'required|string',
                'load_time' => 'required|numeric|min:0',
            ]);

            $this->performanceService->recordPageLoadTime(
                $request->input('route'),
                $request->input('load_time')
            );

            return response()->json(['success' => true, 'message' => 'Page load time recorded']);
        } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to record page load time', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to record page load time'], 500);
        }
    }

    /**
     * Record API response time
     */
    public function recordApiResponseTime(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'endpoint' => 'required|string',
                'response_time' => 'required|numeric|min:0',
            ]);

            $this->performanceService->recordApiResponseTime(
                $request->input('endpoint'),
                $request->input('response_time')
            );

            return response()->json(['success' => true, 'message' => 'API response time recorded']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to record API response time', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to record API response time'], 500);
        }
    }

    /**
     * Record memory usage
     */
    public function recordMemoryUsage(): JsonResponse
    {
        try {
            $this->memoryService->recordMemoryUsage();
            return response()->json(['success' => true, 'message' => 'Memory usage recorded']);
        } catch (\Exception $e) {
            Log::error('Failed to record memory usage', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to record memory usage'], 500);
        }
    }

    /**
     * Monitor network endpoint
     */
    public function monitorNetworkEndpoint(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'url' => 'required|url',
                'options' => 'nullable|array',
            ]);

            $result = $this->networkService->monitorApiEndpoint(
                $request->input('url'),
                $request->input('options', [])
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to monitor network endpoint', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to monitor network endpoint'], 500);
        }
    }

    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics(): JsonResponse
    {
        try {
            $metrics = [
                'performance' => $this->performanceService->getRealTimeMetrics(),
                'memory' => $this->memoryService->getRealTimeMetrics(),
                'network' => $this->networkService->getRealTimeMetrics(),
            ];

            return response()->json(['success' => true, 'data' => $metrics]);
        } catch (\Exception $e) {
            Log::error('Failed to get real-time metrics', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to get real-time metrics'], 500);
        }
    }

    /**
     * Clear performance data
     */
    public function clearData(): JsonResponse
    {
        try {
            $this->performanceService->clearMetrics();
            $this->memoryService->clearHistory();
            $this->networkService->clearHistory();

            return response()->json(['success' => true, 'message' => 'Performance data cleared']);
        } catch (\Exception $e) {
            Log::error('Failed to clear performance data', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to clear performance data'], 500);
        }
    }

    /**
     * Export performance data
     */
    public function exportData(): JsonResponse
    {
        try {
            $data = [
                'performance' => $this->performanceService->exportPerformanceData(),
                'memory' => $this->memoryService->exportMemoryData(),
                'network' => $this->networkService->exportNetworkData(),
            ];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Failed to export performance data', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to export performance data'], 500);
        }
    }

    /**
     * Force garbage collection
     */
    public function forceGarbageCollection(): JsonResponse
    {
        try {
            $result = $this->memoryService->forceGarbageCollection();
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('Failed to force garbage collection', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to force garbage collection'], 500);
        }
    }

    /**
     * Test network connectivity
     */
    public function testConnectivity(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'url' => 'required|url',
            ]);

            $result = $this->networkService->testConnectivity($request->input('url'));
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to test network connectivity', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to test network connectivity'], 500);
        }
    }

    /**
     * Get network health status
     */
    public function getNetworkHealthStatus(): JsonResponse
    {
        try {
            $status = $this->networkService->getNetworkHealthStatus();
            return response()->json(['success' => true, 'data' => $status]);
        } catch (\Exception $e) {
            Log::error('Failed to get network health status', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to get network health status'], 500);
        }
    }
}