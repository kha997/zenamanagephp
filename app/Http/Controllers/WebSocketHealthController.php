<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\WebSocket\DashboardWebSocketHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket Health Controller
 * 
 * Exposes WebSocket health metrics and statistics.
 * Endpoint: GET /api/v1/ws/health
 */
class WebSocketHealthController extends Controller
{
    public function __construct(
        private DashboardWebSocketHandler $wsHandler
    ) {}

    /**
     * Get WebSocket health metrics
     * 
     * Returns:
     * - Total connections
     * - Connections per tenant
     * - Messages per second per tenant
     * - Slow consumers
     * - Drops (if any)
     */
    public function health(Request $request): JsonResponse
    {
        try {
            $stats = $this->wsHandler->getStats();
            $messageCounts = $this->wsHandler->getMessageCounts();
            $slowConsumers = $this->wsHandler->getSlowConsumers();
            $queueSizes = $this->wsHandler->getQueueSizes();
            
            // Calculate messages per second
            $messagesPerSecond = 0;
            foreach ($messageCounts as $connId => $data) {
                if (isset($data['count']) && isset($data['window_start'])) {
                    $elapsed = time() - $data['window_start'];
                    if ($elapsed > 0) {
                        $messagesPerSecond += $data['count'] / $elapsed;
                    }
                }
            }
            
            // Count drops (slow consumers that were disconnected)
            $drops = count($slowConsumers);
            
            return response()->json([
                'ok' => true,
                'health' => 'healthy',
                'metrics' => [
                    'connections' => [
                        'total' => $stats['total_connections'],
                        'authenticated' => $stats['authenticated_users'],
                        'per_tenant' => $stats['connections_per_tenant'] ?? [],
                    ],
                    'messages' => [
                        'per_second' => round($messagesPerSecond, 2),
                        'per_tenant_per_second' => $stats['messages_per_tenant_per_second'] ?? [],
                    ],
                    'performance' => [
                        'slow_consumers' => count($slowConsumers),
                        'slow_consumer_ids' => $slowConsumers,
                        'queue_sizes' => $queueSizes,
                        'drops' => $drops,
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('WebSocket health check failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'ok' => false,
                'health' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }
}

