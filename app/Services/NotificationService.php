<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use App\Models\NotificationRule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class NotificationService
{
    protected static array $notifications = [];
    
    protected $emailService;
    protected $webSocketService;

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

    public function __construct(EmailService $emailService, WebSocketService $webSocketService)
    {
        $this->emailService = $emailService;
        $this->webSocketService = $webSocketService;
    }

    /**
     * Send notification to user
     */
    public function sendNotification(User $user, array $data): bool
    {
        try {
            // Create in-app notification
            $notification = Notification::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'type' => $data['type'] ?? 'info',
                'priority' => $data['priority'] ?? 'normal',
                'title' => $data['title'],
                'body' => $data['message'],
                'link_url' => $data['link_url'] ?? null,
                'channel' => 'inapp',
                'data' => $data['data'] ?? [],
                'project_id' => $data['project_id'] ?? null,
            ]);

            // Send real-time notification via WebSocket
            $this->webSocketService->broadcast('notifications', 'new_notification', [
                'notification' => $notification,
                'user_id' => $user->id
            ], $user->tenant_id);

            // Send email notification if enabled
            if ($data['send_email'] ?? false) {
                $this->sendEmailNotification($user, $notification);
            }

            Log::info('Notification sent successfully', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'type' => $data['type']
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return false;
        }
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(User $user, Notification $notification): void
    {
        try {
            $this->emailService->sendNotificationEmail($user, $notification);
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
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