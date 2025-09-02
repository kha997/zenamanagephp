<?php declare(strict_types=1);

/**
 * Simple WebSocket Server for ZENA Project
 * Không cần dependencies phức tạp, chỉ sử dụng PHP native
 */
class SimpleWebSocketServer
{
    private $host = '127.0.0.1';
    private $port = 8080;
    private $socket;
    private $clients = [];
    private $userConnections = [];
    private $projectChannels = [];

    public function __construct()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $this->host, $this->port);
        socket_listen($this->socket, 20);
        
        echo "ZENA WebSocket Server started on {$this->host}:{$this->port}\n";
        echo "Connect to: ws://{$this->host}:{$this->port}\n";
        echo "Press Ctrl+C to stop\n";
    }

    public function run()
    {
        while (true) {
            $read = array_merge([$this->socket], $this->clients);
            $write = null;
            $except = null;
            
            if (socket_select($read, $write, $except, 0, 10000) < 1) {
                continue;
            }
            
            // Handle new connections
            if (in_array($this->socket, $read)) {
                $newSocket = socket_accept($this->socket);
                $this->clients[] = $newSocket;
                $this->performHandshake($newSocket);
                echo "New connection established\n";
                
                $key = array_search($this->socket, $read);
                unset($read[$key]);
            }
            
            // Handle client messages
            foreach ($read as $client) {
                $data = socket_read($client, 1024);
                
                if ($data === false || $data === '') {
                    $this->disconnect($client);
                    continue;
                }
                
                $decodedData = $this->decode($data);
                if ($decodedData) {
                    $this->handleMessage($client, $decodedData);
                }
            }
        }
    }

    private function performHandshake($client)
    {
        $request = socket_read($client, 5000);
        
        preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
        $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        $headers = "HTTP/1.1 101 Switching Protocols\r\n";
        $headers .= "Upgrade: websocket\r\n";
        $headers .= "Connection: Upgrade\r\n";
        $headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
        
        socket_write($client, $headers, strlen($headers));
        
        // Send welcome message
        $this->send($client, json_encode([
            'type' => 'connection',
            'message' => 'Connected to ZENA WebSocket Server',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    private function decode($data)
    {
        $length = ord($data[1]) & 127;
        
        if ($length == 126) {
            $masks = substr($data, 4, 4);
            $data = substr($data, 8);
        } elseif ($length == 127) {
            $masks = substr($data, 10, 4);
            $data = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $data = substr($data, 6);
        }
        
        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        
        return $text;
    }

    private function encode($text)
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);
        
        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } elseif ($length >= 65536) {
            $header = pack('CCNN', $b1, 127, $length);
        }
        
        return $header . $text;
    }

    private function send($client, $message)
    {
        socket_write($client, $this->encode($message), strlen($this->encode($message)));
    }

    private function handleMessage($client, $message)
    {
        echo "Received: $message\n";
        
        try {
            $data = json_decode($message, true);
            
            if (!$data) {
                $this->send($client, json_encode(['error' => 'Invalid JSON format']));
                return;
            }

            switch ($data['type'] ?? '') {
                case 'auth':
                    $this->handleAuth($client, $data);
                    break;
                    
                case 'join_project':
                    $this->handleJoinProject($client, $data);
                    break;
                    
                case 'notification':
                    $this->handleNotification($client, $data);
                    break;
                    
                case 'ping':
                    $this->send($client, json_encode(['type' => 'pong', 'timestamp' => time()]));
                    break;
                    
                default:
                    $this->send($client, json_encode(['error' => 'Unknown message type']));
            }
        } catch (Exception $e) {
            echo "Error processing message: " . $e->getMessage() . "\n";
            $this->send($client, json_encode(['error' => 'Server error processing message']));
        }
    }

    private function handleAuth($client, array $data)
    {
        $userId = $data['user_id'] ?? null;
        $token = $data['token'] ?? null;
        
        if ($userId && $token) {
            $this->userConnections[$userId] = $client;
            
            $this->send($client, json_encode([
                'type' => 'auth_success',
                'user_id' => $userId,
                'message' => 'Authentication successful'
            ]));
            
            echo "User {$userId} authenticated\n";
        } else {
            $this->send($client, json_encode([
                'type' => 'auth_error',
                'message' => 'Invalid authentication data'
            ]));
        }
    }

    private function handleJoinProject($client, array $data)
    {
        $projectId = $data['project_id'] ?? null;
        
        if ($projectId) {
            if (!isset($this->projectChannels[$projectId])) {
                $this->projectChannels[$projectId] = [];
            }
            
            $this->projectChannels[$projectId][] = $client;
            
            $this->send($client, json_encode([
                'type' => 'project_joined',
                'project_id' => $projectId,
                'message' => "Joined project channel {$projectId}"
            ]));
            
            echo "Client joined project {$projectId}\n";
        }
    }

    private function handleNotification($client, array $data)
    {
        $targetType = $data['target_type'] ?? 'user';
        $targetId = $data['target_id'] ?? null;
        $notification = $data['notification'] ?? [];
        
        if ($targetType === 'user' && isset($this->userConnections[$targetId])) {
            $this->send($this->userConnections[$targetId], json_encode([
                'type' => 'notification',
                'data' => $notification,
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            
            echo "Notification sent to user {$targetId}\n";
        } elseif ($targetType === 'project' && isset($this->projectChannels[$targetId])) {
            foreach ($this->projectChannels[$targetId] as $conn) {
                $this->send($conn, json_encode([
                    'type' => 'project_notification',
                    'project_id' => $targetId,
                    'data' => $notification,
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            
            echo "Notification broadcast to project {$targetId}\n";
        }
        
        $this->send($client, json_encode([
            'type' => 'notification_sent',
            'message' => 'Notification delivered successfully'
        ]));
    }

    private function disconnect($client)
    {
        $key = array_search($client, $this->clients);
        if ($key !== false) {
            unset($this->clients[$key]);
        }
        
        // Remove from user connections
        foreach ($this->userConnections as $userId => $conn) {
            if ($conn === $client) {
                unset($this->userConnections[$userId]);
                echo "User {$userId} disconnected\n";
                break;
            }
        }
        
        // Remove from project channels
        foreach ($this->projectChannels as $projectId => $connections) {
            $this->projectChannels[$projectId] = array_filter(
                $connections,
                function($conn) use ($client) { return $conn !== $client; }
            );
        }
        
        socket_close($client);
        echo "Client disconnected\n";
    }

    public function __destruct()
    {
        socket_close($this->socket);
    }
}

// Start the server
$server = new SimpleWebSocketServer();
$server->run();