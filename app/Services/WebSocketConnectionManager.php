<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * WebSocket Connection Manager
 * 
 * Features:
 * - Connection tracking
 * - Channel management
 * - Message queuing
 * - Connection health monitoring
 * - Load balancing support
 */
class WebSocketConnectionManager
{
    private const CONNECTION_TTL = 300; // 5 minutes
    private const MESSAGE_QUEUE_TTL = 3600; // 1 hour
    private const HEALTH_CHECK_INTERVAL = 30; // 30 seconds

    /**
     * Register WebSocket connection
     */
    public function registerConnection(User $user, string $connectionId, array $metadata = []): bool
    {
        try {
            $connectionKey = "ws_connection:{$connectionId}";
            $connectionData = [
                'user_id' => $user->id,
                'connection_id' => $connectionId,
                'connected_at' => now()->toISOString(),
                'last_ping' => now()->toISOString(),
                'metadata' => $metadata,
                'status' => 'connected',
            ];
            
            Cache::put($connectionKey, $connectionData, self::CONNECTION_TTL);
            
            // Add to user's active connections
            $this->addUserConnection($user, $connectionId);
            
            // Update presence
            $this->updateUserPresence($user, 'online');
            
            Log::info('WebSocket connection registered', [
                'user_id' => $user->id,
                'connection_id' => $connectionId,
                'metadata' => $metadata,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to register WebSocket connection', [
                'user_id' => $user->id,
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Unregister WebSocket connection
     */
    public function unregisterConnection(string $connectionId): bool
    {
        try {
            $connectionKey = "ws_connection:{$connectionId}";
            $connectionData = Cache::get($connectionKey);
            
            if ($connectionData) {
                $userId = $connectionData['user_id'];
                $user = User::find($userId);
                
                // Remove from user's active connections
                $this->removeUserConnection($user, $connectionId);
                
                // Update presence if no other connections
                $activeConnections = $this->getUserConnections($user);
                if (empty($activeConnections)) {
                    $this->updateUserPresence($user, 'offline');
                }
                
                // Remove connection data
                Cache::forget($connectionKey);
                
                Log::info('WebSocket connection unregistered', [
                    'user_id' => $userId,
                    'connection_id' => $connectionId,
                ]);
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to unregister WebSocket connection', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Update connection ping
     */
    public function updatePing(string $connectionId): bool
    {
        try {
            $connectionKey = "ws_connection:{$connectionId}";
            $connectionData = Cache::get($connectionKey);
            
            if ($connectionData) {
                $connectionData['last_ping'] = now()->toISOString();
                Cache::put($connectionKey, $connectionData, self::CONNECTION_TTL);
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Failed to update connection ping', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Send message to connection
     */
    public function sendToConnection(string $connectionId, array $message): bool
    {
        try {
            $connectionKey = "ws_connection:{$connectionId}";
            $connectionData = Cache::get($connectionKey);
            
            if (!$connectionData) {
                // Connection not found, queue message
                $this->queueMessage($connectionId, $message);
                return false;
            }
            
            // Check if connection is healthy
            if (!$this->isConnectionHealthy($connectionData)) {
                $this->queueMessage($connectionId, $message);
                return false;
            }
            
            // Send message via WebSocket
            $this->broadcastToConnection($connectionId, $message);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send message to connection', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
            
            return false;
        }
    }

    /**
     * Send message to user
     */
    public function sendToUser(User $user, array $message): array
    {
        $results = [];
        $connections = $this->getUserConnections($user);
        
        foreach ($connections as $connectionId) {
            $success = $this->sendToConnection($connectionId, $message);
            $results[$connectionId] = $success;
        }
        
        return $results;
    }

    /**
     * Broadcast to channel
     */
    public function broadcastToChannel(string $channel, array $message): array
    {
        $results = [];
        $subscribers = $this->getChannelSubscribers($channel);
        
        foreach ($subscribers as $connectionId) {
            $success = $this->sendToConnection($connectionId, $message);
            $results[$connectionId] = $success;
        }
        
        return $results;
    }

    /**
     * Subscribe connection to channel
     */
    public function subscribeToChannel(string $connectionId, string $channel): bool
    {
        try {
            $subscriptionKey = "ws_subscription:{$connectionId}:{$channel}";
            $subscriptionData = [
                'connection_id' => $connectionId,
                'channel' => $channel,
                'subscribed_at' => now()->toISOString(),
            ];
            
            Cache::put($subscriptionKey, $subscriptionData, self::CONNECTION_TTL);
            
            // Add to channel subscribers
            $this->addChannelSubscriber($channel, $connectionId);
            
            Log::info('Connection subscribed to channel', [
                'connection_id' => $connectionId,
                'channel' => $channel,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to subscribe connection to channel', [
                'connection_id' => $connectionId,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Unsubscribe connection from channel
     */
    public function unsubscribeFromChannel(string $connectionId, string $channel): bool
    {
        try {
            $subscriptionKey = "ws_subscription:{$connectionId}:{$channel}";
            Cache::forget($subscriptionKey);
            
            // Remove from channel subscribers
            $this->removeChannelSubscriber($channel, $connectionId);
            
            Log::info('Connection unsubscribed from channel', [
                'connection_id' => $connectionId,
                'channel' => $channel,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe connection from channel', [
                'connection_id' => $connectionId,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get connection info
     */
    public function getConnectionInfo(string $connectionId): ?array
    {
        $connectionKey = "ws_connection:{$connectionId}";
        return Cache::get($connectionKey);
    }

    /**
     * Get user connections
     */
    public function getUserConnections(User $user): array
    {
        $connectionsKey = "user_connections:{$user->id}";
        return Cache::get($connectionsKey, []);
    }

    /**
     * Get channel subscribers
     */
    public function getChannelSubscribers(string $channel): array
    {
        $subscribersKey = "channel_subscribers:{$channel}";
        return Cache::get($subscribersKey, []);
    }

    /**
     * Health check connections
     */
    public function healthCheck(): array
    {
        $stats = [
            'total_connections' => 0,
            'healthy_connections' => 0,
            'unhealthy_connections' => 0,
            'queued_messages' => 0,
        ];
        
        try {
            // This would typically use Redis SCAN in production
            // For now, we'll use a simplified approach
            
            $stats['total_connections'] = $this->getTotalConnections();
            $stats['healthy_connections'] = $this->getHealthyConnections();
            $stats['unhealthy_connections'] = $stats['total_connections'] - $stats['healthy_connections'];
            $stats['queued_messages'] = $this->getQueuedMessagesCount();
            
        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $stats;
    }

    /**
     * Clean up stale connections
     */
    public function cleanupStaleConnections(): int
    {
        $cleanedCount = 0;
        
        try {
            // This would typically use Redis SCAN in production
            // For now, we'll use a simplified approach
            
            $allConnections = $this->getAllConnections();
            
            foreach ($allConnections as $connectionId) {
                $connectionData = $this->getConnectionInfo($connectionId);
                
                if ($connectionData && !$this->isConnectionHealthy($connectionData)) {
                    $this->unregisterConnection($connectionId);
                    $cleanedCount++;
                }
            }
            
            Log::info('Cleaned up stale connections', [
                'cleaned_count' => $cleanedCount,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to cleanup stale connections', [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $cleanedCount;
    }

    /**
     * Add user connection
     */
    private function addUserConnection(User $user, string $connectionId): void
    {
        $connectionsKey = "user_connections:{$user->id}";
        $connections = Cache::get($connectionsKey, []);
        
        if (!in_array($connectionId, $connections)) {
            $connections[] = $connectionId;
            Cache::put($connectionsKey, $connections, self::CONNECTION_TTL);
        }
    }

    /**
     * Remove user connection
     */
    private function removeUserConnection(User $user, string $connectionId): void
    {
        $connectionsKey = "user_connections:{$user->id}";
        $connections = Cache::get($connectionsKey, []);
        
        $connections = array_filter($connections, fn($id) => $id !== $connectionId);
        Cache::put($connectionsKey, $connections, self::CONNECTION_TTL);
    }

    /**
     * Add channel subscriber
     */
    private function addChannelSubscriber(string $channel, string $connectionId): void
    {
        $subscribersKey = "channel_subscribers:{$channel}";
        $subscribers = Cache::get($subscribersKey, []);
        
        if (!in_array($connectionId, $subscribers)) {
            $subscribers[] = $connectionId;
            Cache::put($subscribersKey, $subscribers, self::CONNECTION_TTL);
        }
    }

    /**
     * Remove channel subscriber
     */
    private function removeChannelSubscriber(string $channel, string $connectionId): void
    {
        $subscribersKey = "channel_subscribers:{$channel}";
        $subscribers = Cache::get($subscribersKey, []);
        
        $subscribers = array_filter($subscribers, fn($id) => $id !== $connectionId);
        Cache::put($subscribersKey, $subscribers, self::CONNECTION_TTL);
    }

    /**
     * Update user presence
     */
    private function updateUserPresence(User $user, string $status): void
    {
        $presenceKey = "presence:{$user->id}";
        $presenceData = [
            'user_id' => $user->id,
            'status' => $status,
            'last_seen' => now()->toISOString(),
        ];
        
        Cache::put($presenceKey, $presenceData, self::CONNECTION_TTL);
    }

    /**
     * Queue message for connection
     */
    private function queueMessage(string $connectionId, array $message): void
    {
        $queueKey = "ws_queue:{$connectionId}";
        $queue = Cache::get($queueKey, []);
        
        $queue[] = [
            'message' => $message,
            'queued_at' => now()->toISOString(),
        ];
        
        // Keep only last 100 messages
        $queue = array_slice($queue, -100);
        
        Cache::put($queueKey, $queue, self::MESSAGE_QUEUE_TTL);
    }

    /**
     * Get queued messages for connection
     */
    public function getQueuedMessages(string $connectionId): array
    {
        $queueKey = "ws_queue:{$connectionId}";
        $queue = Cache::get($queueKey, []);
        
        // Clear queue after retrieving
        Cache::forget($queueKey);
        
        return $queue;
    }

    /**
     * Check if connection is healthy
     */
    private function isConnectionHealthy(array $connectionData): bool
    {
        $lastPing = Carbon::parse($connectionData['last_ping']);
        $threshold = now()->subSeconds(self::HEALTH_CHECK_INTERVAL * 2);
        
        return $lastPing->isAfter($threshold);
    }

    /**
     * Broadcast to connection (WebSocket implementation)
     */
    private function broadcastToConnection(string $connectionId, array $message): void
    {
        // This would integrate with your WebSocket server (e.g., Pusher, Socket.IO, etc.)
        // For now, we'll just log the message
        
        Log::info('WebSocket message sent', [
            'connection_id' => $connectionId,
            'message' => $message,
        ]);
    }

    /**
     * Get total connections count
     */
    private function getTotalConnections(): int
    {
        // This would typically use Redis SCAN in production
        return 0; // Placeholder
    }

    /**
     * Get healthy connections count
     */
    private function getHealthyConnections(): int
    {
        // This would typically use Redis SCAN in production
        return 0; // Placeholder
    }

    /**
     * Get all connections
     */
    private function getAllConnections(): array
    {
        // This would typically use Redis SCAN in production
        return []; // Placeholder
    }

    /**
     * Get queued messages count
     */
    private function getQueuedMessagesCount(): int
    {
        // This would typically use Redis SCAN in production
        return 0; // Placeholder
    }
}
