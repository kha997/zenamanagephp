<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RealData\RealPerformanceService;
use App\Services\PerformanceAlertingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PerformanceController extends Controller
{
    protected PerformanceMonitoringService $performanceService;
    protected PerformanceAlertingService $alertingService;

    public function __construct(
        PerformanceMonitoringService $performanceService,
        PerformanceAlertingService $alertingService
    ) {
        $this->performanceService = $performanceService;
        $this->alertingService = $alertingService;
    }

    /**
     * Get performance dashboard data
     */
    public function dashboard(): JsonResponse
    {
        try {
            // Check if user has permission to view performance data
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance data']
                ], 403);
            }

            $data = $this->performanceService->getDashboardData();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance data']
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function metrics(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance metrics']
                ], 403);
            }

            $metrics = $this->performanceService->getAllMetrics();

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance metrics']
            ], 500);
        }
    }

    /**
     * Get performance alerts
     */
    public function alerts(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance alerts']
                ], 403);
            }

            $alerts = $this->performanceService->getPerformanceAlerts();

            return response()->json([
                'success' => true,
                'data' => $alerts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance alerts']
            ], 500);
        }
    }

    /**
     * Get performance recommendations
     */
    public function recommendations(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance recommendations']
                ], 403);
            }

            $recommendations = $this->performanceService->getPerformanceRecommendations();

            return response()->json([
                'success' => true,
                'data' => $recommendations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance recommendations']
            ], 500);
        }
    }

    /**
     * Get performance trends
     */
    public function trends(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance trends']
                ], 403);
            }

            $trends = $this->performanceService->getPerformanceTrends();

            return response()->json([
                'success' => true,
                'data' => $trends
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance trends']
            ], 500);
        }
    }

    /**
     * Log performance metrics
     */
    public function logMetrics(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to log performance metrics']
                ], 403);
            }

            $this->performanceService->logMetrics();

            return response()->json([
                'success' => true,
                'message' => 'Performance metrics logged successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to log performance metrics']
            ], 500);
        }
    }
    
    /**
     * Get historical performance data
     */
    public function getHistoricalData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view historical data']
                ], 403);
            }

            $metric = $request->get('metric', 'api_response_time');
            $days = (int) $request->get('days', 7);
            
            // Validate metric
            $allowedMetrics = ['api_response_time', 'database_query_time', 'memory_usage', 'cpu_usage', 'error_rate', 'request_count'];
            if (!in_array($metric, $allowedMetrics)) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Invalid metric specified']
                ], 400);
            }
            
            // Validate days
            if ($days < 1 || $days > 30) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Days must be between 1 and 30']
                ], 400);
            }

            $historicalData = $this->getHistoricalMetrics($metric, $days);

            return response()->json([
                'success' => true,
                'data' => [
                    'metric' => $metric,
                    'days' => $days,
                    'data' => $historicalData
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve historical data']
            ], 500);
        }
    }
    
    /**
     * Get performance trends analysis
     */
    public function getPerformanceTrends(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance trends']
                ], 403);
            }

            $trends = $this->alertingService->detectPerformanceRegressions();
            $recommendations = $this->alertingService->getPerformanceRecommendations();

            return response()->json([
                'success' => true,
                'data' => [
                    'regressions' => $trends,
                    'recommendations' => $recommendations,
                    'analysis_date' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance trends']
            ], 500);
        }
    }
    
    /**
     * Get performance recommendations
     */
    public function getPerformanceRecommendations(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance recommendations']
                ], 403);
            }

            $recommendations = $this->alertingService->getPerformanceRecommendations();

            return response()->json([
                'success' => true,
                'data' => $recommendations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance recommendations']
            ], 500);
        }
    }
    
    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance alerts']
                ], 403);
            }

            $alerts = $this->alertingService->getPerformanceAlerts();

            return response()->json([
                'success' => true,
                'data' => $alerts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance alerts']
            ], 500);
        }
    }
    
    /**
     * Trigger performance threshold check
     */
    public function checkThresholds(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to check performance thresholds']
                ], 403);
            }

            $this->alertingService->checkPerformanceThresholds();

            return response()->json([
                'success' => true,
                'message' => 'Performance thresholds checked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to check performance thresholds']
            ], 500);
        }
    }
    
    /**
     * Get performance benchmarking data
     */
    public function getBenchmarks(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Insufficient permissions to view performance benchmarks']
                ], 403);
            }

            $realPerformanceService = app(RealPerformanceService::class);
            $benchmarks = $realPerformanceService->getPerformanceBenchmarks($user->tenant_id);

            return response()->json([
                'success' => true,
                'data' => $benchmarks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to retrieve performance benchmarks']
            ], 500);
        }
    }
    
    /**
     * Get historical metrics for a specific metric and time period
     */
    private function getHistoricalMetrics(string $metric, int $days): array
    {
        try {
            $data = [];
            $currentDate = now();
            
            for ($i = 0; $i < $days; $i++) {
                $date = $currentDate->copy()->subDays($i);
                $dateString = $date->toDateString();
                
                // Mock historical data - in production, this would query a metrics database
                $value = $this->generateMockHistoricalValue($metric, $date);
                
                $data[] = [
                    'date' => $dateString,
                    'value' => $value,
                    'timestamp' => $date->toISOString()
                ];
            }
            
            // Sort by date (oldest first)
            usort($data, function($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });
            
            return $data;
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Generate mock historical value for testing
     */
    private function generateMockHistoricalValue(string $metric, $date): float
    {
        $baseValues = [
            'api_response_time' => 200,
            'database_query_time' => 50,
            'memory_usage' => 60,
            'cpu_usage' => 40,
            'error_rate' => 2,
            'request_count' => 1000
        ];
        
        $baseValue = $baseValues[$metric] ?? 100;
        
        // Add some variation based on day of week and time
        $dayOfWeek = $date->dayOfWeek;
        $hour = $date->hour;
        
        // Higher values on weekdays and during business hours
        $multiplier = 1.0;
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday to Friday
            $multiplier += 0.2;
        }
        
        if ($hour >= 9 && $hour <= 17) { // Business hours
            $multiplier += 0.3;
        }
        
        // Add random variation
        $variation = (rand(-20, 20) / 100); // ±20%
        
        return round($baseValue * $multiplier * (1 + $variation), 2);
    }
    
    /**
     * Get performance benchmarks
     */
    private function getPerformanceBenchmarks(): array
    {
        return [
            'api_response_time' => [
                'excellent' => 100,
                'good' => 200,
                'acceptable' => 300,
                'poor' => 500
            ],
            'database_query_time' => [
                'excellent' => 10,
                'good' => 25,
                'acceptable' => 50,
                'poor' => 100
            ],
            'memory_usage' => [
                'excellent' => 40,
                'good' => 60,
                'acceptable' => 80,
                'poor' => 95
            ],
            'cpu_usage' => [
                'excellent' => 30,
                'good' => 50,
                'acceptable' => 75,
                'poor' => 90
            ],
            'error_rate' => [
                'excellent' => 0.1,
                'good' => 1,
                'acceptable' => 3,
                'poor' => 10
            ],
            'request_count' => [
                'excellent' => 2000,
                'good' => 1000,
                'acceptable' => 500,
                'poor' => 100
            ]
        ];
    }
}
