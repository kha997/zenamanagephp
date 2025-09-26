<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseApiController;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Real-time Controller
 * 
 * Handles real-time features including activity feeds, notifications, and WebSocket authentication
 */
class RealTimeController extends BaseApiController
{
    /**
     * Get project activity feed
     */
    public function getProjectActivityFeed(Request $request, string $projectId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);

            // Get recent activities (this would typically come from an activity log table)
            $activities = $this->getProjectActivities($projectId, $limit, $offset);

            return $this->successResponse([
                'project_id' => $projectId,
                'activities' => $activities,
                'has_more' => count($activities) === $limit
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting project activity feed', [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to get activity feed', 500);
        }
    }

    /**
     * Get notification history
     */
    public function getNotificationHistory(Request $request, string $projectId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);

            $notifications = Notification::where('user_id', $user->id)
                ->where('project_id', $projectId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            return $this->successResponse([
                'project_id' => $projectId,
                'notifications' => $notifications,
                'has_more' => $notifications->count() === $limit
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting notification history', [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to get notification history', 500);
        }
    }

    /**
     * Get activity statistics
     */
    public function getActivityStatistics(Request $request, string $projectId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            $stats = [
                'total_tasks' => Task::where('project_id', $projectId)->count(),
                'completed_tasks' => Task::where('project_id', $projectId)
                    ->where('status', 'completed')->count(),
                'active_tasks' => Task::where('project_id', $projectId)
                    ->where('status', 'in_progress')->count(),
                'overdue_tasks' => Task::where('project_id', $projectId)
                    ->where('due_date', '<', now())
                    ->where('status', '!=', 'completed')->count(),
                'recent_activities' => $this->getRecentActivityCount($projectId, 24), // Last 24 hours
                'notifications_sent' => Notification::where('project_id', $projectId)
                    ->where('created_at', '>=', now()->subDay())->count()
            ];

            return $this->successResponse($stats);

        } catch (\Exception $e) {
            Log::error('Error getting activity statistics', [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to get activity statistics', 500);
        }
    }

    /**
     * Get user recent activities
     */
    public function getUserRecentActivities(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $limit = $request->input('limit', 20);
            $hours = $request->input('hours', 24);

            $activities = $this->getUserActivities($user->id, $limit, $hours);

            return $this->successResponse([
                'user_id' => $user->id,
                'activities' => $activities,
                'period_hours' => $hours
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user recent activities', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to get recent activities', 500);
        }
    }

    /**
     * Get user notification preferences
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $preferences = Cache::get("notification_preferences_{$user->id}", [
                'email' => true,
                'push' => true,
                'sms' => false,
                'project_updates' => true,
                'task_assignments' => true,
                'deadline_reminders' => true,
                'system_alerts' => true,
                'frequency' => 'immediate' // immediate, daily, weekly
            ]);

            return $this->successResponse([
                'user_id' => $user->id,
                'preferences' => $preferences
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting notification preferences', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to get notification preferences', 500);
        }
    }

    /**
     * Update user notification preferences
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $request->validate([
                'email' => 'boolean',
                'push' => 'boolean',
                'sms' => 'boolean',
                'project_updates' => 'boolean',
                'task_assignments' => 'boolean',
                'deadline_reminders' => 'boolean',
                'system_alerts' => 'boolean',
                'frequency' => 'in:immediate,daily,weekly'
            ]);

            $preferences = $request->only([
                'email', 'push', 'sms', 'project_updates', 
                'task_assignments', 'deadline_reminders', 'system_alerts', 'frequency'
            ]);

            Cache::put("notification_preferences_{$user->id}", $preferences, 86400); // 24 hours

            return $this->successResponse([
                'user_id' => $user->id,
                'preferences' => $preferences
            ], 'Notification preferences updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating notification preferences', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to update notification preferences', 500);
        }
    }

    /**
     * Send custom notification
     */
    public function sendCustomNotification(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $request->validate([
                'recipient_id' => 'required|string',
                'title' => 'required|string|max:255',
                'message' => 'required|string|max:1000',
                'type' => 'required|in:info,warning,error,success',
                'priority' => 'in:low,medium,high,urgent',
                'project_id' => 'nullable|string'
            ]);

            $notification = Notification::create([
                'user_id' => $request->input('recipient_id'),
                'project_id' => $request->input('project_id'),
                'type' => $request->input('type'),
                'title' => $request->input('title'),
                'message' => $request->input('message'),
                'priority' => $request->input('priority', 'medium'),
                'created_by' => $user->id
            ]);

            // Broadcast the notification
            $this->broadcastNotification($notification);

            return $this->successResponse([
                'notification_id' => $notification->id,
                'recipient_id' => $notification->user_id,
                'title' => $notification->title
            ], 'Custom notification sent successfully');

        } catch (\Exception $e) {
            Log::error('Error sending custom notification', [
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to send notification', 500);
        }
    }

    /**
     * Get WebSocket authentication token
     */
    public function getWebSocketAuth(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Generate a temporary token for WebSocket authentication
            $token = base64_encode(json_encode([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'expires_at' => now()->addMinutes(30)->timestamp,
                'nonce' => uniqid()
            ]));

            Cache::put("websocket_auth_{$token}", $user->id, 1800); // 30 minutes

            return $this->successResponse([
                'token' => $token,
                'expires_in' => 1800, // 30 minutes
                'websocket_url' => config('websocket.url', 'ws://localhost:6001')
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating WebSocket auth token', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to generate WebSocket token', 500);
        }
    }

    /**
     * Get connection status
     */
    public function getConnectionStatus(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $status = [
                'websocket' => $this->checkWebSocketStatus($user->id),
                'sse' => $this->checkSSEStatus($user->id),
                'redis' => $this->checkRedisStatus(),
                'last_seen' => Cache::get("user_last_seen_{$user->id}", null)
            ];

            return $this->successResponse($status);

        } catch (\Exception $e) {
            Log::error('Error getting connection status', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to get connection status', 500);
        }
    }

    /**
     * Get project activities (mock implementation)
     */
    private function getProjectActivities(string $projectId, int $limit, int $offset): array
    {
        // This would typically query an activity log table
        // For now, return mock data
        return [
            [
                'id' => '1',
                'type' => 'task_created',
                'description' => 'New task created: "Design Review"',
                'user' => 'John Doe',
                'timestamp' => now()->subMinutes(5)->toISOString()
            ],
            [
                'id' => '2',
                'type' => 'task_completed',
                'description' => 'Task completed: "Site Survey"',
                'user' => 'Jane Smith',
                'timestamp' => now()->subMinutes(15)->toISOString()
            ]
        ];
    }

    /**
     * Get recent activity count
     */
    private function getRecentActivityCount(string $projectId, int $hours): int
    {
        // Mock implementation
        return rand(5, 25);
    }

    /**
     * Get user activities (mock implementation)
     */
    private function getUserActivities(string $userId, int $limit, int $hours): array
    {
        // Mock implementation
        return [
            [
                'id' => '1',
                'type' => 'login',
                'description' => 'Logged into system',
                'timestamp' => now()->subMinutes(10)->toISOString()
            ],
            [
                'id' => '2',
                'type' => 'task_viewed',
                'description' => 'Viewed task: "Design Review"',
                'timestamp' => now()->subMinutes(20)->toISOString()
            ]
        ];
    }

    /**
     * Broadcast notification
     */
    private function broadcastNotification(Notification $notification): void
    {
        try {
            // Use Redis pub/sub to communicate with WebSocket server
            Cache::store('redis')->publish('websocket_broadcast', json_encode([
                'user_id' => $notification->user_id,
                'message' => [
                    'type' => 'notification',
                    'data' => [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'priority' => $notification->priority,
                        'created_at' => $notification->created_at->toISOString(),
                    ]
                ]
            ]));
        } catch (\Exception $e) {
            Log::error('Error broadcasting notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check WebSocket status
     */
    private function checkWebSocketStatus(string $userId): bool
    {
        // Check if user has active WebSocket connection
        return Cache::has("websocket_connection_{$userId}");
    }

    /**
     * Check SSE status
     */
    private function checkSSEStatus(string $userId): bool
    {
        // Check if user has active SSE connection
        return Cache::has("sse_connection_{$userId}");
    }

    /**
     * Check Redis status
     */
    private function checkRedisStatus(): bool
    {
        try {
            Cache::store('redis')->get('test');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}