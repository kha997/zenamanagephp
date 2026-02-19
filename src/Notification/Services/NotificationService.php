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
            'type' => $data['type'] ?? Notification::TYPE_SYSTEM,
            'priority' => $data['priority'] ?? 'normal',
            'title' => $data['title'],
            'body' => $data['body'],
            'link_url' => $data['link_url'] ?? null,
            'channel' => $data['channel'] ?? 'inapp',
            'tenant_id' => $data['tenant_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'event_key' => $data['event_key'] ?? null,
            'project_id' => $data['project_id'] ?? null,
        ]);

        // Dispatch event để xử lý gửi thông báo qua các kênh khác nhau
        EventBus::dispatch('Notification.Notification.Created', [
            'entityId' => $notification->id,
            'projectId' => $notification->project_id ?? 'system',
            'actorId' => $notification->user_id,
            'notificationId' => $notification->id,
            'userId' => $notification->user_id,
            'channel' => $notification->channel,
            'priority' => $notification->priority,
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
     * @param string $userId
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserNotifications(
        string $userId,
        array $filters = [],
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Notification::query()
            ->forUser($userId)
            ->with('user')
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
     * @param string $userId
     * @return bool
     */
    public function markAsRead(string $notificationId, string $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();

            // Dispatch event
            EventBus::dispatch('Notification.Notification.Read', [
                'entityId' => $notification->id,
                'projectId' => $notification->project_id ?? 'system',
                'actorId' => $userId,
                'notificationId' => $notification->id,
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
     * @param string $userId
     * @return int Số lượng thông báo đã được đánh dấu
     */
    public function markMultipleAsRead(array $notificationIds, string $userId): int
    {
        $count = Notification::whereIn('id', $notificationIds)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($count > 0) {
            // Dispatch event
            EventBus::dispatch('Notification.Notification.BulkRead', [
                'entityId' => $notificationIds[0] ?? null,
                'projectId' => 'system',
                'actorId' => $userId,
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
     * @param string $userId
     * @return int
     */
    public function markAllAsRead(string $userId): int
    {
        $count = Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($count > 0) {
            // Dispatch event
            EventBus::dispatch('Notification.Notification.AllRead', [
                'entityId' => $userId,
                'projectId' => 'system',
                'actorId' => $userId,
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
     * @param string $userId
     * @return bool
     */
    public function deleteNotification(string $notificationId, string $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->delete();

        // Dispatch event
            EventBus::dispatch('Notification.Notification.Deleted', [
                'entityId' => $notificationId,
                'projectId' => $notification->project_id ?? 'system',
                'actorId' => $userId,
                'notificationId' => $notification->id,
                'userId' => $userId,
                'timestamp' => now()->toISOString()
            ]);

        return true;
    }

    /**
     * Lấy số lượng thông báo chưa đọc của user
     * 
     * @param string $userId
     * @return int
     */
    public function getUnreadCount(string $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Lấy thống kê thông báo theo project
     * 
     * @param string $projectId
     * @return array
     */
    public function getProjectNotificationStats(string $projectId): array
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
     * Tạo thông báo mới (alias cho createNotification)
     * 
     * @param array $data Dữ liệu thông báo
     * @return Notification
     */
    public function create(array $data): Notification
    {
        return $this->createNotification($data);
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
            EventBus::dispatch('Notification.Notification.Cleanup', [
                'entityId' => (string) $cutoffDate->timestamp,
                'projectId' => null,
                'actorId' => 'system',
                'deletedCount' => $count,
                'cutoffDate' => $cutoffDate->toISOString(),
                'timestamp' => now()->toISOString()
            ]);
        }

        return $count;
    }
}
