<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseApiController;
use App\Models\ZenaNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NotificationController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $query = ZenaNotification::where('user_id', $user->id);

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('read')) {
            $query->where('read_at', $request->input('read') ? '!=' : '=', null);
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successResponse($notifications);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:task_assigned,task_completed,rfi_submitted,rfi_answered,change_request_submitted,change_request_approved,document_uploaded,inspection_scheduled,safety_incident_reported',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'priority' => 'required|in:low,medium,high,urgent',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $notification = ZenaNotification::create([
                'user_id' => $request->input('user_id'),
                'type' => $request->input('type'),
                'title' => $request->input('title'),
                'message' => $request->input('message'),
                'data' => $request->input('data', []),
                'priority' => $request->input('priority'),
                'expires_at' => $request->input('expires_at'),
                'status' => 'unread',
            ]);

            // Broadcast real-time notification
            $this->broadcastNotification($notification);

            return $this->successResponse($notification, 'Notification created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create notification: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $notification = ZenaNotification::where('user_id', $user->id)
            ->find($id);

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        return $this->successResponse($notification);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $notification = ZenaNotification::where('user_id', $user->id)
            ->find($id);

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        try {
            $notification->update([
                'read_at' => now(),
                'status' => 'read'
            ]);

            return $this->successResponse($notification, 'Notification marked as read');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark notification as read: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            ZenaNotification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update([
                    'read_at' => now(),
                    'status' => 'read'
                ]);

            return $this->successResponse(null, 'All notifications marked as read');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark all notifications as read: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $notification = ZenaNotification::where('user_id', $user->id)
            ->find($id);

        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }

        try {
            $notification->delete();

            return $this->successResponse(null, 'Notification deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete notification: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $count = ZenaNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return $this->successResponse(['count' => $count]);
    }

    /**
     * Get notification statistics
     */
    public function getStats(): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $stats = [
            'total' => ZenaNotification::where('user_id', $user->id)->count(),
            'unread' => ZenaNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
            'read' => ZenaNotification::where('user_id', $user->id)->whereNotNull('read_at')->count(),
            'by_type' => ZenaNotification::where('user_id', $user->id)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'by_priority' => ZenaNotification::where('user_id', $user->id)
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority'),
        ];

        return $this->successResponse($stats);
    }

    /**
     * Broadcast notification via real-time channels
     */
    private function broadcastNotification(ZenaNotification $notification): void
    {
        try {
            // Broadcast via WebSocket
            $this->broadcastWebSocket($notification);
            
            // Broadcast via SSE
            $this->broadcastSSE($notification);
            
        } catch (\Exception $e) {
            \Log::error('Error broadcasting notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast via WebSocket
     */
    private function broadcastWebSocket(ZenaNotification $notification): void
    {
        try {
            $message = [
                'type' => 'notification',
                'data' => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'priority' => $notification->priority,
                    'created_at' => $notification->created_at->toISOString(),
                ]
            ];

            // Use Redis pub/sub to communicate with WebSocket server
            \Cache::store('redis')->publish('websocket_broadcast', json_encode([
                'user_id' => $notification->user_id,
                'message' => $message
            ]));
            
        } catch (\Exception $e) {
            \Log::error('Error broadcasting WebSocket notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast via SSE
     */
    private function broadcastSSE(ZenaNotification $notification): void
    {
        try {
            $cacheKey = "sse_notification_{$notification->user_id}_" . time();
            \Cache::put($cacheKey, [
                'type' => 'notification',
                'data' => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'priority' => $notification->priority,
                    'created_at' => $notification->created_at->toISOString(),
                ]
            ], 60);
            
        } catch (\Exception $e) {
            \Log::error('Error broadcasting SSE notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
