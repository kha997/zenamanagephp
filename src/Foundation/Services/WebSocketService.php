<?php declare(strict_types=1);

namespace Src\Foundation\Services;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Log;

/**
 * Service quản lý WebSocket broadcasting cho real-time notifications
 */
class WebSocketService
{
    private BroadcastManager $broadcastManager;

    public function __construct(BroadcastManager $broadcastManager)
    {
        $this->broadcastManager = $broadcastManager;
    }

    /**
     * Broadcast notification đến user cụ thể
     *
     * @param int $userId ID của user
     * @param array $data Dữ liệu notification
     * @return bool
     */
    public function broadcastToUser(int $userId, array $data): bool
    {
        try {
            $channel = "user.{$userId}";
            
            $this->broadcastManager->connection()->trigger(
                $channel,
                'notification.new',
                $data
            );

            Log::info('WebSocket notification sent', [
                'user_id' => $userId,
                'channel' => $channel,
                'data' => $data
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send WebSocket notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return false;
        }
    }

    /**
     * Broadcast notification đến project team
     *
     * @param int $projectId ID của project
     * @param array $data Dữ liệu notification
     * @return bool
     */
    public function broadcastToProject(int $projectId, array $data): bool
    {
        try {
            $channel = "project.{$projectId}";
            
            $this->broadcastManager->connection()->trigger(
                $channel,
                'project.notification',
                $data
            );

            Log::info('WebSocket project notification sent', [
                'project_id' => $projectId,
                'channel' => $channel,
                'data' => $data
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send WebSocket project notification', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return false;
        }
    }

    /**
     * Broadcast system-wide notification
     *
     * @param array $data Dữ liệu notification
     * @return bool
     */
    public function broadcastSystemWide(array $data): bool
    {
        try {
            $channel = 'system';
            
            $this->broadcastManager->connection()->trigger(
                $channel,
                'system.notification',
                $data
            );

            Log::info('WebSocket system notification sent', [
                'channel' => $channel,
                'data' => $data
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send WebSocket system notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return false;
        }
    }

    /**
     * Kiểm tra trạng thái WebSocket server
     *
     * @return bool
     */
    public function isServerRunning(): bool
    {
        try {
            $host = config('broadcasting.connections.websockets.options.host', '127.0.0.1');
            $port = config('broadcasting.connections.websockets.options.port', 6001);
            
            $connection = @fsockopen($host, $port, $errno, $errstr, 1);
            
            if ($connection) {
                fclose($connection);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to check WebSocket server status', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}