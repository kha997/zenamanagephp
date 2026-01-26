<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

/**
 * Real-time WebSocket Service
 * 
 * Provides WebSocket functionality for:
 * - Live dashboard updates
 * - Real-time notifications
 * - Collaborative features
 * - Live data synchronization
 */
class WebSocketService
{
    private array $channels = [
        'dashboard' => 'dashboard_updates',
        'notifications' => 'user_notifications',
        'projects' => 'project_updates',
        'tasks' => 'task_updates',
        'users' => 'user_activity',
        'system' => 'system_alerts',
    ];

    private array $eventTypes = [
        'dashboard' => [
            'data_updated',
            'widget_refresh',
            'kpi_change',
            'chart_update',
        ],
        'notifications' => [
            'new_notification',
            'notification_read',
            'notification_cleared',
            'system_notification',
        ],
        'projects' => [
            'project_created',
            'project_updated',
            'project_deleted',
            'project_status_change',
        ],
        'tasks' => [
            'task_created',
            'task_updated',
            'task_deleted',
            'task_assigned',
            'task_completed',
        ],
        'users' => [
            'user_online',
            'user_offline',
            'user_activity',
            'user_status_change',
        ],
        'system' => [
            'maintenance_mode',
            'system_alert',
            'performance_warning',
        ],
    ];

