<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\DashboardService;
use App\Http\Controllers\BaseApiController;
use App\Models\DashboardAlert;
use App\Models\DashboardMetricValue;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Dashboard Server-Sent Events Controller
 * 
 * Xử lý Server-Sent Events cho Dashboard real-time updates
 */
class DashboardSSEController extends BaseApiController
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Stream dashboard events
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $projectId = $request->get('project_id');
        $channels = $request->get('channels', ['dashboard', 'alerts', 'metrics']);

        return response()->stream(function () use ($user, $projectId, $channels) {
            // SSE implementation here
            echo "data: {\"message\": \"SSE connection established\"}\n\n";
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Cache-Control',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Stream dashboard events
     */
    private function streamDashboardEvents(User $user, ?string $projectId, array $channels): void
    {
        $lastEventId = 0;
        $heartbeatInterval = 30; // seconds
        $lastHeartbeat = time();

        // Send initial connection event
        $this->sendSSEEvent('connection', [
            'status' => 'connected',
            'user_id' => $user->id,
            'channels' => $channels,
            'timestamp' => now()->toISOString()
        ], $lastEventId++);

        // Send initial dashboard data
        $this->sendInitialData($user, $projectId, $channels, $lastEventId++);

        while (true) {
            // Check if client disconnected
            if (connection_aborted()) {
                Log::info('SSE client disconnected', ['user_id' => $user->id]);
                break;
            }

            // Send heartbeat
            if (time() - $lastHeartbeat >= $heartbeatInterval) {
                $this->sendSSEEvent('heartbeat', [
                    'timestamp' => now()->toISOString()
                ], $lastEventId++);
                $lastHeartbeat = time();
            }

            // Check for new events
            $this->checkForNewEvents($user, $projectId, $channels, $lastEventId);

            // Sleep to prevent excessive CPU usage
            usleep(100000); // 100ms
        }
    }

    /**
     * Send initial data
     */
    private function sendInitialData(User $user, ?string $projectId, array $channels, int &$eventId): void
    {
        foreach ($channels as $channel) {
            switch ($channel) {
                case 'dashboard':
                    $this->sendDashboardData($user, $projectId, $eventId++);
                    break;
                    
                case 'alerts':
                    $this->sendAlertsData($user, $projectId, $eventId++);
                    break;
                    
                case 'metrics':
                    $this->sendMetricsData($user, $projectId, $eventId++);
                    break;
                    
                case 'notifications':
                    $this->sendNotificationsData($user, $eventId++);
                    break;
            }
        }
    }

    /**
     * Check for new events
     */
    private function checkForNewEvents(User $user, ?string $projectId, array $channels, int &$eventId): void
    {
        foreach ($channels as $channel) {
            $cacheKey = "sse_last_check_{$user->id}_{$channel}";
            $lastCheck = Cache::get($cacheKey, 0);
            $currentTime = time();

            // Check every 5 seconds
            if ($currentTime - $lastCheck >= 5) {
                Cache::put($cacheKey, $currentTime, 60);

                switch ($channel) {
                    case 'dashboard':
                        $this->checkDashboardUpdates($user, $projectId, $eventId++);
                        break;
                        
                    case 'alerts':
                        $this->checkNewAlerts($user, $projectId, $eventId++);
                        break;
                        
                    case 'metrics':
                        $this->checkMetricUpdates($user, $projectId, $eventId++);
                        break;
                        
                    case 'notifications':
                        $this->checkNotifications($user, $eventId++);
                        break;
                }
            }
        }
    }

    /**
     * Send dashboard data
     */
    private function sendDashboardData(User $user, ?string $projectId, int $eventId): void
    {
        try {
            $dashboard = $this->dashboardService->getUserDashboard($user->id);
            
            $this->sendSSEEvent('dashboard_data', [
                'dashboard' => $dashboard,
                'project_id' => $projectId
            ], $eventId);
            
        } catch (\Exception $e) {
            Log::error('Error sending dashboard data via SSE', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send alerts data
     */
    private function sendAlertsData(User $user, ?string $projectId, int $eventId): void
    {
        try {
            $alerts = $this->dashboardService->getUserAlerts($user->id, $projectId, null, null, true);
            
            $this->sendSSEEvent('alerts_data', [
                'alerts' => $alerts,
                'count' => count($alerts)
            ], $eventId);
            
        } catch (\Exception $e) {
            Log::error('Error sending alerts data via SSE', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send metrics data
     */
    private function sendMetricsData(User $user, ?string $projectId, int $eventId): void
    {
        try {
            $metrics = $this->dashboardService->getDashboardMetrics($user, $projectId);
            
            $this->sendSSEEvent('metrics_data', [
                'metrics' => $metrics
            ], $eventId);
            
        } catch (\Exception $e) {
            Log::error('Error sending metrics data via SSE', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notifications data
     */
    private function sendNotificationsData(User $user, int $eventId): void
    {
        try {
            // Get unread notifications count
            $unreadCount = $user->unreadNotifications()->count();
            
            $this->sendSSEEvent('notifications_data', [
                'unread_count' => $unreadCount
            ], $eventId);
            
        } catch (\Exception $e) {
            Log::error('Error sending notifications data via SSE', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check for dashboard updates
     */
    private function checkDashboardUpdates(User $user, ?string $projectId, int $eventId): void
    {
        $cacheKey = "dashboard_last_update_{$user->id}";
        $lastUpdate = Cache::get($cacheKey, 0);
        
        // Check if dashboard was updated recently
        $dashboard = $this->dashboardService->getUserDashboard($user->id);
        $currentUpdate = $dashboard->updated_at->timestamp;
        
        if ($currentUpdate > $lastUpdate) {
            Cache::put($cacheKey, $currentUpdate, 300);
            
            $this->sendSSEEvent('dashboard_update', [
                'dashboard' => $dashboard,
                'project_id' => $projectId
            ], $eventId);
        }
    }

    /**
     * Check for new alerts
     */
    private function checkNewAlerts(User $user, ?string $projectId, int $eventId): void
    {
        $cacheKey = "alerts_last_check_{$user->id}";
        $lastAlertId = Cache::get($cacheKey, 0);
        
        $latestAlert = DashboardAlert::forUser($user->id)
            ->when($projectId, function ($query) use ($projectId) {
                $query->where('project_id', $projectId);
            })
            ->latest()
            ->first();
        
        if ($latestAlert && $latestAlert->id !== $lastAlertId) {
            Cache::put($cacheKey, $latestAlert->id, 300);
            
            $this->sendSSEEvent('new_alert', [
                'alert' => $latestAlert,
                'project_id' => $projectId
            ], $eventId);
        }
    }

    /**
     * Check for metric updates
     */
    private function checkMetricUpdates(User $user, ?string $projectId, int $eventId): void
    {
        $cacheKey = "metrics_last_update_{$user->tenant_id}";
        $lastUpdate = Cache::get($cacheKey, 0);
        
        $latestMetric = DashboardMetricValue::forTenant($user->tenant_id)
            ->when($projectId, function ($query) use ($projectId) {
                $query->where('project_id', $projectId);
            })
            ->latest('recorded_at')
            ->first();
        
        if ($latestMetric && $latestMetric->recorded_at->timestamp > $lastUpdate) {
            Cache::put($cacheKey, $latestMetric->recorded_at->timestamp, 300);
            
            $this->sendSSEEvent('metric_update', [
                'metric' => $latestMetric,
                'project_id' => $projectId
            ], $eventId);
        }
    }

    /**
     * Check for notifications
     */
    private function checkNotifications(User $user, int $eventId): void
    {
        $cacheKey = "notifications_last_check_{$user->id}";
        $lastCheck = Cache::get($cacheKey, 0);
        
        $unreadCount = $user->unreadNotifications()->count();
        $lastUnreadCount = Cache::get("notifications_count_{$user->id}", 0);
        
        if ($unreadCount !== $lastUnreadCount) {
            Cache::put($cacheKey, time(), 300);
            Cache::put("notifications_count_{$user->id}", $unreadCount, 300);
            
            $this->sendSSEEvent('notification_update', [
                'unread_count' => $unreadCount
            ], $eventId);
        }
    }

    /**
     * Send SSE event
     */
    private function sendSSEEvent(string $event, array $data, int $id): void
    {
        echo "id: {$id}\n";
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        // Flush output immediately
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Broadcast event to specific user
     */
    public function broadcastToUser(string $userId, string $event, array $data): void
    {
        $cacheKey = "sse_broadcast_{$userId}_" . time();
        Cache::put($cacheKey, [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], 60);
    }

    /**
     * Broadcast dashboard update
     */
    public function broadcastDashboardUpdate(string $userId, string $widgetId, array $data): void
    {
        $this->broadcastToUser($userId, 'widget_update', [
            'widget_id' => $widgetId,
            'data' => $data
        ]);
    }

    /**
     * Broadcast alert
     */
    public function broadcastAlert(string $userId, array $alert): void
    {
        $this->broadcastToUser($userId, 'new_alert', [
            'alert' => $alert
        ]);
    }

    /**
     * Broadcast metric update
     */
    public function broadcastMetricUpdate(string $tenantId, string $metricCode, array $data): void
    {
        $cacheKey = "sse_metric_broadcast_{$tenantId}_{$metricCode}_" . time();
        Cache::put($cacheKey, [
            'event' => 'metric_update',
            'data' => [
                'metric_code' => $metricCode,
                'data' => $data
            ],
            'timestamp' => now()->toISOString()
        ], 60);
    }
}