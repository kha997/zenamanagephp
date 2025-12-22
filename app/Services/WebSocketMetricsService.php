<?php declare(strict_types=1);

namespace App\Services;

use App\WebSocket\DashboardWebSocketHandler;
use App\Services\TenantCacheService;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket Metrics Service
 * 
 * Collects and aggregates WebSocket connection metrics:
 * - Connection count per tenant
 * - Message rate per connection
 * - Error rate
 * - Slow consumer detection
 */
class WebSocketMetricsService
{
    private DashboardWebSocketHandler $handler;

    public function __construct(DashboardWebSocketHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Get connection metrics
     */
    public function getConnectionMetrics(): array
    {
        $stats = $this->handler->getStats();
        
        return [
            'total_connections' => $stats['total_connections'] ?? 0,
            'authenticated_users' => $stats['authenticated_users'] ?? 0,
            'user_connections' => $stats['user_connections'] ?? [],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get message rate metrics
     */
    public function getMessageRateMetrics(): array
    {
        $messageCounts = $this->handler->getMessageCounts();
        $now = time();
        
        $totalMessages = 0;
        $activeConnections = 0;
        $peakRate = 0;
        
        foreach ($messageCounts as $connId => $countData) {
            $windowAge = $now - ($countData['window_start'] ?? $now);
            
            // Only count connections with recent activity (within last 10 seconds)
            if ($windowAge < 10) {
                $rate = $countData['count'] ?? 0;
                $totalMessages += $rate;
                $activeConnections++;
                
                if ($rate > $peakRate) {
                    $peakRate = $rate;
                }
            }
        }
        
        $averageRate = $activeConnections > 0 ? $totalMessages / $activeConnections : 0;
        
        return [
            'messages_per_second' => round($averageRate, 2),
            'peak_message_rate' => $peakRate,
            'active_connections' => $activeConnections,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get error rate metrics
     */
    public function getErrorRateMetrics(): array
    {
        $id = 'error_rate:' . date('Y-m-d-H');
        $errorCount = TenantCacheService::get('websocket', $id, 0);
        
        return [
            'error_count' => $errorCount,
            'error_rate' => $this->calculateErrorRate($errorCount),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get slow consumer metrics
     */
    public function getSlowConsumerMetrics(): array
    {
        $slowConsumers = $this->handler->getSlowConsumers();
        $queueSizes = $this->handler->getQueueSizes();
        
        $totalQueued = array_sum($queueSizes);
        $maxQueueSize = !empty($queueSizes) ? max($queueSizes) : 0;
        
        return [
            'slow_consumers' => count($slowConsumers),
            'slow_consumer_ids' => $slowConsumers,
            'total_queued_messages' => $totalQueued,
            'max_queue_size' => $maxQueueSize,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get all metrics
     */
    public function getAllMetrics(): array
    {
        return [
            'connections' => $this->getConnectionMetrics(),
            'message_rate' => $this->getMessageRateMetrics(),
            'error_rate' => $this->getErrorRateMetrics(),
            'slow_consumers' => $this->getSlowConsumerMetrics(),
        ];
    }

    /**
     * Record error
     */
    public function recordError(string $errorType): void
    {
        $id = 'error_rate:' . date('Y-m-d-H');
        TenantCacheService::increment('websocket', $id, 1);
        // TTL is handled by the cache key pattern (hourly keys expire naturally)
    }

    /**
     * Calculate error rate
     */
    private function calculateErrorRate(int $errorCount): float
    {
        // Get total messages from message rate metrics
        $messageMetrics = $this->getMessageRateMetrics();
        $totalMessages = $messageMetrics['messages_per_second'] * $messageMetrics['active_connections'];
        
        if ($totalMessages === 0) {
            return 0.0;
        }
        
        // Error rate as percentage
        return round(($errorCount / max($totalMessages, 1)) * 100, 2);
    }

    /**
     * Health check for WebSocket service
     */
    public function healthCheck(): array
    {
        $metrics = $this->getAllMetrics();
        
        $isHealthy = true;
        $issues = [];
        
        // Check connection count
        if ($metrics['connections']['total_connections'] > 10000) {
            $isHealthy = false;
            $issues[] = 'High connection count';
        }
        
        // Check error rate
        if ($metrics['error_rate']['error_rate'] > 0.1) {
            $isHealthy = false;
            $issues[] = 'High error rate';
        }
        
        return [
            'healthy' => $isHealthy,
            'issues' => $issues,
            'metrics' => $metrics,
            'timestamp' => now()->toISOString(),
        ];
    }
}

