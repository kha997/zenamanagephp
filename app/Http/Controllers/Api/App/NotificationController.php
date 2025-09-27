<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);

            $notifications = $this->notificationService->getUserNotifications($user, $limit, $offset);

            return response()->json([
                'status' => 'success',
                'data' => $notifications,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get notifications', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load notifications',
                'error_id' => 'NOTIFICATIONS_LOAD_ERROR'
            ], 500);
        }
    }

    /**
     * Get unread count
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $user = Auth::user();
            $count = $this->notificationService->getUnreadCount($user);

            return response()->json([
                'status' => 'success',
                'data' => ['count' => $count],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get unread count', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get unread count',
                'error_id' => 'UNREAD_COUNT_ERROR'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->notificationService->markAsRead($notificationId, $user);

            if ($result) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Notification marked as read'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found',
                    'error_id' => 'NOTIFICATION_NOT_FOUND'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'user_id' => Auth::id(),
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark notification as read',
                'error_id' => 'MARK_READ_ERROR'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            $count = $this->notificationService->markAllAsRead($user);

            return response()->json([
                'status' => 'success',
                'message' => "Marked {$count} notifications as read",
                'data' => ['count' => $count]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark all notifications as read',
                'error_id' => 'MARK_ALL_READ_ERROR'
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(string $notificationId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notification = \App\Models\Notification::where('id', $notificationId)
                ->where('user_id', $user->id)
                ->first();

            if ($notification) {
                $notification->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Notification deleted successfully'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found',
                    'error_id' => 'NOTIFICATION_NOT_FOUND'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Failed to delete notification', [
                'user_id' => Auth::id(),
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete notification',
                'error_id' => 'DELETE_NOTIFICATION_ERROR'
            ], 500);
        }
    }

    /**
     * Get notification settings
     */
    public function settings(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get user notification preferences
            $settings = [
                'email_notifications' => $user->preferences['email_notifications'] ?? true,
                'push_notifications' => $user->preferences['push_notifications'] ?? true,
                'in_app_notifications' => $user->preferences['in_app_notifications'] ?? true,
                'notification_frequency' => $user->preferences['notification_frequency'] ?? 'immediate',
                'quiet_hours' => $user->preferences['quiet_hours'] ?? null,
                'notification_types' => $user->preferences['notification_types'] ?? [
                    'project_updates' => true,
                    'task_assignments' => true,
                    'deadline_reminders' => true,
                    'system_alerts' => true,
                    'team_messages' => true
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => $settings,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get notification settings', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load notification settings',
                'error_id' => 'SETTINGS_LOAD_ERROR'
            ], 500);
        }
    }

    /**
     * Update notification settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validated = $request->validate([
                'email_notifications' => 'boolean',
                'push_notifications' => 'boolean',
                'in_app_notifications' => 'boolean',
                'notification_frequency' => 'string|in:immediate,daily,weekly',
                'quiet_hours' => 'nullable|array',
                'notification_types' => 'array'
            ]);

            // Update user preferences
            $preferences = $user->preferences ?? [];
            $preferences = array_merge($preferences, $validated);
            
            $user->update(['preferences' => $preferences]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification settings updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update notification settings', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update notification settings',
                'error_id' => 'SETTINGS_UPDATE_ERROR'
            ], 500);
        }
    }

    /**
     * Send test notification
     */
    public function sendTest(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $result = $this->notificationService->sendNotification($user, [
                'type' => 'info',
                'priority' => 'normal',
                'title' => 'Test Notification',
                'message' => 'This is a test notification to verify your settings are working correctly.',
                'send_email' => false
            ]);

            if ($result) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Test notification sent successfully'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send test notification',
                    'error_id' => 'TEST_NOTIFICATION_ERROR'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send test notification', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test notification',
                'error_id' => 'TEST_NOTIFICATION_ERROR'
            ], 500);
        }
    }
}
