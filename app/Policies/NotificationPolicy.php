<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any notifications.
     */
    public function viewAny(User $user)
    {
        return true; // All authenticated users can view their notifications
    }

    /**
     * Determine whether the user can view the notification.
     */
    public function view(User $user, Notification $notification)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notification->tenant_id) {
            return false;
        }

        // Users can only view their own notifications
        return $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can create notifications.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can update the notification.
     */
    public function update(User $user, Notification $notification)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notification->tenant_id) {
            return false;
        }

        // Users can only update their own notifications (mark as read/unread)
        return $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can delete the notification.
     */
    public function delete(User $user, Notification $notification)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notification->tenant_id) {
            return false;
        }

        // Users can only delete their own notifications
        return $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can mark notification as read.
     */
    public function markAsRead(User $user, Notification $notification)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notification->tenant_id) {
            return false;
        }

        // Users can only mark their own notifications as read
        return $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can mark notification as unread.
     */
    public function markAsUnread(User $user, Notification $notification)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notification->tenant_id) {
            return false;
        }

        // Users can only mark their own notifications as unread
        return $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can send notifications to others.
     */
    public function send(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can manage notification settings.
     */
    public function manageSettings(User $user)
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
