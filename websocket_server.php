<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Simple WebSocket Server for ZENA Project
 * Handles real-time notifications and project updates
 */
class ZenaWebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections = [];
    protected $projectChannels = [];

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        echo "ZENA WebSocket Server initialized\n";
    }

    /**
     * Handle new connection
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
        
        // Send welcome message
        $conn->send(json_encode([
            'type' => 'connection',
            'message' => 'Connected to ZENA WebSocket Server',
            'connection_id' => $conn->resourceId,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    /**
     * Handle incoming messages
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "Message from {$from->resourceId}: {$msg}\n";
        
        try {
            $data = json_decode($msg, true);
            
            if (!$data) {
                $from->send(json_encode(['error' => 'Invalid JSON format']));
                return;
            }

            switch ($data['type'] ?? '') {
                case 'auth':
                    $this->handleAuth($from, $data);
                    break;
                    
                case 'join_project':
                    $this->handleJoinProject($from, $data);
                    break;
                    
                case 'notification':
                    $this->handleNotification($from, $data);
                    break;
                    
                case 'ping':
                    $from->send(json_encode(['type' => 'pong', 'timestamp' => time()]));
                    break;
                    
                default:
                    $from->send(json_encode(['error' => 'Unknown message type']));
            }
        } catch (Exception $e) {
            echo "Error processing message: " . $e->getMessage() . "\n";
            $from->send(json_encode(['error' => 'Server error processing message']));
        }
    }

    /**
     * Handle user authentication
     */
    private function handleAuth(ConnectionInterface $conn, array $data)
    {
        $userId = $data['user_id'] ?? null;
        $token = $data['token'] ?? null;
        
        if ($userId && $token) {
            // In real implementation, validate JWT token here
            $this->userConnections[$userId] = $conn;
            $conn->userId = $userId;
            
            $conn->send(json_encode([
                'type' => 'auth_success',
                'user_id' => $userId,
                'message' => 'Authentication successful'
            ]));
            
            echo "User {$userId} authenticated\n";
        } else {
            $conn->send(json_encode([
                'type' => 'auth_error',
                'message' => 'Invalid authentication data'
            ]));
        }
    }

    /**
     * Handle joining project channel
     */
    private function handleJoinProject(ConnectionInterface $conn, array $data)
    {
        $projectId = $data['project_id'] ?? null;
        
        if ($projectId) {
            if (!isset($this->projectChannels[$projectId])) {
                $this->projectChannels[$projectId] = [];
            }
            
            $this->projectChannels[$projectId][] = $conn;
            $conn->projectId = $projectId;
            
            $conn->send(json_encode([
                'type' => 'project_joined',
                'project_id' => $projectId,
                'message' => "Joined project channel {$projectId}"
            ]));
            
            echo "Connection {$conn->resourceId} joined project {$projectId}\n";
        }
    }

    /**
     * Handle notification broadcasting
     */
    private function handleNotification(ConnectionInterface $from, array $data)
    {
        $targetType = $data['target_type'] ?? 'user'; // 'user' or 'project'
        $targetId = $data['target_id'] ?? null;
        $notification = $data['notification'] ?? [];
        
        if ($targetType === 'user' && isset($this->userConnections[$targetId])) {
            $this->userConnections[$targetId]->send(json_encode([
                'type' => 'notification',
                'data' => $notification,
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            
            echo "Notification sent to user {$targetId}\n";
        } elseif ($targetType === 'project' && isset($this->projectChannels[$targetId])) {
            foreach ($this->projectChannels[$targetId] as $conn) {
                $conn->send(json_encode([
                    'type' => 'project_notification',
                    'project_id' => $targetId,
                    'data' => $notification,
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            
            echo "Notification broadcast to project {$targetId}\n";
        }
        
        $from->send(json_encode([
            'type' => 'notification_sent',
            'message' => 'Notification delivered successfully'
        ]));
    }

    /**
     * Handle connection close
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        
        // Remove from user connections
        if (isset($conn->userId)) {
            unset($this->userConnections[$conn->userId]);
            echo "User {$conn->userId} disconnected\n";
        }
        
        // Remove from project channels
        if (isset($conn->projectId)) {
            $projectId = $conn->projectId;
            if (isset($this->projectChannels[$projectId])) {
                $this->projectChannels[$projectId] = array_filter(
                    $this->projectChannels[$projectId],
                    function($c) use ($conn) { return $c !== $conn; }
                );
            }
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    /**
     * Handle connection error
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start the server
$loop = Loop::get();
$webSock = new SocketServer('0.0.0.0:8080', $loop);
$webServer = new IoServer(
    new HttpServer(
        new WsServer(
            new ZenaWebSocketServer()
        )
    ),
    $webSock
);

echo "ZENA WebSocket Server running on port 8080\n";
echo "Connect to: ws://localhost:8080\n";
echo "Press Ctrl+C to stop\n";

$webServer->run();