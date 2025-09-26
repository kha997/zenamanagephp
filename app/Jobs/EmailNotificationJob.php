<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected $notificationId;
    protected $userId;
    protected $emailData;

    /**
     * Create a new job instance.
     */
    public function __construct(int $notificationId, int $userId, array $emailData = [])
    {
        $this->notificationId = $notificationId;
        $this->userId = $userId;
        $this->emailData = $emailData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get notification and user
            $notification = Notification::find($this->notificationId);
            $user = User::find($this->userId);

            if (!$notification || !$user) {
                Log::warning('EmailNotificationJob: Notification or user not found', [
                    'notification_id' => $this->notificationId,
                    'user_id' => $this->userId
                ]);
                return;
            }

            // Check if user has email notifications enabled
            if (!$this->shouldSendEmail($user, $notification)) {
                Log::info('EmailNotificationJob: Email notifications disabled for user', [
                    'user_id' => $this->userId,
                    'notification_id' => $this->notificationId
                ]);
                return;
            }

            // Prepare email data
            $emailData = array_merge([
                'user' => $user,
                'notification' => $notification,
                'subject' => $notification->title,
                'message' => $notification->message,
                'priority' => $notification->priority,
                'type' => $notification->type
            ], $this->emailData);

            // Send email based on notification type
            $this->sendEmailByType($user, $emailData);

            // Mark notification as sent
            $notification->update([
                'email_sent_at' => now(),
                'email_sent' => true
            ]);

            Log::info('EmailNotificationJob: Email sent successfully', [
                'user_id' => $this->userId,
                'notification_id' => $this->notificationId,
                'type' => $notification->type
            ]);

        } catch (\Exception $e) {
            Log::error('EmailNotificationJob: Failed to send email', [
                'error' => $e->getMessage(),
                'notification_id' => $this->notificationId,
                'user_id' => $this->userId
            ]);
            
            throw $e;
        }
    }

    /**
     * Check if email should be sent.
     */
    protected function shouldSendEmail(User $user, Notification $notification): bool
    {
        // Check user email preferences
        $emailPreferences = $user->email_preferences ?? [];
        
        // Check if email notifications are enabled
        if (!($emailPreferences['enabled'] ?? true)) {
            return false;
        }

        // Check priority preferences
        $priority = $notification->priority;
        $allowedPriorities = $emailPreferences['priorities'] ?? ['critical', 'normal'];
        
        if (!in_array($priority, $allowedPriorities)) {
            return false;
        }

        // Check type preferences
        $type = $notification->type;
        $allowedTypes = $emailPreferences['types'] ?? ['task', 'project', 'document', 'system'];
        
        if (!in_array($type, $allowedTypes)) {
            return false;
        }

        return true;
    }

    /**
     * Send email based on notification type.
     */
    protected function sendEmailByType(User $user, array $emailData): void
    {
        $type = $emailData['type'];
        
        switch ($type) {
            case 'task':
                $this->sendTaskNotificationEmail($user, $emailData);
                break;
            case 'project':
                $this->sendProjectNotificationEmail($user, $emailData);
                break;
            case 'document':
                $this->sendDocumentNotificationEmail($user, $emailData);
                break;
            case 'system':
                $this->sendSystemNotificationEmail($user, $emailData);
                break;
            default:
                $this->sendGenericNotificationEmail($user, $emailData);
                break;
        }
    }

    /**
     * Send task notification email.
     */
    protected function sendTaskNotificationEmail(User $user, array $emailData): void
    {
        Mail::send('emails.notifications.task', $emailData, function ($message) use ($user, $emailData) {
            $message->to($user->email, $user->name)
                   ->subject($emailData['subject'])
                   ->priority($this->getEmailPriority($emailData['priority']));
        });
    }

    /**
     * Send project notification email.
     */
    protected function sendProjectNotificationEmail(User $user, array $emailData): void
    {
        Mail::send('emails.notifications.project', $emailData, function ($message) use ($user, $emailData) {
            $message->to($user->email, $user->name)
                   ->subject($emailData['subject'])
                   ->priority($this->getEmailPriority($emailData['priority']));
        });
    }

    /**
     * Send document notification email.
     */
    protected function sendDocumentNotificationEmail(User $user, array $emailData): void
    {
        Mail::send('emails.notifications.document', $emailData, function ($message) use ($user, $emailData) {
            $message->to($user->email, $user->name)
                   ->subject($emailData['subject'])
                   ->priority($this->getEmailPriority($emailData['priority']));
        });
    }

    /**
     * Send system notification email.
     */
    protected function sendSystemNotificationEmail(User $user, array $emailData): void
    {
        Mail::send('emails.notifications.system', $emailData, function ($message) use ($user, $emailData) {
            $message->to($user->email, $user->name)
                   ->subject($emailData['subject'])
                   ->priority($this->getEmailPriority($emailData['priority']));
        });
    }

    /**
     * Send generic notification email.
     */
    protected function sendGenericNotificationEmail(User $user, array $emailData): void
    {
        Mail::send('emails.notifications.generic', $emailData, function ($message) use ($user, $emailData) {
            $message->to($user->email, $user->name)
                   ->subject($emailData['subject'])
                   ->priority($this->getEmailPriority($emailData['priority']));
        });
    }

    /**
     * Get email priority.
     */
    protected function getEmailPriority(string $priority): int
    {
        return match ($priority) {
            'critical' => 1,
            'normal' => 3,
            'low' => 5,
            default => 3
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('EmailNotificationJob: Job failed permanently', [
            'error' => $exception->getMessage(),
            'notification_id' => $this->notificationId,
            'user_id' => $this->userId
        ]);

        // Mark notification as failed
        $notification = Notification::find($this->notificationId);
        if ($notification) {
            $notification->update([
                'email_failed_at' => now(),
                'email_failed' => true,
                'email_error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'email-notification',
            'user:' . $this->userId,
            'notification:' . $this->notificationId
        ];
    }
}