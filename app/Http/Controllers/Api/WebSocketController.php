<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebSocketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * WebSocket Management Controller
 * 
 * Provides endpoints for:
 * - WebSocket connection management
 * - Real-time event broadcasting
 * - User presence tracking
 * - WebSocket statistics
 */
class WebSocketController extends Controller
{
    private WebSocketService $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Get WebSocket connection info
     */
    public function getConnectionInfo(): JsonResponse
    {
        try {
            $info = [
                'websocket_url' => config('websocket.url', 'ws://localhost:6001'),
                'channels' => $this->webSocketService->getChannels(),
                'event_types' => $this->webSocketService->getEventTypes('dashboard'),
                'online_users' => $this->webSocketService->getOnlineUsersCount(),
                'connection_id' => uniqid('ws_conn_', true),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $info,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get WebSocket connection info',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_INFO_ERROR',
            ], 500);
        }
    }

    /**
     * Mark user as online
     */
    public function markOnline(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'activity' => 'sometimes|string',
        ]);

        try {
            $userId = $request->input('user_id');
            $activity = $request->input('activity', 'online');
            $tenantId = $request->header('X-Tenant-ID');
            
            $success = $this->webSocketService->markUserOnline($userId, $tenantId);
            
            if ($activity !== 'online') {
                $this->webSocketService->updateUserActivity($userId, $activity, [], $tenantId);
            }
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'User marked as online',
                    'user_id' => $userId,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to mark user as online',
                    'code' => 'WEBSOCKET_ONLINE_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark user online',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_ONLINE_ERROR',
            ], 500);
        }
    }

    /**
     * Mark user as offline
     */
    public function markOffline(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        try {
            $userId = $request->input('user_id');
            $tenantId = $request->header('X-Tenant-ID');
            
            $success = $this->webSocketService->markUserOffline($userId, $tenantId);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'User marked as offline',
                    'user_id' => $userId,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to mark user as offline',
                    'code' => 'WEBSOCKET_OFFLINE_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark user offline',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_OFFLINE_ERROR',
            ], 500);
        }
    }

    /**
     * Update user activity
     */
    public function updateActivity(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'activity' => 'required|string',
            'metadata' => 'sometimes|array',
        ]);

        try {
            $userId = $request->input('user_id');
            $activity = $request->input('activity');
            $metadata = $request->input('metadata', []);
            $tenantId = $request->header('X-Tenant-ID');
            
            $success = $this->webSocketService->updateUserActivity($userId, $activity, $metadata, $tenantId);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'User activity updated',
                    'user_id' => $userId,
                    'activity' => $activity,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to update user activity',
                    'code' => 'WEBSOCKET_ACTIVITY_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update user activity',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_ACTIVITY_ERROR',
            ], 500);
        }
    }

    /**
     * Broadcast message
     */
    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string',
            'event' => 'required|string',
            'data' => 'required|array',
            'target_users' => 'sometimes|array',
            'target_users.*' => 'integer',
        ]);

        try {
            $channel = $request->input('channel');
            $event = $request->input('event');
            $data = $request->input('data');
            $targetUsers = $request->input('target_users');
            $tenantId = $request->header('X-Tenant-ID');
            
            // Validate event type
            if (!$this->webSocketService->isValidEvent($channel, $event)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid event type for channel',
                    'code' => 'WEBSOCKET_INVALID_EVENT',
                ], 400);
            }
            
            $success = false;
            
            if ($targetUsers && !empty($targetUsers)) {
                // Broadcast to specific users
                $success = $this->webSocketService->broadcastToUsers($targetUsers, $event, $data, $tenantId);
            } else {
                // Broadcast to channel
                $success = $this->webSocketService->broadcast($channel, $event, $data, $tenantId);
            }
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message broadcasted successfully',
                    'channel' => $channel,
                    'event' => $event,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to broadcast message',
                    'code' => 'WEBSOCKET_BROADCAST_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to broadcast message',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_BROADCAST_ERROR',
            ], 500);
        }
    }

    /**
     * Send notification
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'notification' => 'required|array',
            'notification.title' => 'required|string',
            'notification.message' => 'required|string',
            'notification.type' => 'sometimes|string',
        ]);

        try {
            $userId = $request->input('user_id');
            $notification = $request->input('notification');
            $tenantId = $request->header('X-Tenant-ID');
            
            $success = $this->webSocketService->sendNotification($userId, $notification, $tenantId);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'user_id' => $userId,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send notification',
                    'code' => 'WEBSOCKET_NOTIFICATION_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send notification',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_NOTIFICATION_ERROR',
            ], 500);
        }
    }

    /**
     * Get WebSocket statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->webSocketService->getStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get WebSocket statistics',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_STATS_ERROR',
            ], 500);
        }
    }

    /**
     * Get available channels and events
     */
    public function getChannels(): JsonResponse
    {
        try {
            $channels = [];
            
            foreach ($this->webSocketService->getChannels() as $key => $channel) {
                $channels[$key] = [
                    'name' => $channel,
                    'events' => $this->webSocketService->getEventTypes($key),
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $channels,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get channels',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_CHANNELS_ERROR',
            ], 500);
        }
    }

    /**
     * Test WebSocket connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $testData = [
                'message' => 'WebSocket connection test',
                'timestamp' => time(),
                'test_id' => uniqid('test_', true),
            ];
            
            $success = $this->webSocketService->broadcast('system', 'connection_test', $testData);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'WebSocket connection test successful',
                    'test_data' => $testData,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'WebSocket connection test failed',
                    'code' => 'WEBSOCKET_TEST_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'WebSocket connection test failed',
                'message' => $e->getMessage(),
                'code' => 'WEBSOCKET_TEST_ERROR',
            ], 500);
        }
    }
}
