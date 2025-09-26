<?php

namespace App\Repositories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class NotificationRepository
{
    protected $model;

    public function __construct(Notification $model)
    {
        $this->model = $model;
    }

    /**
     * Get all notifications with pagination.
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['read'])) {
            $query->where('read', $filters['read']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('message', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->with(['user', 'tenant'])->paginate($perPage);
    }

    /**
     * Get notification by ID.
     */
    public function getById(int $id): ?Notification
    {
        return $this->model->with(['user', 'tenant'])->find($id);
    }

    /**
     * Get notifications by user ID.
     */
    public function getByUserId(int $userId): Collection
    {
        return $this->model->where('user_id', $userId)
                          ->with(['user', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Get notifications by tenant ID.
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['user', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Get notifications by type.
     */
    public function getByType(string $type): Collection
    {
        return $this->model->where('type', $type)
                          ->with(['user', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Get notifications by priority.
     */
    public function getByPriority(string $priority): Collection
    {
        return $this->model->where('priority', $priority)
                          ->with(['user', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Create a new notification.
     */
    public function create(array $data): Notification
    {
        $notification = $this->model->create($data);

        Log::info('Notification created', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'priority' => $notification->priority
        ]);

        return $notification->load(['user', 'tenant']);
    }

    /**
     * Update notification.
     */
    public function update(int $id, array $data): ?Notification
    {
        $notification = $this->model->find($id);

        if (!$notification) {
            return null;
        }

        $notification->update($data);

        Log::info('Notification updated', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id
        ]);

        return $notification->load(['user', 'tenant']);
    }

    /**
     * Delete notification.
     */
    public function delete(int $id): bool
    {
        $notification = $this->model->find($id);

        if (!$notification) {
            return false;
        }

        $notification->delete();

        Log::info('Notification deleted', [
            'notification_id' => $id,
            'user_id' => $notification->user_id
        ]);

        return true;
    }

    /**
     * Soft delete notification.
     */
    public function softDelete(int $id): bool
    {
        $notification = $this->model->find($id);

        if (!$notification) {
            return false;
        }

        $notification->delete();

        Log::info('Notification soft deleted', [
            'notification_id' => $id,
            'user_id' => $notification->user_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted notification.
     */
    public function restore(int $id): bool
    {
        $notification = $this->model->withTrashed()->find($id);

        if (!$notification) {
            return false;
        }

        $notification->restore();

        Log::info('Notification restored', [
            'notification_id' => $id,
            'user_id' => $notification->user_id
        ]);

        return true;
    }

    /**
     * Get unread notifications.
     */
    public function getUnread(int $userId = null): Collection
    {
        $query = $this->model->where('read', false);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->with(['user', 'tenant'])
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    /**
     * Get read notifications.
     */
    public function getRead(int $userId = null): Collection
    {
        $query = $this->model->where('read', true);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->with(['user', 'tenant'])
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    /**
     * Get critical notifications.
     */
    public function getCritical(): Collection
    {
        return $this->model->where('priority', 'critical')
                          ->with(['user', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Get recent notifications.
     */
    public function getRecent(int $days = 7): Collection
    {
        return $this->model->where('created_at', '>=', now()->subDays($days))
                          ->with(['user', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $id): bool
    {
        $notification = $this->model->find($id);

        if (!$notification) {
            return false;
        }

        $notification->update([
            'read' => true,
            'read_at' => now()
        ]);

        Log::info('Notification marked as read', [
            'notification_id' => $id,
            'user_id' => $notification->user_id
        ]);

        return true;
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(int $id): bool
    {
        $notification = $this->model->find($id);

        if (!$notification) {
            return false;
        }

        $notification->update([
            'read' => false,
            'read_at' => null
        ]);

        Log::info('Notification marked as unread', [
            'notification_id' => $id,
            'user_id' => $notification->user_id
        ]);

        return true;
    }

    /**
     * Mark all notifications as read for user.
     */
    public function markAllAsRead(int $userId): int
    {
        $updated = $this->model->where('user_id', $userId)
                              ->where('read', false)
                              ->update([
                                  'read' => true,
                                  'read_at' => now()
                              ]);

        Log::info('All notifications marked as read', [
            'user_id' => $userId,
            'count' => $updated
        ]);

        return $updated;
    }

    /**
     * Mark all notifications as unread for user.
     */
    public function markAllAsUnread(int $userId): int
    {
        $updated = $this->model->where('user_id', $userId)
                              ->where('read', true)
                              ->update([
                                  'read' => false,
                                  'read_at' => null
                              ]);

        Log::info('All notifications marked as unread', [
            'user_id' => $userId,
            'count' => $updated
        ]);

        return $updated;
    }

    /**
     * Get notification statistics.
     */
    public function getStatistics(int $userId = null): array
    {
        $query = $this->model->query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return [
            'total_notifications' => $query->count(),
            'unread_notifications' => $query->where('read', false)->count(),
            'read_notifications' => $query->where('read', true)->count(),
            'critical_notifications' => $query->where('priority', 'critical')->count(),
            'normal_notifications' => $query->where('priority', 'normal')->count(),
            'low_notifications' => $query->where('priority', 'low')->count(),
            'recent_notifications' => $query->where('created_at', '>=', now()->subDays(7))->count(),
            'email_sent_notifications' => $query->where('email_sent', true)->count(),
            'email_failed_notifications' => $query->where('email_failed', true)->count()
        ];
    }

    /**
     * Search notifications.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('title', 'like', '%' . $term . '%')
              ->orWhere('message', 'like', '%' . $term . '%');
        })->with(['user', 'tenant'])
          ->orderBy('created_at', 'desc')
          ->limit($limit)
          ->get();
    }

    /**
     * Get notifications by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['user', 'tenant'])
                          ->get();
    }

    /**
     * Bulk update notifications.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('Notifications bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete notifications.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('Notifications bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Create bulk notifications.
     */
    public function createBulk(array $notifications): int
    {
        $created = $this->model->insert($notifications);

        Log::info('Bulk notifications created', [
            'count' => count($notifications)
        ]);

        return $created;
    }

    /**
     * Get notification count for user.
     */
    public function getCount(int $userId): int
    {
        return $this->model->where('user_id', $userId)->count();
    }

    /**
     * Get unread notification count for user.
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->model->where('user_id', $userId)
                          ->where('read', false)
                          ->count();
    }

    /**
     * Get notification by type and user.
     */
    public function getByTypeAndUser(string $type, int $userId): Collection
    {
        return $this->model->where('type', $type)
                          ->where('user_id', $userId)
                          ->with(['user', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Get notification by priority and user.
     */
    public function getByPriorityAndUser(string $priority, int $userId): Collection
    {
        return $this->model->where('priority', $priority)
                          ->where('user_id', $userId)
                          ->with(['user', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Clean up old notifications.
     */
    public function cleanup(int $days = 30): int
    {
        $deleted = $this->model->where('created_at', '<', now()->subDays($days))
                              ->where('read', true)
                              ->delete();

        Log::info('Old notifications cleaned up', [
            'count' => $deleted,
            'days' => $days
        ]);

        return $deleted;
    }

    /**
     * Get notification trends.
     */
    public function getTrends(int $days = 30): array
    {
        $trends = [];

        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $count = $this->model->whereDate('created_at', $date)->count();
            $trends[] = [
                'date' => $date,
                'count' => $count
            ];
        }

        return $trends;
    }

    /**
     * Get notification by external ID.
     */
    public function getByExternalId(string $externalId): ?Notification
    {
        return $this->model->where('external_id', $externalId)->first();
    }

    /**
     * Update notification status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $notification = $this->model->find($id);

        if (!$notification) {
            return false;
        }

        $notification->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('Notification status updated', [
            'notification_id' => $id,
            'status' => $status
        ]);

        return true;
    }
}
