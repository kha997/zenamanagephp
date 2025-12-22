<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Real-time Notification Service
 * 
 * Features:
 * - WebSocket connections management
 * - Real-time notifications
 * - User presence tracking
 * - Channel subscriptions
 * - Message broadcasting
 * - Notification persistence
 * - Delivery status tracking
 */
class RealTimeNotificationService
{
    private const PRESENCE_TTL = 300; // 5 minutes
    private const NOTIFICATION_TTL = 86400; // 24 hours
    private const CHANNEL_TTL = 3600; // 1 hour

    /**
     * Send real-time notification to user
     */
    public function sendToUser(User $user, array $notification, array $options = []): bool
    {
        try {
            $notificationId = Str::uuid()->toString();
            $channel = "user:{$user->id}";
            
            $message = [
                'id' => $notificationId,
                'type' => $notification['type'] ?? 'info',
                'title' => $notification['title'] ?? 'Notification',
                'message' => $notification['message'] ?? '',
                'data' => $notification['data'] ?? [],
                'timestamp' => now()->toISOString(),
                'persistent' => $options['persistent'] ?? false,
                'priority' => $options['priority'] ?? 'normal',
                'actions' => $options['actions'] ?? [],
            ];
            
            // Broadcast to WebSocket channel
            $this->broadcastToChannel($channel, $message);
            
            // Store notification if persistent
            if ($message['persistent']) {
                $this->storeNotification($user, $message);
            }
            
            // Log notification
            Log::info('Real-time notification sent', [
                'user_id' => $user->id,
                'notification_id' => $notificationId,
                'type' => $message['type'],
                'channel' => $channel,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send real-time notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);
            
            return false;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(array $users, array $notification, array $options = []): array
    {
        $results = [];
        
        foreach ($users as $user) {
            $results[$user->id] = $this->sendToUser($user, $notification, $options);
        }
        
        return $results;
    }

    /**
     * Send notification to tenant
     */
    public function sendToTenant(Tenant $tenant, array $notification, array $options = []): array
    {
        try {
            $users = User::where('tenant_id', $tenant->id)->get();
            return $this->sendToUsers($users->toArray(), $notification, $options);
            
        } catch (\Exception $e) {
            Log::error('Failed to send notification to tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);
            
            return [];
        }
    }

    /**
     * Send notification to role
     */
    public function sendToRole(string $role, array $notification, array $options = []): array
    {
        try {
            $users = User::where('role', $role)->get();
            return $this->sendToUsers($users->toArray(), $notification, $options);
            
        } catch (\Exception $e) {
            Log::error('Failed to send notification to role', [
                'role' => $role,
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);
            
            return [];
        }
    }

    /**
     * Broadcast to channel
     */
    public function broadcastToChannel(string $channel, array $message): bool
    {
        try {
            // Use Redis pub/sub for WebSocket broadcasting
            Redis::publish("notifications:{$channel}", json_encode($message));
            
            // Also store in channel history for late subscribers
            $this->storeChannelMessage($channel, $message);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast to channel', [
                'channel' => $channel,
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
            
            return false;
        }
    }

    /**
     * Subscribe user to channel
     */
    public function subscribeToChannel(User $user, string $channel, array $options = []): bool
    {
        try {
            $subscriptionKey = "subscription:{$user->id}:{$channel}";
            $subscriptionData = [
                'user_id' => $user->id,
                'channel' => $channel,
                'subscribed_at' => now()->toISOString(),
                'options' => $options,
            ];
            
            Cache::put($subscriptionKey, $subscriptionData, self::CHANNEL_TTL);
            
            // Add to user's active subscriptions
            $this->addUserSubscription($user, $channel);
            
            Log::info('User subscribed to channel', [
                'user_id' => $user->id,
                'channel' => $channel,
                'options' => $options,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to subscribe user to channel', [
                'user_id' => $user->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Unsubscribe user from channel
     */
    public function unsubscribeFromChannel(User $user, string $channel): bool
    {
        try {
            $subscriptionKey = "subscription:{$user->id}:{$channel}";
            Cache::forget($subscriptionKey);
            
            // Remove from user's active subscriptions
            $this->removeUserSubscription($user, $channel);
            
            Log::info('User unsubscribed from channel', [
                'user_id' => $user->id,
                'channel' => $channel,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe user from channel', [
                'user_id' => $user->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Update user presence
     */
    public function updatePresence(User $user, string $status = 'online', array $metadata = []): bool
    {
        try {
            $presenceKey = "presence:{$user->id}";
            $presenceData = [
                'user_id' => $user->id,
                'status' => $status,
                'last_seen' => now()->toISOString(),
                'metadata' => $metadata,
            ];
            
            Cache::put($presenceKey, $presenceData, self::PRESENCE_TTL);
            
            // Broadcast presence update
            $this->broadcastPresenceUpdate($user, $status, $metadata);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to update user presence', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get user presence
     */
    public function getUserPresence(User $user): ?array
    {
        $presenceKey = "presence:{$user->id}";
        return Cache::get($presenceKey);
    }

    /**
     * Get online users
     */
    public function getOnlineUsers(Tenant $tenant = null): array
    {
        try {
            $onlineUsers = [];
            
            // This would typically use Redis SCAN in production
            // For now, we'll use a simplified approach
            $users = $tenant ? 
                User::where('tenant_id', $tenant->id)->get() : 
                User::all();
            
            foreach ($users as $user) {
                $presence = $this->getUserPresence($user);
                if ($presence && $presence['status'] === 'online') {
                    $onlineUsers[] = [
                        'user_id' => $user->id,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'email' => $user->email,
                        'last_seen' => $presence['last_seen'],
                        'metadata' => $presence['metadata'],
                    ];
                }
            }
            
            return $onlineUsers;
            
        } catch (\Exception $e) {
            Log::error('Failed to get online users', [
                'tenant_id' => $tenant?->id,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Store notification
     */
    private function storeNotification(User $user, array $notification): void
    {
        $notificationKey = "notification:{$user->id}:{$notification['id']}";
        Cache::put($notificationKey, $notification, self::NOTIFICATION_TTL);
        
        // Add to user's notification list
        $this->addUserNotification($user, $notification);
    }

    /**
     * Store channel message
     */
    private function storeChannelMessage(string $channel, array $message): void
    {
        $channelKey = "channel:{$channel}:messages";
        $messages = Cache::get($channelKey, []);
        
        $messages[] = $message;
        
        // Keep only last 100 messages
        $messages = array_slice($messages, -100);
        
        Cache::put($channelKey, $messages, self::CHANNEL_TTL);
    }

    /**
     * Add user subscription
     */
    private function addUserSubscription(User $user, string $channel): void
    {
        $subscriptionsKey = "user_subscriptions:{$user->id}";
        $subscriptions = Cache::get($subscriptionsKey, []);
        
        if (!in_array($channel, $subscriptions)) {
            $subscriptions[] = $channel;
            Cache::put($subscriptionsKey, $subscriptions, self::CHANNEL_TTL);
        }
    }

    /**
     * Remove user subscription
     */
    private function removeUserSubscription(User $user, string $channel): void
    {
        $subscriptionsKey = "user_subscriptions:{$user->id}";
        $subscriptions = Cache::get($subscriptionsKey, []);
        
        $subscriptions = array_filter($subscriptions, fn($sub) => $sub !== $channel);
        Cache::put($subscriptionsKey, $subscriptions, self::CHANNEL_TTL);
    }

    /**
     * Add user notification
     */
    private function addUserNotification(User $user, array $notification): void
    {
        $notificationsKey = "user_notifications:{$user->id}";
        $notifications = Cache::get($notificationsKey, []);
        
        $notifications[] = $notification;
        
        // Keep only last 50 notifications
        $notifications = array_slice($notifications, -50);
        
        Cache::put($notificationsKey, $notifications, self::NOTIFICATION_TTL);
    }

    /**
     * Broadcast presence update
     */
    private function broadcastPresenceUpdate(User $user, string $status, array $metadata): void
    {
        $message = [
            'type' => 'presence_update',
            'user_id' => $user->id,
            'status' => $status,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ];
        
        // Broadcast to user's channel
        $this->broadcastToChannel("user:{$user->id}", $message);
        
        // Broadcast to tenant channel if applicable
        if ($user->tenant_id) {
            $this->broadcastToChannel("tenant:{$user->tenant_id}", $message);
        }
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(User $user, int $limit = 20): array
    {
        $notificationsKey = "user_notifications:{$user->id}";
        $notifications = Cache::get($notificationsKey, []);
        
        return array_slice($notifications, -$limit);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(User $user, string $notificationId): bool
    {
        try {
            $notificationKey = "notification:{$user->id}:{$notificationId}";
            $notification = Cache::get($notificationKey);
            
            if ($notification) {
                $notification['read_at'] = now()->toISOString();
                Cache::put($notificationKey, $notification, self::NOTIFICATION_TTL);
                
                // Update in user's notification list
                $this->updateUserNotification($user, $notificationId, ['read_at' => $notification['read_at']]);
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'user_id' => $user->id,
                'notification_id' => $notificationId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Update user notification
     */
    private function updateUserNotification(User $user, string $notificationId, array $updates): void
    {
        $notificationsKey = "user_notifications:{$user->id}";
        $notifications = Cache::get($notificationsKey, []);
        
        foreach ($notifications as &$notification) {
            if ($notification['id'] === $notificationId) {
                $notification = array_merge($notification, $updates);
                break;
            }
        }
        
        Cache::put($notificationsKey, $notifications, self::NOTIFICATION_TTL);
    }

    /**
     * Get channel history
     */
    public function getChannelHistory(string $channel, int $limit = 50): array
    {
        $channelKey = "channel:{$channel}:messages";
        $messages = Cache::get($channelKey, []);
        
        return array_slice($messages, -$limit);
    }

    /**
     * Get user subscriptions
     */
    public function getUserSubscriptions(User $user): array
    {
        $subscriptionsKey = "user_subscriptions:{$user->id}";
        return Cache::get($subscriptionsKey, []);
    }

    /**
     * Send system notification
     */
    public function sendSystemNotification(array $notification, array $options = []): array
    {
        try {
            $users = User::all();
            return $this->sendToUsers($users->toArray(), $notification, $options);
            
        } catch (\Exception $e) {
            Log::error('Failed to send system notification', [
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);
            
            return [];
        }
    }

    /**
     * Send project notification
     */
    public function sendProjectNotification(int $projectId, array $notification, array $options = []): array
    {
        try {
            // Get project members
            $project = \App\Models\Project::find($projectId);
            if (!$project) {
                return [];
            }
            
            $users = User::where('tenant_id', $project->tenant_id)->get();
            return $this->sendToUsers($users->toArray(), $notification, $options);
            
        } catch (\Exception $e) {
            Log::error('Failed to send project notification', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);
            
            return [];
        }
    }

    /**
     * Send task notification
     */
    public function sendTaskNotification(int $taskId, array $notification, array $options = []): array
    {
        try {
            $task = \App\Models\Task::find($taskId);
            if (!$task) {
                return [];
            }
            
            $users = [];
            
            // Add task assignee
            if ($task->assigned_to) {
                $assignee = User::find($task->assigned_to);
                if ($assignee) {
                    $users[] = $assignee;
                }
            }
            
            // Add project members
            if ($task->project_id) {
                $project = \App\Models\Project::find($task->project_id);
                if ($project) {
                    $projectUsers = User::where('tenant_id', $project->tenant_id)->get();
                    $users = array_merge($users, $projectUsers->toArray());
                }
            }
            
            // Remove duplicates
            $users = array_unique($users, SORT_REGULAR);
            
            return $this->sendToUsers($users, $notification, $options);
            
        } catch (\Exception $e) {
            Log::error('Failed to send task notification', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);
            
            return [];
        }
    }
}