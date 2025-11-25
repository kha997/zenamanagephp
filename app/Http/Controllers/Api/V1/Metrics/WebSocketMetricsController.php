<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Metrics;

use App\Http\Controllers\Controller;
use App\Services\WebSocketMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket Metrics Controller
 * 
 * Provides metrics endpoint for WebSocket server monitoring
 * 
 * GET /api/v1/metrics/websocket
 */
class WebSocketMetricsController extends Controller
{
    public function __construct(
        private WebSocketMetricsService $metricsService
    ) {}

    /**
     * Get WebSocket metrics
     * 
     * Returns:
     * - connections: total, per tenant
     * - messages: per second, total today
     * - errors: per second, total today
     * - dropped: per second, total today
     */
    public function index(): JsonResponse
    {
        try {
            $metrics = $this->metricsService->getAllMetrics();
            
            // Format response according to documentation
            $response = [
                'connections' => [
                    'total' => $metrics['connections']['total_connections'] ?? 0,
                    'per_tenant' => $this->getConnectionsPerTenant($metrics),
                ],
                'messages' => [
                    'per_second' => $metrics['message_rate']['messages_per_second'] ?? 0,
                    'total_today' => $this->getTotalMessagesToday(),
                ],
                'errors' => [
                    'per_second' => $this->calculateErrorsPerSecond($metrics),
                    'total_today' => $metrics['error_rate']['error_count'] ?? 0,
                ],
                'dropped' => [
                    'per_second' => 0, // TODO: Implement dropped message tracking
                    'total_today' => 0, // TODO: Implement dropped message tracking
                ],
                'timestamp' => now()->toISOString(),
            ];
            
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Failed to get WebSocket metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'WEBSOCKET_METRICS_ERROR',
                    'message' => 'Failed to retrieve WebSocket metrics',
                    'traceId' => request()->header('X-Request-Id'),
                ],
            ], 500);
        }
    }

    /**
     * Get connections per tenant
     */
    private function getConnectionsPerTenant(array $metrics): array
    {
        $userConnections = $metrics['connections']['user_connections'] ?? [];
        $perTenant = [];
        
        foreach ($userConnections as $userId => $connections) {
            // TODO: Get tenant_id from user connections
            // For now, return empty array
            // This should be implemented when DashboardWebSocketHandler tracks tenant connections
        }
        
        return $perTenant;
    }

    /**
     * Get total messages today
     */
    private function getTotalMessagesToday(): int
    {
        // TODO: Implement daily message counter
        // This should track messages per day in cache
        return 0;
    }

    /**
     * Calculate errors per second
     */
    private function calculateErrorsPerSecond(array $metrics): float
    {
        $errorRate = $metrics['error_rate']['error_rate'] ?? 0;
        // Convert percentage to per second (approximate)
        return round($errorRate / 100, 4);
    }
}