    /**
     * Broadcast message to specific channel
     */
    public function broadcast(string $channel, string $event, array $data, string $tenantId = null): bool
    {
        try {
            $fullChannel = $this->buildChannelName($channel, $tenantId);
            $message = $this->buildMessage($event, $data);
            
            // Publish to Redis for WebSocket server
            Redis::publish($fullChannel, json_encode($message));
            
            // Log the broadcast
            Log::info('WebSocket broadcast', [
                'channel' => $fullChannel,
                'event' => $event,
                'data_size' => strlen(json_encode($data)),
                'tenant_id' => $tenantId,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('WebSocket broadcast failed', [
                'channel' => $channel,
                'event' => $event,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return false;
        }
    }

    /**
     * Broadcast to user-specific channel
     */
    public function broadcastToUser(string $userId, string $event, array $data, string $tenantId = null): bool
    {
        $channel = "user:{$userId}";
        return $this->broadcast($channel, $event, $data, $tenantId);
    }

    /**
     * Broadcast to tenant-specific channel
     */
    public function broadcastToTenant(string $tenantId, string $channel, string $event, array $data): bool
    {
        return $this->broadcast($channel, $event, $data, $tenantId);
    }

    /**
     * Broadcast to multiple users
     */
    public function broadcastToUsers(array $userIds, string $event, array $data, string $tenantId = null): bool
    {
        $success = true;
        foreach ($userIds as $userId) {
            if (!$this->broadcastToUser($userId, $event, $data, $tenantId)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Subscribe to channel (for WebSocket server)
     */
    public function subscribe(string $channel, callable $callback): void
    {
        try {
            Redis::subscribe([$channel], function ($message, $channel) use ($callback) {
                $data = json_decode($message, true);
                if ($data) {
                    $callback($data, $channel);
                }
            });
        } catch (\Exception $e) {
            Log::error('WebSocket subscription failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get online users count
     */
    public function getOnlineUsersCount(string $tenantId = null): int
    {
        try {
            $pattern = $tenantId ? "online_users:{$tenantId}:*" : "online_users:*";
            $keys = Redis::keys($pattern);
            return count($keys);
        } catch (\Exception $e) {
            Log::error('Failed to get online users count', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return 0;
        }
    }

    /**
     * Mark user as online
     */
    public function markUserOnline(string $userId, string $tenantId = null): bool
    {
        try {
            $key = $this->buildOnlineUserKey($userId, $tenantId);
            $data = [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'online_at' => time(),
                'last_seen' => time(),
            ];
            
            Redis::setex($key, 300, json_encode($data)); // 5 minutes TTL
            
            // Broadcast user online event
            $this->broadcast('users', 'user_online', [
                'user_id' => $userId,
                'online_at' => $data['online_at'],
            ], $tenantId);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark user online', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return false;
        }
    }

    /**
     * Mark user as offline
     */
    public function markUserOffline(string $userId, string $tenantId = null): bool
    {
        try {
            $key = $this->buildOnlineUserKey($userId, $tenantId);
            Redis::del($key);
            
            // Broadcast user offline event
            $this->broadcast('users', 'user_offline', [
                'user_id' => $userId,
                'offline_at' => time(),
            ], $tenantId);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark user offline', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return false;
        }
    }

    /**
     * Update user activity
     */
    public function updateUserActivity(string $userId, string $activity, array $metadata = [], string $tenantId = null): bool
    {
        try {
            $key = $this->buildOnlineUserKey($userId, $tenantId);
            $existingData = Redis::get($key);
            
            if ($existingData) {
                $data = json_decode($existingData, true);
                $data['last_seen'] = time();
                $data['last_activity'] = $activity;
                $data['activity_metadata'] = $metadata;
                
                Redis::setex($key, 300, json_encode($data));
                
                // Broadcast user activity
                $this->broadcast('users', 'user_activity', [
                    'user_id' => $userId,
                    'activity' => $activity,
                    'metadata' => $metadata,
                    'timestamp' => time(),
                ], $tenantId);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update user activity', [
                'user_id' => $userId,
                'activity' => $activity,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return false;
        }
    }

    /**
     * Send notification via WebSocket
     */
    public function sendNotification(string $userId, array $notification, string $tenantId = null): bool
    {
        try {
            $event = 'new_notification';
            $data = [
                'notification' => $notification,
                'timestamp' => time(),
            ];
            
            return $this->broadcastToUser($userId, $event, $data, $tenantId);
        } catch (\Exception $e) {
            Log::error('Failed to send WebSocket notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return false;
        }
    }

    /**
     * Broadcast dashboard update
     */
    public function broadcastDashboardUpdate(string $event, array $data, string $tenantId = null): bool
    {
        return $this->broadcast('dashboard', $event, $data, $tenantId);
    }

    /**
     * Broadcast project update
     */
    public function broadcastProjectUpdate(int $projectId, string $event, array $data, string $tenantId = null): bool
    {
        $data['project_id'] = $projectId;
        return $this->broadcast('projects', $event, $data, $tenantId);
    }

    /**
     * Broadcast task update
     */
    public function broadcastTaskUpdate(int $taskId, string $event, array $data, string $tenantId = null): bool
    {
        $data['task_id'] = $taskId;
        return $this->broadcast('tasks', $event, $data, $tenantId);
    }

    /**
     * Get WebSocket statistics
     */
    public function getStats(): array
    {
        try {
            $stats = [
                'online_users' => $this->getOnlineUsersCount(),
                'channels' => array_keys($this->channels),
                'event_types' => $this->eventTypes,
                'redis_connected' => $this->isRedisConnected(),
            ];
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get WebSocket stats', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Build channel name with tenant isolation
     */
    private function buildChannelName(string $channel, string $tenantId = null): string
    {
        $baseChannel = $this->channels[$channel] ?? $channel;
        
        if ($tenantId) {
            return "tenant:{$tenantId}:{$baseChannel}";
        }
        
        return $baseChannel;
    }

    /**
     * Build message structure
     */
    private function buildMessage(string $event, array $data): array
    {
        return [
            'event' => $event,
            'data' => $data,
            'timestamp' => time(),
            'id' => uniqid('ws_', true),
        ];
    }

    /**
     * Build online user key
     */
    private function buildOnlineUserKey(string $userId, string $tenantId = null): string
    {
        if ($tenantId) {
            return "online_users:{$tenantId}:{$userId}";
        }
        
        return "online_users:{$userId}";
    }

    /**
     * Check if Redis is connected
     */
    private function isRedisConnected(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate event type
     */
    public function isValidEvent(string $channel, string $event): bool
    {
        return isset($this->eventTypes[$channel]) && 
               in_array($event, $this->eventTypes[$channel]);
    }

    /**
     * Get available channels
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get available event types for channel
     */
    public function getEventTypes(string $channel): array
    {
        return $this->eventTypes[$channel] ?? [];
    }
}
