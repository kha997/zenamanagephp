<?php declare(strict_types=1);

namespace App\WebSocket;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Ratchet\MessageComponentInterface;

/**
 * Dashboard WebSocket Handler
 * 
 * Xử lý WebSocket connections cho Dashboard real-time updates
 */
class DashboardWebSocketHandler implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections;
    protected $dashboardService;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->dashboardService = app(DashboardService::class);
    }

    /**
     * Khi có connection mới
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        
        Log::info('New WebSocket connection', [
            'connection_id' => $conn->resourceId,
            'total_clients' => $this->clients->count()
        ]);
        
        // Gửi welcome message
        $conn->send(json_encode([
            'type' => 'connection',
            'status' => 'connected',
            'connection_id' => $conn->resourceId,
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Khi nhận được message
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['type'])) {
                $this->sendError($from, 'Invalid message format');
                return;
            }

            switch ($data['type']) {
                case 'authenticate':
                    $this->handleAuthentication($from, $data);
                    break;
                    
                case 'subscribe':
                    $this->handleSubscription($from, $data);
                    break;
                    
                case 'unsubscribe':
                    $this->handleUnsubscription($from, $data);
                    break;
                    
                case 'ping':
                    $this->handlePing($from, $data);
                    break;
                    
                default:
                    $this->sendError($from, 'Unknown message type');
            }
            
        } catch (\Exception $e) {
            Log::error('WebSocket message error', [
                'error' => $e->getMessage(),
                'connection_id' => $from->resourceId
            ]);
            
            $this->sendError($from, 'Internal server error');
        }
    }

    /**
     * Khi connection đóng
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        
        // Xóa user connection mapping
        foreach ($this->userConnections as $userId => $connections) {
            $this->userConnections[$userId] = array_filter(
                $connections,
                fn($connection) => $connection !== $conn
            );
            
            if (empty($this->userConnections[$userId])) {
                unset($this->userConnections[$userId]);
            }
        }
        
        Log::info('WebSocket connection closed', [
            'connection_id' => $conn->resourceId,
            'total_clients' => $this->clients->count()
        ]);
    }

    /**
     * Khi có lỗi
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        Log::error('WebSocket error', [
            'connection_id' => $conn->resourceId,
            'error' => $e->getMessage()
        ]);
        
        $conn->close();
    }

    /**
     * Xử lý authentication
     */
    private function handleAuthentication(ConnectionInterface $conn, array $data)
    {
        $token = $data['token'] ?? null;
        
        if (!$token) {
            $this->sendError($conn, 'Authentication token required');
            return;
        }

        try {
            // Verify token và lấy user
            $user = $this->authenticateUser($token);
            
            if (!$user) {
                $this->sendError($conn, 'Invalid authentication token');
                return;
            }

            // Lưu user connection mapping
            if (!isset($this->userConnections[$user->id])) {
                $this->userConnections[$user->id] = [];
            }
            $this->userConnections[$user->id][] = $conn;

            // Gửi authentication success
            $conn->send(json_encode([
                'type' => 'authentication',
                'status' => 'success',
                'user_id' => $user->id,
                'user_name' => $user->name,
                'timestamp' => now()->toISOString()
            ]));

            Log::info('User authenticated via WebSocket', [
                'user_id' => $user->id,
                'connection_id' => $conn->resourceId
            ]);

        } catch (\Exception $e) {
            Log::error('WebSocket authentication error', [
                'error' => $e->getMessage(),
                'connection_id' => $conn->resourceId
            ]);
            
            $this->sendError($conn, 'Authentication failed');
        }
    }

    /**
     * Xử lý subscription
     */
    private function handleSubscription(ConnectionInterface $conn, array $data)
    {
        $channels = $data['channels'] ?? [];
        
        if (empty($channels)) {
            $this->sendError($conn, 'Channels required for subscription');
            return;
        }

        // Lưu subscription info vào connection metadata
        $conn->subscriptions = $channels;
        
        $conn->send(json_encode([
            'type' => 'subscription',
            'status' => 'success',
            'channels' => $channels,
            'timestamp' => now()->toISOString()
        ]));

        Log::info('WebSocket subscription', [
            'connection_id' => $conn->resourceId,
            'channels' => $channels
        ]);
    }

    /**
     * Xử lý unsubscription
     */
    private function handleUnsubscription(ConnectionInterface $conn, array $data)
    {
        $channels = $data['channels'] ?? [];
        
        if (empty($conn->subscriptions)) {
            $this->sendError($conn, 'No active subscriptions');
            return;
        }

        $conn->subscriptions = array_diff($conn->subscriptions, $channels);
        
        $conn->send(json_encode([
            'type' => 'unsubscription',
            'status' => 'success',
            'channels' => $channels,
            'remaining_channels' => $conn->subscriptions,
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Xử lý ping
     */
    private function handlePing(ConnectionInterface $conn, array $data)
    {
        $conn->send(json_encode([
            'type' => 'pong',
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Gửi error message
     */
    private function sendError(ConnectionInterface $conn, string $message)
    {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
            'timestamp' => now()->toISOString()
        ]));
    }

    /**
     * Authenticate user từ token
     */
    private function authenticateUser(string $token): ?User
    {
        try {
            // Parse JWT token hoặc Sanctum token
            $user = Auth::guard('sanctum')->setToken($token)->user();
            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Broadcast message đến tất cả clients
     */
    public function broadcast(array $message)
    {
        $jsonMessage = json_encode($message);
        
        foreach ($this->clients as $client) {
            $client->send($jsonMessage);
        }
        
        Log::info('WebSocket broadcast', [
            'message_type' => $message['type'] ?? 'unknown',
            'recipients' => $this->clients->count()
        ]);
    }

    /**
     * Broadcast message đến user cụ thể
     */
    public function broadcastToUser(string $userId, array $message)
    {
        if (!isset($this->userConnections[$userId])) {
            return;
        }

        $jsonMessage = json_encode($message);
        
        foreach ($this->userConnections[$userId] as $connection) {
            if ($connection->getResourceId() !== null) {
                $connection->send($jsonMessage);
            }
        }
        
        Log::info('WebSocket user broadcast', [
            'user_id' => $userId,
            'message_type' => $message['type'] ?? 'unknown',
            'recipients' => count($this->userConnections[$userId])
        ]);
    }

    /**
     * Broadcast message đến channel cụ thể
     */
    public function broadcastToChannel(string $channel, array $message)
    {
        $jsonMessage = json_encode($message);
        $recipients = 0;
        
        foreach ($this->clients as $client) {
            if (isset($client->subscriptions) && in_array($channel, $client->subscriptions)) {
                $client->send($jsonMessage);
                $recipients++;
            }
        }
        
        Log::info('WebSocket channel broadcast', [
            'channel' => $channel,
            'message_type' => $message['type'] ?? 'unknown',
            'recipients' => $recipients
        ]);
    }

    /**
     * Broadcast dashboard update
     */
    public function broadcastDashboardUpdate(string $userId, string $widgetId, array $data)
    {
        $this->broadcastToUser($userId, [
            'type' => 'dashboard_update',
            'widget_id' => $widgetId,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Broadcast alert
     */
    public function broadcastAlert(string $userId, array $alert)
    {
        $this->broadcastToUser($userId, [
            'type' => 'alert',
            'alert' => $alert,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Broadcast metric update
     */
    public function broadcastMetricUpdate(string $tenantId, string $metricCode, array $data)
    {
        $this->broadcastToChannel("metrics.{$tenantId}", [
            'type' => 'metric_update',
            'metric_code' => $metricCode,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        return [
            'total_connections' => $this->clients->count(),
            'authenticated_users' => count($this->userConnections),
            'user_connections' => array_map('count', $this->userConnections)
        ];
    }
}