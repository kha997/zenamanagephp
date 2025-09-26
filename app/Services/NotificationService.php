<?php

namespace App\Services;

class NotificationService
{
    protected static array $notifications = [];

    /**
     * Add a notification
     */
    public static function add(string $type, string $title, string $message, bool $dismissible = true): void
    {
        static::$notifications[] = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'dismissible' => $dismissible,
            'id' => uniqid('notif_')
        ];
    }

    /**
     * Add info notification
     */
    public static function info(string $title, string $message, bool $dismissible = true): void
    {
        static::add('info', $title, $message, $dismissible);
    }

    /**
     * Add success notification
     */
    public static function success(string $title, string $message, bool $dismissible = true): void
    {
        static::add('success', $title, $message, $dismissible);
    }

    /**
     * Add warning notification
     */
    public static function warning(string $title, string $message, bool $dismissible = true): void
    {
        static::add('warning', $title, $message, $dismissible);
    }

    /**
     * Add error notification
     */
    public static function error(string $title, string $message, bool $dismissible = true): void
    {
        static::add('error', $title, $message, $dismissible);
    }

    /**
     * Get all notifications
     */
    public static function getAll(): array
    {
        return static::$notifications;
    }

    /**
     * Clear all notifications
     */
    public static function clear(): void
    {
        static::$notifications = [];
    }

    /**
     * Get notifications for specific user context
     */
    public static function getUserNotifications($user = null): array
    {
        $notifications = static::$notifications;

        // Add user-specific notifications
        if ($user) {
            // Example: Add notifications based on user role or status
            if ($user->isSuperAdmin()) {
                static::add('info', 'Admin Panel', 'You have access to all system features');
            }

            // Example: Add notifications based on user activity
            if ($user->last_login_at && $user->last_login_at->diffInHours() > 24) {
                static::add('success', 'Welcome back!', 'You have been away for ' . $user->last_login_at->diffInHours() . ' hours');
            }
        }

        return $notifications;
    }

    /**
     * Add system-wide notifications
     */
    public static function addSystemNotifications(): void
    {
        // System maintenance notifications
        static::add('warning', 'System Maintenance', 'Scheduled maintenance on Sunday 2:00 AM - 4:00 AM');
        
        // Feature announcements
        static::add('info', 'New Feature', 'Quick Actions has been updated with new project templates');
        
        // Tips and hints
        static::add('info', 'Tip', 'Use keyboard shortcuts (Ctrl+N) to create new projects faster');
    }
}