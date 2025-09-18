<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

/**
 * Service class for managing notifications and delivery channels
 * Handles notification creation, rule evaluation, and multi-channel delivery
 */
class NotificationService
{
    /**
     * Create and send notification based on event and user rules
     *
     * @param string $eventKey Event identifier (e.g., 'task.assigned', 'project.completed')
     * @param array $eventData Event payload data
     * @param int $projectId Project context for notification
     * @param string $priority Notification priority (critical, normal, low)
     * @return Collection Collection of created notifications
     */
    public function createNotificationFromEvent(
        string $eventKey,
        array $eventData,
        int $projectId,
        string $priority = 'normal'
    ): Collection {
        // Get all applicable notification rules for this event
        $rules = $this->getApplicableRules($eventKey, $projectId, $priority);
        
        $notifications = collect();
        
        foreach ($rules as $rule) {
            try {
                $notification = $this->createNotification(
                    $rule->user_id,
                    $priority,
                    $this->generateTitle($eventKey, $eventData),
                    $this->generateBody($eventKey, $eventData),
                    $this->generateLinkUrl($eventKey, $eventData, $projectId)
                );
                
                // Send through configured channels
                $this->sendThroughChannels($notification, $rule->channels, $rule->user);
                
                $notifications->push($notification);
                
            } catch (\Exception $e) {
                Log::error('Failed to create notification', [
                    'event_key' => $eventKey,
                    'user_id' => $rule->user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $notifications;
    }
    
    /**
     * Create a new notification record
     *
     * @param int $userId Target user ID
     * @param string $priority Notification priority
     * @param string $title Notification title
     * @param string $body Notification body content
     * @param string|null $linkUrl Optional link URL
     * @return Notification Created notification instance
     */
    public function createNotification(
        int $userId,
        string $priority,
        string $title,
        string $body,
        ?string $linkUrl = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'priority' => $priority,
            'title' => $title,
            'body' => $body,
            'link_url' => $linkUrl,
            'channel' => 'inapp' // Default channel, will be updated by delivery
        ]);
    }
    
    /**
     * Send notification through specified channels
     *
     * @param Notification $notification Notification to send
     * @param array $channels Array of channel names
     * @param User $user Target user
     * @return void
     */
    protected function sendThroughChannels(Notification $notification, array $channels, User $user): void
    {
        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $this->sendEmailNotification($notification, $user);
                        break;
                    case 'webhook':
                        $this->sendWebhookNotification($notification, $user);
                        break;
                    case 'inapp':
                        // In-app notifications are already stored in database
                        break;
                    default:
                        Log::warning('Unknown notification channel', ['channel' => $channel]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send notification via channel', [
                    'channel' => $channel,
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Send notification via email
     *
     * @param Notification $notification Notification to send
     * @param User $user Target user
     * @return void
     */
    protected function sendEmailNotification(Notification $notification, User $user): void
    {
        // Implementation would use Laravel Mail facade
        // For now, just log the action
        Log::info('Email notification sent', [
            'notification_id' => $notification->id,
            'user_email' => $user->email,
            'title' => $notification->title
        ]);
    }
    
    /**
     * Send notification via webhook
     *
     * @param Notification $notification Notification to send
     * @param User $user Target user
     * @return void
     */
    protected function sendWebhookNotification(Notification $notification, User $user): void
    {
        // Implementation would send HTTP POST to configured webhook URL
        Log::info('Webhook notification sent', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'title' => $notification->title
        ]);
    }
    
    /**
     * Get notification rules applicable to event and priority
     *
     * @param string $eventKey Event identifier
     * @param int $projectId Project context
     * @param string $priority Event priority
     * @return Collection Collection of applicable notification rules
     */
    protected function getApplicableRules(string $eventKey, int $projectId, string $priority): Collection
    {
        $priorityLevels = ['low' => 1, 'normal' => 2, 'critical' => 3];
        $eventPriorityLevel = $priorityLevels[$priority] ?? 2;
        
        return NotificationRule::with('user')
            ->where('event_key', $eventKey)
            ->where('is_enabled', true)
            ->where(function ($query) use ($projectId) {
                $query->whereNull('project_id')
                      ->orWhere('project_id', $projectId);
            })
            ->where(function ($query) use ($eventPriorityLevel, $priorityLevels) {
                $query->where('min_priority', 'low')
                      ->orWhere(function ($subQuery) use ($eventPriorityLevel, $priorityLevels) {
                          $subQuery->where('min_priority', 'normal')
                                   ->where($eventPriorityLevel, '>=', $priorityLevels['normal']);
                      })
                      ->orWhere(function ($subQuery) use ($eventPriorityLevel, $priorityLevels) {
                          $subQuery->where('min_priority', 'critical')
                                   ->where($eventPriorityLevel, '>=', $priorityLevels['critical']);
                      });
            })
            ->get();
    }
    
    /**
     * Generate notification title from event data
     *
     * @param string $eventKey Event identifier
     * @param array $eventData Event payload
     * @return string Generated title
     */
    protected function generateTitle(string $eventKey, array $eventData): string
    {
        $titles = [
            'task.assigned' => 'Nhiệm vụ mới được giao',
            'task.completed' => 'Nhiệm vụ đã hoàn thành',
            'project.progress_updated' => 'Tiến độ dự án được cập nhật',
            'change_request.approved' => 'Yêu cầu thay đổi được phê duyệt',
            'change_request.rejected' => 'Yêu cầu thay đổi bị từ chối',
            'document.version_created' => 'Phiên bản tài liệu mới',
            'interaction_log.created' => 'Nhật ký tương tác mới'
        ];
        
        return $titles[$eventKey] ?? 'Thông báo hệ thống';
    }
    
    /**
     * Generate notification body from event data
     *
     * @param string $eventKey Event identifier
     * @param array $eventData Event payload
     * @return string Generated body content
     */
    protected function generateBody(string $eventKey, array $eventData): string
    {
        switch ($eventKey) {
            case 'task.assigned':
                return sprintf(
                    'Bạn đã được giao nhiệm vụ "%s" trong dự án "%s".',
                    $eventData['task_name'] ?? 'N/A',
                    $eventData['project_name'] ?? 'N/A'
                );
            case 'task.completed':
                return sprintf(
                    'Nhiệm vụ "%s" đã được hoàn thành bởi %s.',
                    $eventData['task_name'] ?? 'N/A',
                    $eventData['completed_by'] ?? 'N/A'
                );
            case 'project.progress_updated':
                return sprintf(
                    'Tiến độ dự án "%s" đã được cập nhật lên %s%%.',
                    $eventData['project_name'] ?? 'N/A',
                    $eventData['progress'] ?? '0'
                );
            case 'change_request.approved':
                return sprintf(
                    'Yêu cầu thay đổi "%s" đã được phê duyệt.',
                    $eventData['cr_title'] ?? 'N/A'
                );
            default:
                return 'Có hoạt động mới trong hệ thống.';
        }
    }
    
    /**
     * Generate link URL for notification
     *
     * @param string $eventKey Event identifier
     * @param array $eventData Event payload
     * @param int $projectId Project context
     * @return string|null Generated URL or null
     */
    protected function generateLinkUrl(string $eventKey, array $eventData, int $projectId): ?string
    {
        $baseUrl = config('app.frontend_url', 'http://localhost:3000');
        
        switch ($eventKey) {
            case 'task.assigned':
            case 'task.completed':
                return $eventData['task_id'] 
                    ? "{$baseUrl}/projects/{$projectId}/tasks/{$eventData['task_id']}"
                    : null;
            case 'project.progress_updated':
                return "{$baseUrl}/projects/{$projectId}";
            case 'change_request.approved':
            case 'change_request.rejected':
                return $eventData['cr_id']
                    ? "{$baseUrl}/projects/{$projectId}/change-requests/{$eventData['cr_id']}"
                    : null;
            default:
                return "{$baseUrl}/projects/{$projectId}";
        }
    }
    
    /**
     * Mark notification as read
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID for security check
     * @return bool Success status
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();
            
        if (!$notification) {
            return false;
        }
        
        $notification->update(['read_at' => now()]);
        return true;
    }
    
    /**
     * Get unread notifications for user
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications
     * @return Collection Collection of unread notifications
     */
    public function getUnreadNotifications(int $userId, int $limit = 50): Collection
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}