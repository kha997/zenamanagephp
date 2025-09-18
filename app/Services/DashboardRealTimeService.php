<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\DashboardAlert;
use App\Models\DashboardMetricValue;
use App\Models\DashboardWidgetDataCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

/**
 * Dashboard Real-Time Service
 * 
 * Service xử lý real-time updates cho Dashboard System
 */
class DashboardRealTimeService
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Broadcast dashboard update
     */
    public function broadcastDashboardUpdate(string $userId, string $widgetId, array $data): void
    {
        try {
            // Broadcast via WebSocket
            $this->broadcastWebSocket($userId, 'dashboard_update', [
                'widget_id' => $widgetId,
                'data' => $data
            ]);

            // Broadcast via SSE
            $this->broadcastSSE($userId, 'widget_update', [
                'widget_id' => $widgetId,
                'data' => $data
            ]);

            // Clear widget cache
            $this->clearWidgetCache($userId, $widgetId);

            Log::info('Dashboard update broadcasted', [
                'user_id' => $userId,
                'widget_id' => $widgetId
            ]);

        } catch (\Exception $e) {
            Log::error('Error broadcasting dashboard update', [
                'user_id' => $userId,
                'widget_id' => $widgetId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast alert
     */
    public function broadcastAlert(string $userId, array $alert): void
    {
        try {
            // Broadcast via WebSocket
            $this->broadcastWebSocket($userId, 'alert', [
                'alert' => $alert
            ]);

            // Broadcast via SSE
            $this->broadcastSSE($userId, 'new_alert', [
                'alert' => $alert
            ]);

            Log::info('Alert broadcasted', [
                'user_id' => $userId,
                'alert_id' => $alert['id'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('Error broadcasting alert', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast metric update
     */
    public function broadcastMetricUpdate(string $tenantId, string $metricCode, array $data): void
    {
        try {
            // Broadcast to all users in tenant
            $users = User::where('tenant_id', $tenantId)->pluck('id');
            
            foreach ($users as $userId) {
                $this->broadcastWebSocket($userId, 'metric_update', [
                    'metric_code' => $metricCode,
                    'data' => $data
                ]);

                $this->broadcastSSE($userId, 'metric_update', [
                    'metric_code' => $metricCode,
                    'data' => $data
                ]);
            }

            Log::info('Metric update broadcasted', [
                'tenant_id' => $tenantId,
                'metric_code' => $metricCode,
                'users_count' => $users->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error broadcasting metric update', [
                'tenant_id' => $tenantId,
                'metric_code' => $metricCode,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast project update
     */
    public function broadcastProjectUpdate(string $projectId, string $eventType, array $data): void
    {
        try {
            // Get all users with access to this project
            $users = $this->getProjectUsers($projectId);
            
            foreach ($users as $userId) {
                $this->broadcastWebSocket($userId, 'project_update', [
                    'project_id' => $projectId,
                    'event_type' => $eventType,
                    'data' => $data
                ]);

                $this->broadcastSSE($userId, 'project_update', [
                    'project_id' => $projectId,
                    'event_type' => $eventType,
                    'data' => $data
                ]);
            }

            Log::info('Project update broadcasted', [
                'project_id' => $projectId,
                'event_type' => $eventType,
                'users_count' => $users->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error broadcasting project update', [
                'project_id' => $projectId,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast system notification
     */
    public function broadcastSystemNotification(string $tenantId, string $type, string $message, array $data = []): void
    {
        try {
            $users = User::where('tenant_id', $tenantId)->pluck('id');
            
            foreach ($users as $userId) {
                $this->broadcastWebSocket($userId, 'system_notification', [
                    'type' => $type,
                    'message' => $message,
                    'data' => $data
                ]);

                $this->broadcastSSE($userId, 'system_notification', [
                    'type' => $type,
                    'message' => $message,
                    'data' => $data
                ]);
            }

            Log::info('System notification broadcasted', [
                'tenant_id' => $tenantId,
                'type' => $type,
                'users_count' => $users->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error broadcasting system notification', [
                'tenant_id' => $tenantId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Trigger widget data refresh
     */
    public function triggerWidgetRefresh(string $userId, string $widgetId): void
    {
        try {
            // Clear cache
            $this->clearWidgetCache($userId, $widgetId);

            // Broadcast refresh event
            $this->broadcastWebSocket($userId, 'widget_refresh', [
                'widget_id' => $widgetId
            ]);

            $this->broadcastSSE($userId, 'widget_refresh', [
                'widget_id' => $widgetId
            ]);

            Log::info('Widget refresh triggered', [
                'user_id' => $userId,
                'widget_id' => $widgetId
            ]);

        } catch (\Exception $e) {
            Log::error('Error triggering widget refresh', [
                'user_id' => $userId,
                'widget_id' => $widgetId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast WebSocket message
     */
    private function broadcastWebSocket(string $userId, string $event, array $data): void
    {
        try {
            // Check if WebSocket server is running
            if ($this->isWebSocketServerRunning()) {
                // Send message to WebSocket server via Redis or Queue
                $message = [
                    'type' => 'broadcast',
                    'user_id' => $userId,
                    'event' => $event,
                    'data' => $data,
                    'timestamp' => now()->toISOString()
                ];

                // Use Redis pub/sub or Queue to communicate with WebSocket server
                Cache::store('redis')->publish('websocket_broadcast', json_encode($message));
            }
        } catch (\Exception $e) {
            Log::error('Error broadcasting WebSocket message', [
                'user_id' => $userId,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast SSE message
     */
    private function broadcastSSE(string $userId, string $event, array $data): void
    {
        try {
            $cacheKey = "sse_broadcast_{$userId}_" . time();
            Cache::put($cacheKey, [
                'event' => $event,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ], 60);
        } catch (\Exception $e) {
            Log::error('Error broadcasting SSE message', [
                'user_id' => $userId,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear widget cache
     */
    private function clearWidgetCache(string $userId, string $widgetId): void
    {
        try {
            // Clear specific widget cache
            DashboardWidgetDataCache::clearCacheData($widgetId, $userId);
            
            // Clear user's dashboard cache
            Cache::forget("dashboard_data_{$userId}");
            
        } catch (\Exception $e) {
            Log::error('Error clearing widget cache', [
                'user_id' => $userId,
                'widget_id' => $widgetId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get users with access to project
     */
    private function getProjectUsers(string $projectId): array
    {
        try {
            return \DB::table('project_user_roles')
                ->where('project_id', $projectId)
                ->pluck('user_id')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error getting project users', [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if WebSocket server is running
     */
    private function isWebSocketServerRunning(): bool
    {
        try {
            // Check if WebSocket server process is running
            $port = config('websocket.port', 8080);
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            
            if ($connection) {
                fclose($connection);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get real-time statistics
     */
    public function getRealTimeStats(): array
    {
        try {
            return [
                'websocket_connections' => $this->getWebSocketConnections(),
                'sse_connections' => $this->getSSEConnections(),
                'cache_hit_rate' => $this->getCacheHitRate(),
                'broadcast_rate' => $this->getBroadcastRate(),
                'last_update' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('Error getting real-time stats', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get WebSocket connections count
     */
    private function getWebSocketConnections(): int
    {
        try {
            // This would typically query the WebSocket server
            return Cache::get('websocket_connections_count', 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get SSE connections count
     */
    private function getSSEConnections(): int
    {
        try {
            // Count active SSE connections from cache
            $keys = Cache::store('redis')->getRedis()->keys('sse_connection_*');
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache hit rate
     */
    private function getCacheHitRate(): float
    {
        try {
            $hits = Cache::get('cache_hits', 0);
            $misses = Cache::get('cache_misses', 0);
            $total = $hits + $misses;
            
            return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get broadcast rate
     */
    private function getBroadcastRate(): int
    {
        try {
            return Cache::get('broadcast_rate_per_minute', 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Setup event listeners
     */
    public function setupEventListeners(): void
    {
        // Listen for model events
        Event::listen('eloquent.created: App\Models\DashboardAlert', function ($alert) {
            $this->broadcastAlert($alert->user_id, $alert->toArray());
        });

        Event::listen('eloquent.updated: App\Models\DashboardMetricValue', function ($metric) {
            $this->broadcastMetricUpdate($metric->tenant_id, $metric->metric->metric_code, $metric->toArray());
        });

        Event::listen('eloquent.updated: App\Models\UserDashboard', function ($dashboard) {
            $this->broadcastDashboardUpdate($dashboard->user_id, 'dashboard', $dashboard->toArray());
        });
    }
}
