<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * NotificationPolicy
 * 
 * Authorization policy for Notification model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class NotificationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any notifications.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the notification.
     */
    public function view(User $user, Notification $notification): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $notification->tenant_id) {
            return false;
        }

        // User can only view their own notifications
        return $notification->user_id === $user->id;
    }

    /**
     * Determine whether the user can create notifications.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the notification.
     */
    public function update(User $user, Notification $notification): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $notification->tenant_id) {
            return false;
        }

        // User can only update their own notifications
        return $notification->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the notification.
     */
    public function delete(User $user, Notification $notification): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $notification->tenant_id) {
            return false;
        }

        // User can only delete their own notifications
        return $notification->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the notification.
     */
    public function restore(User $user, Notification $notification): bool
    {
        return $this->update($user, $notification);
    }

    /**
     * Determine whether the user can permanently delete the notification.
     */
    public function forceDelete(User $user, Notification $notification): bool
    {
        return $this->delete($user, $notification);
    }

    /**
     * Determine whether the user can mark notification as read.
     */
    public function markAsRead(User $user, Notification $notification): bool
    {
        return $this->update($user, $notification);
    }

    /**
     * Determine whether the user can mark notification as unread.
     */
    public function markAsUnread(User $user, Notification $notification): bool
    {
        return $this->update($user, $notification);
    }

    /**
     * Determine whether the user can mark all notifications as read.
     */
    public function markAllAsRead(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can clear old notifications.
     */
    public function clearOld(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can send notifications.
     */
    public function send(User $user): bool
    {
        // Multi-tenant check
        if ($user->tenant_id === null) {
            return false;
        }

        // Users with management roles can send notifications
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager']);
    }
}