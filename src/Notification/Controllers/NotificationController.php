<?php declare(strict_types=1);

namespace Src\Notification\Controllers;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Notification\Models\Notification;
use Src\Notification\Services\NotificationService;
use Src\Notification\Requests\StoreNotificationRequest;
use Src\Notification\Requests\UpdateNotificationRequest;
use Src\Notification\Resources\NotificationResource;
use Src\Notification\Resources\NotificationCollection;
use Src\RBAC\Middleware\RBACMiddleware;
use App\Support\ApiResponse;
use Exception;

/**
 * Controller xử lý các hoạt động CRUD và quản lý thông báo
 * 
 * @package Src\Notification\Controllers
 */
class NotificationController
{
    /**
     * @var NotificationService
     */
    private NotificationService $notificationService;

    /**
     * Constructor - áp dụng RBAC middleware và inject service
     */
    public function __construct(NotificationService $notificationService)
    {
        // Xóa middleware khỏi constructor - sẽ áp dụng trong routes
        // $this->middleware(RBACMiddleware::class);
        $this->notificationService = $notificationService;
    }

    /**
     * Lấy ID người dùng hiện tại một cách an toàn
     *
     * @return int|null
     */
    private function getUserId(): ?int
    {
        try {
            if (AuthHelper::check()) {
                return AuthHelper::id();
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Lấy danh sách notifications của user hiện tại
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $filters = [
                'priority' => $request->get('priority'),
                'channel' => $request->get('channel'),
                'read' => $request->get('read'),
                'limit' => $request->get('limit', 20)
            ];

            $notifications = $this->notificationService->getUserNotifications($userId, $filters);

            return ApiResponse::success(
                new NotificationCollection($notifications)
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể lấy danh sách thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Tạo notification mới (chỉ dành cho admin/system)
     *
     * @param StoreNotificationRequest $request
     * @return JsonResponse
     */
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        try {
            $notification = $this->notificationService->createNotification(
                $request->validated()
            );

            return ApiResponse::success(
                new NotificationResource($notification),
                'Thông báo đã được tạo thành công',
                201
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể tạo thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thông tin chi tiết notification
     *
     * @param string $ulid
     * @return JsonResponse
     */
    public function show(string $ulid): JsonResponse
    {
        try {
            $notification = $this->notificationService->getNotificationById($ulid);
            $currentUserId = $this->getUserId();

            if (!$notification || !$currentUserId || $notification->user_id !== $currentUserId) {
                return ApiResponse::error('Thông báo không tồn tại', 404);
            }

            return ApiResponse::success(
                new NotificationResource($notification)
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể lấy thông tin thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật notification (chỉ admin/system)
     *
     * @param UpdateNotificationRequest $request
     * @param string $ulid
     * @return JsonResponse
     */
    public function update(UpdateNotificationRequest $request, string $ulid): JsonResponse
    {
        try {
            $notification = $this->notificationService->updateNotification(
                $ulid,
                $request->validated()
            );

            return ApiResponse::success(
                new NotificationResource($notification),
                'Thông báo đã được cập nhật thành công'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể cập nhật thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Xóa notification (chỉ admin/system)
     *
     * @param string $ulid
     * @return JsonResponse
     */
    public function destroy(string $ulid): JsonResponse
    {
        try {
            $this->notificationService->deleteNotification($ulid);

            return ApiResponse::success(
                null,
                'Thông báo đã được xóa thành công'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể xóa thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Đánh dấu notification đã đọc
     *
     * @param string $ulid
     * @return JsonResponse
     */
    public function markAsRead(string $ulid): JsonResponse
    {
        try {
            $currentUserId = $this->getUserId();
            if (!$currentUserId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }

            $notification = $this->notificationService->markAsRead($ulid, $currentUserId);

            return ApiResponse::success(
                new NotificationResource($notification),
                'Thông báo đã được đánh dấu đã đọc'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể đánh dấu thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Đánh dấu notification chưa đọc
     *
     * @param string $ulid
     * @return JsonResponse
     */
    public function markAsUnread(string $ulid): JsonResponse
    {
        try {
            $currentUserId = $this->getUserId();
            if (!$currentUserId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }

            $notification = $this->notificationService->markAsUnread($ulid, $currentUserId);

            return ApiResponse::success(
                new NotificationResource($notification),
                'Thông báo đã được đánh dấu chưa đọc'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể đánh dấu thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Đánh dấu tất cả notifications đã đọc
     *
     * @return JsonResponse
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $currentUserId = $this->getUserId();
            if (!$currentUserId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }

            $count = $this->notificationService->markAllAsRead($currentUserId);

            return ApiResponse::success(
                ['marked_count' => $count],
                'Tất cả thông báo đã được đánh dấu đã đọc'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể đánh dấu thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Lấy số lượng notifications chưa đọc
     *
     * @return JsonResponse
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $currentUserId = $this->getUserId();
            if (!$currentUserId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }

            $count = $this->notificationService->getUnreadCount($currentUserId);

            return ApiResponse::success(
                ['unread_count' => $count]
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể lấy số lượng thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Gửi notification test (chỉ admin)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTest(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'channel' => 'required|in:inapp,email,webhook',
                'priority' => 'required|in:critical,normal,low'
            ]);

            $notification = $this->notificationService->sendNotification(
                $request->get('user_id'),
                $request->get('title'),
                $request->get('body'),
                $request->get('channel'),
                $request->get('priority'),
                null, // link_url
                [], // metadata
                'test.notification' // event_key
            );

            return ApiResponse::success(
                new NotificationResource($notification),
                'Thông báo test đã được gửi thành công'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể gửi thông báo test: ' . $e->getMessage());
        }
    }
}