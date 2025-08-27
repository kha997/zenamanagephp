<?php declare(strict_types=1);

namespace Src\Notification\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Src\Notification\Events\NotificationCreated;
use Src\Notification\Models\Notification;
use App\Models\User;

/**
 * Event Listener xử lý gửi notifications qua các kênh khác nhau
 * Xử lý delivery của notifications (email, webhook, etc.)
 */
class NotificationChannelListener
{
    /**
     * Đăng ký các event listeners
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            NotificationCreated::class,
            [NotificationChannelListener::class, 'handleNotificationCreated']
        );
    }

    /**
     * Xử lý khi Notification được tạo
     * Gửi notification qua kênh tương ứng
     *
     * @param NotificationCreated $event
     * @return void
     */
    public function handleNotificationCreated(NotificationCreated $event): void
    {
        $notification = $event->notification;
        
        try {
            switch ($notification->channel) {
                case 'inapp':
                    $this->handleInAppNotification($notification);
                    break;
                    
                case 'email':
                    $this->handleEmailNotification($notification);
                    break;
                    
                case 'webhook':
                    $this->handleWebhookNotification($notification);
                    break;
                    
                default:
                    Log::warning('Unknown notification channel', [
                        'notification_id' => $notification->ulid,
                        'channel' => $notification->channel
                    ]);
            }
            
            // Cập nhật trạng thái đã gửi
            $notification->update([
                'sent_at' => now(),
                'delivery_status' => 'sent'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'notification_id' => $notification->ulid,
                'channel' => $notification->channel,
                'error' => $e->getMessage()
            ]);
            
            // Cập nhật trạng thái lỗi
            $notification->update([
                'delivery_status' => 'failed',
                'delivery_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý in-app notification
     * (Notification đã được lưu vào DB, chỉ cần log)
     *
     * @param Notification $notification
     * @return void
     */
    private function handleInAppNotification(Notification $notification): void
    {
        Log::info('In-app notification created', [
            'notification_id' => $notification->ulid,
            'user_id' => $notification->user_id,
            'title' => $notification->title
        ]);
        
        // TODO: Có thể push qua WebSocket/Pusher để real-time notification
        // TODO: Có thể trigger browser notification nếu user online
    }

    /**
     * Xử lý email notification
     *
     * @param Notification $notification
     * @return void
     */
    private function handleEmailNotification(Notification $notification): void
    {
        $user = User::find($notification->user_id);
        
        if (!$user || !$user->email) {
            throw new \Exception('User not found or email not available');
        }

        // TODO: Tạo Mailable class cho notification emails
        // Mail::to($user->email)->send(new NotificationMail($notification));
        
        Log::info('Email notification sent', [
            'notification_id' => $notification->ulid,
            'user_email' => $user->email,
            'title' => $notification->title
        ]);
    }

    /**
     * Xử lý webhook notification
     *
     * @param Notification $notification
     * @return void
     */
    private function handleWebhookNotification(Notification $notification): void
    {
        $user = User::find($notification->user_id);
        
        // TODO: Lấy webhook URL từ user settings hoặc project settings
        $webhookUrl = $this->getWebhookUrl($user, $notification);
        
        if (!$webhookUrl) {
            throw new \Exception('Webhook URL not configured');
        }

        $payload = [
            'notification_id' => $notification->ulid,
            'user_id' => $notification->user_id,
            'project_id' => $notification->project_id,
            'priority' => $notification->priority,
            'title' => $notification->title,
            'body' => $notification->body,
            'link_url' => $notification->link_url,
            'event_key' => $notification->event_key,
            'metadata' => $notification->metadata,
            'created_at' => $notification->created_at->toISOString()
        ];

        $response = Http::timeout(10)->post($webhookUrl, $payload);
        
        if (!$response->successful()) {
            throw new \Exception('Webhook request failed: ' . $response->status());
        }
        
        Log::info('Webhook notification sent', [
            'notification_id' => $notification->ulid,
            'webhook_url' => $webhookUrl,
            'response_status' => $response->status()
        ]);
    }

    /**
     * Lấy webhook URL cho user/project
     *
     * @param User|null $user
     * @param Notification $notification
     * @return string|null
     */
    private function getWebhookUrl(?User $user, Notification $notification): ?string
    {
        // TODO: Implement logic để lấy webhook URL
        // Có thể từ user settings, project settings, hoặc notification rule
        
        return null; // Placeholder
    }
}