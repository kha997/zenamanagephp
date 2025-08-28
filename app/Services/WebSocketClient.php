<?php declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket Client Helper for ZENA Project
 * Gửi notification đến WebSocket server
 */
class WebSocketClient
{
    private string $host;
    private int $port;
    private int $timeout;
    
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 8080,
        int $timeout = 5
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
    }
    
    /**
     * Gửi notification đến user cụ thể
     */
    public function sendToUser(int $userId, array $notification): bool
    {
        return $this->sendMessage([
            'type' => 'notification',
            'target_type' => 'user',
            'target_id' => $userId,
            'notification' => $notification
        ]);
    }
    
    /**
     * Gửi notification đến tất cả user trong project
     */
    public function sendToProject(int $projectId, array $notification): bool
    {
        return $this->sendMessage([
            'type' => 'notification',
            'target_type' => 'project',
            'target_id' => $projectId,
            'notification' => $notification
        ]);
    }
    
    /**
     * Gửi message đến WebSocket server
     */
    private function sendMessage(array $message): bool
    {
        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            
            if (!$socket) {
                throw new Exception('Cannot create socket: ' . socket_strerror(socket_last_error()));
            }
            
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [
                'sec' => $this->timeout,
                'usec' => 0
            ]);
            
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, [
                'sec' => $this->timeout,
                'usec' => 0
            ]);
            
            $result = socket_connect($socket, $this->host, $this->port);
            
            if (!$result) {
                throw new Exception('Cannot connect to WebSocket server: ' . socket_strerror(socket_last_error($socket)));
            }
            
            // Perform WebSocket handshake
            $key = base64_encode(random_bytes(16));
            $headers = "GET / HTTP/1.1\r\n";
            $headers .= "Host: {$this->host}:{$this->port}\r\n";
            $headers .= "Upgrade: websocket\r\n";
            $headers .= "Connection: Upgrade\r\n";
            $headers .= "Sec-WebSocket-Key: {$key}\r\n";
            $headers .= "Sec-WebSocket-Version: 13\r\n";
            $headers .= "\r\n";
            
            socket_write($socket, $headers, strlen($headers));
            
            // Read handshake response
            $response = socket_read($socket, 1024);
            
            if (strpos($response, '101 Switching Protocols') === false) {
                throw new Exception('WebSocket handshake failed');
            }
            
            // Send message
            $encodedMessage = $this->encodeMessage(json_encode($message));
            socket_write($socket, $encodedMessage, strlen($encodedMessage));
            
            // Read response (optional)
            socket_read($socket, 1024);
            
            socket_close($socket);
            
            Log::info('WebSocket message sent successfully', $message);
            return true;
            
        } catch (Exception $e) {
            Log::error('WebSocket client error: ' . $e->getMessage(), [
                'message' => $message,
                'host' => $this->host,
                'port' => $this->port
            ]);
            return false;
        }
    }
    
    /**
     * Encode message for WebSocket protocol
     */
    private function encodeMessage(string $text): string
    {
        $length = strlen($text);
        $firstByte = 0x80 | 0x01; // FIN bit + text frame
        
        if ($length <= 125) {
            $header = pack('CC', $firstByte, $length | 0x80); // Set mask bit
        } elseif ($length <= 65535) {
            $header = pack('CCn', $firstByte, 126 | 0x80, $length);
        } else {
            $header = pack('CCNN', $firstByte, 127 | 0x80, 0, $length);
        }
        
        // Generate mask
        $mask = pack('N', random_int(0, 0xFFFFFFFF));
        
        // Apply mask to payload
        $masked = '';
        for ($i = 0; $i < $length; $i++) {
            $masked .= $text[$i] ^ $mask[$i % 4];
        }
        
        return $header . $mask . $masked;
    }
}