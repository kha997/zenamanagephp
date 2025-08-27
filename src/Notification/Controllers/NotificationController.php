<?php declare(strict_types=1);

namespace Src\Notification\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Notification\Models\Notification;
use Src\Notification\Services\NotificationService;
use Src\Notification\Requests\StoreNotificationRequest;
use Src\Notification\Requests\UpdateNotificationRequest;
use Src\Notification\Resources\NotificationResource;
use Src\Notification\Resources\NotificationCollection;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;
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
        $this->middleware(RBACMiddleware::class);
        $this->notificationService = $notificationService;
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
            $userId = auth()->id();
            $filters = [
                'priority' => $request->get('priority'),
                'channel' => $request->get('channel'),
                'read_status' => $request->get('read_status'),
                'project_id' => $request->get('project_id'),
                'event_key' => $request->get('event_key'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to')
            ];
            
            $perPage = $request->get('per_page', 15);
            
            $notifications = $this->notificationService->getUserNotifications(
                $userId,
                $filters,
                $perPage
            );

            return JSendResponse::success(
                new NotificationCollection($notifications)
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách thông báo: ' . $e->getMessage());
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

            return JSendResponse::success(
                new NotificationResource($notification),
                'Thông báo đã được tạo thành công',
                201
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể tạo thông báo: ' . $e->getMessage());
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

            if (!$notification || $notification->user_id !== auth()->id()) {
                return JSendResponse::error('Thông báo không tồn tại', 404);
            }

            return JSendResponse::success(
                new NotificationResource($notification)
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin thông báo: ' . $e->getMessage());
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

            return JSendResponse::success(
                new NotificationResource($notification),
                'Thông báo đã được cập nhật thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể cập nhật thông báo: ' . $e->getMessage());
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
            $notification = Notification::where('ulid', $ulid)->first();

            if (!$notification) {
                return JSendResponse::error('Thông báo không tồn tại', 404);
            }

            $notification->delete();

            return JSendResponse::success(
                null,
                'Thông báo đã được xóa thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể xóa thông báo: ' . $e->getMessage());
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
            $notification = $this->notificationService->markAsRead($ulid, auth()->id());

            return JSendResponse::success(
                new NotificationResource($notification),
                'Thông báo đã được đánh dấu đã đọc'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể đánh dấu thông báo: ' . $e->getMessage());
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
            $notification = $this->notificationService->markAsUnread($ulid, auth()->id());

            return JSendResponse::success(
                new NotificationResource($notification),
                'Thông báo đã được đánh dấu chưa đọc'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể đánh dấu thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Đánh dấu tất cả notifications đã đọc
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');
            $count = $this->notificationService->markAllAsRead(auth()->id(), $projectId);

            return JSendResponse::success(
                ['marked_count' => $count],
                "Đã đánh dấu {$count} thông báo là đã đọc"
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể đánh dấu thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thống kê notifications
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $projectId = $request->get('project_id');
            
            $stats = $this->notificationService->getNotificationStatistics($userId, $projectId);

            return JSendResponse::success($stats);
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy thống kê thông báo: ' . $e->getMessage());
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

            return JSendResponse::success(
                new NotificationResource($notification),
                'Thông báo test đã được gửi thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể gửi thông báo test: ' . $e->getMessage());
        }
    }

    /**
     * Lấy số lượng thông báo chưa đọc
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $projectId = $request->get('project_id');
            
            $count = $this->notificationService->getUnreadCount($userId, $projectId);

            return JSendResponse::success([
                'unread_count' => $count
            ]);
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy số lượng thông báo chưa đọc: ' . $e->getMessage());
        }
    }
}