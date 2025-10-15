<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Task;
use App\Models\Quote;
use App\Models\Client;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send task completed notification
     */
    public function sendTaskCompletedNotification(Task $task, User $user): void
    {
        try {
            // Create in-app notification
            $this->createInAppNotification(
                $user,
                'task_completed',
                __('notifications.task_completed_title'),
                __('notifications.task_completed_message', [
                    'task_title' => $task->title,
                    'project_name' => $task->project->name
                ]),
                'medium',
                [
                    'task_id' => $task->id,
                    'project_id' => $task->project->id,
                    'completed_by' => $task->completedBy->name
                ]
            );

            // Send email notification
            if ($user->email_notifications_enabled) {
                $this->sendEmailNotification(
                    $user,
                    'emails.task-completed',
                    [
                        'user' => $user,
                        'task' => $task
                    ]
                );
            }

            Log::info('Task completed notification sent', [
                'task_id' => $task->id,
                'user_id' => $user->id,
                'tenant_id' => $task->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send task completed notification', [
                'task_id' => $task->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send quote sent notification
     */
    public function sendQuoteSentNotification(Quote $quote, Client $client): void
    {
        try {
            // Get all users who should be notified about quotes
            $users = User::where('tenant_id', $quote->tenant_id)
                ->where('quote_notifications_enabled', true)
                ->get();

            foreach ($users as $user) {
                // Create in-app notification
                $this->createInAppNotification(
                    $user,
                    'quote_sent',
                    __('notifications.quote_sent_title'),
                    __('notifications.quote_sent_message', [
                        'quote_number' => $quote->quote_number,
                        'client_name' => $client->name
                    ]),
                    'high',
                    [
                        'quote_id' => $quote->id,
                        'client_id' => $client->id,
                        'total_amount' => $quote->total_amount
                    ]
                );

                // Send email notification
                if ($user->email_notifications_enabled) {
                    $this->sendEmailNotification(
                        $user,
                        'emails.quote-sent',
                        [
                            'user' => $user,
                            'quote' => $quote,
                            'client' => $client
                        ]
                    );
                }
            }

            Log::info('Quote sent notification sent', [
                'quote_id' => $quote->id,
                'client_id' => $client->id,
                'tenant_id' => $quote->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send quote sent notification', [
                'quote_id' => $quote->id,
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send client created notification
     */
    public function sendClientCreatedNotification(Client $client, User $createdBy): void
    {
        try {
            // Get all users who should be notified about new clients
            $users = User::where('tenant_id', $client->tenant_id)
                ->where('client_notifications_enabled', true)
                ->get();

            foreach ($users as $user) {
                // Create in-app notification
                $this->createInAppNotification(
                    $user,
                    'client_created',
                    __('notifications.client_created_title'),
                    __('notifications.client_created_message', [
                        'client_name' => $client->name,
                        'created_by' => $createdBy->name
                    ]),
                    'medium',
                    [
                        'client_id' => $client->id,
                        'created_by_id' => $createdBy->id,
                        'client_type' => $client->type
                    ]
                );

                // Send email notification
                if ($user->email_notifications_enabled) {
                    $this->sendEmailNotification(
                        $user,
                        'emails.client-created',
                        [
                            'user' => $user,
                            'client' => $client,
                            'createdBy' => $createdBy
                        ]
                    );
                }
            }

            Log::info('Client created notification sent', [
                'client_id' => $client->id,
                'created_by_id' => $createdBy->id,
                'tenant_id' => $client->tenant_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send client created notification', [
                'client_id' => $client->id,
                'created_by_id' => $createdBy->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create in-app notification
     */
    private function createInAppNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        string $priority = 'medium',
        array $data = []
    ): void {
        Notification::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'data' => $data,
            'read_at' => null,
        ]);
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(User $user, string $template, array $data): void
    {
        try {
            Mail::send($template, $data, function ($message) use ($user, $template) {
                $message->to($user->email, $user->name)
                    ->subject(__('notifications.email_subject', ['type' => $template]));
            });
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'user_id' => $user->id,
                'template' => $template,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->first();

            if ($notification && !$notification->read_at) {
                $notification->update(['read_at' => now()]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): int
    {
        try {
            return Notification::where('user_id', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get unread notifications count for user
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            return Notification::where('user_id', $userId)
                ->whereNull('read_at')
                ->count();
        } catch (\Exception $e) {
            Log::error('Failed to get unread notifications count', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}