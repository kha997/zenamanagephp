<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Stub implementation - return empty notifications
        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => 0,
                'items' => [],
                'total' => 0,
                'last_checked' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Mark notification as read
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        // Stub implementation - always return success
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => [
                'id' => $id,
                'read_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Mark all notifications as read
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        // Stub implementation - always return success
        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'marked_count' => 0,
                'marked_at' => now()->toISOString()
            ]
        ]);
    }
}
