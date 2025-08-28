<?php declare(strict_types=1);

namespace Src\Notification\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Src\Foundation\Services\WebSocketService;
use Src\Notification\Events\NotificationCreated;
use Src\Notification\Models\Notification;
use Src\Notification\Models\NotificationRule;

/**
 * Listener xử lý việc gửi notification qua các channel khác nhau
 */
class NotificationChannelListener implements ShouldQueue
{
    use InteractsWithQueue;

    private WebSocketService $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Xử lý event NotificationCreated
     *
     * @param NotificationCreated $event
     * @return void
     */
    public function handle(NotificationCreated $event): void
    {
        $notification = $event->notification;
        
        // Lấy notification rules của user
        $rules = NotificationRule::where('user_id', $notification->user_id)
            ->where('is_enabled', true)
            ->where('min_priority', '<=', $this->getPriorityValue($notification->priority))
            ->get();

        foreach ($rules as $rule) {
            $channels = json_decode($rule->channels, true) ?? [];
            
            foreach ($channels as $channel) {
                $this->sendNotification($notification, $channel);
            }
        }

        // Luôn gửi in-app notification
        $this->sendInAppNotification($notification);
    }

    /**
     * Gửi notification qua channel cụ thể
     *
     * @param Notification $notification
     * @param string $channel
     * @return void
     */
    private function sendNotification(Notification $notification, string $channel): void
    {
        try {
            switch ($channel) {
                case 'inapp':
                    $this->sendInAppNotification($notification);
                    break;
                    
                case 'email':
                    $this->sendEmailNotification($notification);
                    break;
                    
                case 'websocket':
                    $this->sendWebSocketNotification($notification);
                    break;
                    
                case 'webhook':
                    $this->sendWebhookNotification($notification);
                    break;
                    
                default:
                    Log::warning('Unknown notification channel', [
                        'channel' => $channel,
                        'notification_id' => $notification->id
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send notification via channel', [
                'channel' => $channel,
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi in-app notification (đã có sẵn trong database)
     *
     * @param Notification $notification
     * @return void
     */
    private function sendInAppNotification(Notification $notification): void
    {
        // In-app notification đã được lưu trong database
        // Chỉ cần gửi qua WebSocket để real-time update
        $this->sendWebSocketNotification($notification);
        
        Log::info('In-app notification ready', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id
        ]);
    }

    /**
     * Gửi email notification
     *
     * @param Notification $notification
     * @return void
     */
    private function sendEmailNotification(Notification $notification): void
    {
        try {
            // TODO: Implement email notification
            // Mail::to($notification->user->email)->send(new NotificationMail($notification));
            
            Log::info('Email notification sent', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi WebSocket notification cho real-time update
     *
     * @param Notification $notification
     * @return void
     */
    private function sendWebSocketNotification(Notification $notification): void
    {
        try {
            $data = [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'priority' => $notification->priority,
                'link_url' => $notification->link_url,
                'created_at' => $notification->created_at->toISOString(),
                'read_at' => $notification->read_at?->toISOString()
            ];

            $success = $this->webSocketService->broadcastToUser(
                $notification->user_id,
                $data
            );

            if ($success) {
                Log::info('WebSocket notification sent', [
                    'notification_id' => $notification->id,
                    'user_id' => $notification->user_id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send WebSocket notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi webhook notification
     *
     * @param Notification $notification
     * @return void
     */
    private function sendWebhookNotification(Notification $notification): void
    {
        try {
            // TODO: Implement webhook notification
            
            Log::info('Webhook notification sent', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send webhook notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Chuyển đổi priority string thành số để so sánh
     *
     * @param string $priority
     * @return int
     */
    private function getPriorityValue(string $priority): int
    {
        return match ($priority) {
            'critical' => 3,
            'normal' => 2,
            'low' => 1,
            default => 1
        };
    }
}