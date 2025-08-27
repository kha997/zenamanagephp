<?php declare(strict_types=1);

namespace Src\Notification\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Notification\Models\Notification;
use Src\Foundation\EventBus;
use Carbon\Carbon;

/**
 * Service xử lý business logic cho Notification
 * 
 * Chức năng chính:
 * - Tạo và gửi thông báo
 * - Quản lý trạng thái đọc/chưa đọc
 * - Lọc và phân trang thông báo
 * - Xử lý các kênh thông báo khác nhau
 */
class NotificationService
{
    /**
     * Tạo thông báo mới
     * 
     * @param array $data Dữ liệu thông báo
     * @return Notification
     */
    public function createNotification(array $data): Notification
    {
        $notification = Notification::create([
            'user_id' => $data['user_id'],
            'priority' => $data['priority'] ?? 'normal',
            'title' => $data['title'],
            'body' => $data['body'],
            'link_url' => $data['link_url'] ?? null,
            'channel' => $data['channel'] ?? 'inapp',
            'metadata' => $data['metadata'] ?? null,
            'event_key' => $data['event_key'] ?? null,
            'project_id' => $data['project_id'] ?? null,
        ]);

        // Dispatch event để xử lý gửi thông báo qua các kênh khác nhau
        EventBus::dispatch('Notification.Created', [
            'notificationId' => $notification->ulid,
            'userId' => $notification->user_id,
            'channel' => $notification->channel,
            'priority' => $notification->priority,
            'projectId' => $notification->project_id,
            'timestamp' => now()->toISOString()
        ]);

        return $notification;
    }

    /**
     * Tạo thông báo hàng loạt cho nhiều người dùng
     * 
     * @param array $userIds Danh sách user IDs
     * @param array $notificationData Dữ liệu thông báo
     * @return Collection
     */
    public function createBulkNotifications(array $userIds, array $notificationData): Collection
    {
        $notifications = collect();
        
        foreach ($userIds as $userId) {
            $data = array_merge($notificationData, ['user_id' => $userId]);
            $notifications->push($this->createNotification($data));
        }
        
        return $notifications;
    }

    /**
     * Lấy danh sách thông báo của user với phân trang
     * 
     * @param int $userId
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserNotifications(
        int $userId,
        array $filters = [],
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Notification::query()
            ->forUser($userId)
            ->with(['user', 'project'])
            ->orderBy('created_at', 'desc');

        // Lọc theo trạng thái đọc
        if (isset($filters['is_read'])) {
            if ($filters['is_read']) {
                $query->read();
            } else {
                $query->unread();
            }
        }

        // Lọc theo priority
        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        // Lọc theo project
        if (!empty($filters['project_id'])) {
            $query->forProject($filters['project_id']);
        }

        // Lọc theo kênh
        if (!empty($filters['channel'])) {
            $query->byChannel($filters['channel']);
        }

        // Lọc theo event key
        if (!empty($filters['event_key'])) {
            $query->byEventKey($filters['event_key']);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Đánh dấu thông báo đã đọc
     * 
     * @param string $notificationId
     * @param int $userId
     * @return bool
     */
    public function markAsRead(string $notificationId, int $userId): bool
    {
        $notification = Notification::where('ulid', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();

            // Dispatch event
            EventBus::dispatch('Notification.Read', [
                'notificationId' => $notification->ulid,
                'userId' => $notification->user_id,
                'readAt' => $notification->read_at->toISOString(),
                'timestamp' => now()->toISOString()
            ]);
        }

        return true;
    }

    /**
     * Đánh dấu nhiều thông báo đã đọc
     * 
     * @param array $notificationIds
     * @param int $userId
     * @return int Số lượng thông báo đã được đánh dấu
     */
    public function markMultipleAsRead(array $notificationIds, int $userId): int
    {
        $count = Notification::whereIn('ulid', $notificationIds)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($count > 0) {
            // Dispatch event
            EventBus::dispatch('Notification.BulkRead', [
                'notificationIds' => $notificationIds,
                'userId' => $userId,
                'count' => $count,
                'timestamp' => now()->toISOString()
            ]);
        }

        return $count;
    }

    /**
     * Đánh dấu tất cả thông báo của user đã đọc
     * 
     * @param int $userId
     * @return int
     */
    public function markAllAsRead(int $userId): int
    {
        $count = Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($count > 0) {
            // Dispatch event
            EventBus::dispatch('Notification.AllRead', [
                'userId' => $userId,
                'count' => $count,
                'timestamp' => now()->toISOString()
            ]);
        }

        return $count;
    }

    /**
     * Xóa thông báo
     * 
     * @param string $notificationId
     * @param int $userId
     * @return bool
     */
    public function deleteNotification(string $notificationId, int $userId): bool
    {
        $notification = Notification::where('ulid', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->delete();

        // Dispatch event
        EventBus::dispatch('Notification.Deleted', [
            'notificationId' => $notificationId,
            'userId' => $userId,
            'timestamp' => now()->toISOString()
        ]);

        return true;
    }

    /**
     * Lấy số lượng thông báo chưa đọc của user
     * 
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Lấy thống kê thông báo theo project
     * 
     * @param int $projectId
     * @return array
     */
    public function getProjectNotificationStats(int $projectId): array
    {
        $stats = Notification::where('project_id', $projectId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN priority = "critical" THEN 1 ELSE 0 END) as critical,
                SUM(CASE WHEN priority = "normal" THEN 1 ELSE 0 END) as normal,
                SUM(CASE WHEN priority = "low" THEN 1 ELSE 0 END) as low
            ')
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'unread' => $stats->unread ?? 0,
            'read' => ($stats->total ?? 0) - ($stats->unread ?? 0),
            'by_priority' => [
                'critical' => $stats->critical ?? 0,
                'normal' => $stats->normal ?? 0,
                'low' => $stats->low ?? 0,
            ]
        ];
    }

    /**
     * Xóa thông báo cũ (cleanup)
     * 
     * @param int $daysOld Số ngày cũ
     * @return int Số lượng thông báo đã xóa
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        $count = Notification::where('created_at', '<', $cutoffDate)
            ->whereNotNull('read_at') // Chỉ xóa thông báo đã đọc
            ->delete();

        if ($count > 0) {
            // Dispatch event
            EventBus::dispatch('Notification.Cleanup', [
                'deletedCount' => $count,
                'cutoffDate' => $cutoffDate->toISOString(),
                'timestamp' => now()->toISOString()
            ]);
        }

        return $count;
    }
}