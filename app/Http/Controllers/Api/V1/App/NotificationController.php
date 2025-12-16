<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * NotificationController - Round 251: Notifications Center Phase 1
 * 
 * API endpoints for notifications:
 * - GET /api/v1/app/notifications (list with filters and pagination)
 * - PUT /api/v1/app/notifications/{id}/read (mark single as read)
 * - PUT /api/v1/app/notifications/read-all (mark all as read)
 */
class NotificationController extends BaseApiV1Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}
    
    /**
     * Get notifications for current user
     * 
     * Query params:
     * - page: Page number (default: 1)
     * - per_page: Items per page (default: 20)
     * - is_read: Filter by read status (true/false)
     * - module: Filter by module (tasks/documents/cost/rbac/system)
     * - search: Search in title or message
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $tenantId = $this->getTenantId();
            
            $filters = $request->only(['is_read', 'module', 'search']);
            $perPage = (int) $request->get('per_page', 20);
            $page = (int) $request->get('page', 1);
            
            $notifications = $this->notificationService->getNotificationsForUser(
                $user->id,
                $tenantId,
                $filters,
                $perPage,
                $page
            );
            
            // Get unread count
            $unreadCount = $this->notificationService->getUnreadCount($user->id, $tenantId);
            
            return $this->paginatedResponse(
                $notifications->items(),
                [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                    'from' => $notifications->firstItem(),
                    'to' => $notifications->lastItem(),
                    'unread_count' => $unreadCount,
                ],
                'Notifications retrieved successfully',
                [
                    'first' => $notifications->url(1),
                    'last' => $notifications->url($notifications->lastPage()),
                    'prev' => $notifications->previousPageUrl(),
                    'next' => $notifications->nextPageUrl(),
                ]
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index']);
            return $this->errorResponse('Failed to retrieve notifications', 500);
        }
    }
    
    /**
     * Mark a notification as read
     * 
     * @param Request $request
     * @param string $id Notification ID
     * @return JsonResponse
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $tenantId = $this->getTenantId();
            
            $this->notificationService->markAsRead($id, $user->id, $tenantId);
            
            return $this->successResponse(
                ['id' => $id, 'is_read' => true],
                'Notification marked as read'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Notification not found', 404);
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'markRead', 'notification_id' => $id]);
            return $this->errorResponse('Failed to mark notification as read', 500);
        }
    }
    
    /**
     * Mark all notifications as read for current user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $tenantId = $this->getTenantId();
            
            $count = $this->notificationService->markAllAsRead($user->id, $tenantId);
            
            return $this->successResponse(
                ['count' => $count],
                "Marked {$count} notifications as read"
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'markAllRead']);
            return $this->errorResponse('Failed to mark all notifications as read', 500);
        }
    }
}
