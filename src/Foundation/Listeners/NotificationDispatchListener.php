<?php declare(strict_types=1);

namespace Src\Foundation\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Notification\Events\NotificationTriggered;

/**
 * Universal listener để dispatch notifications dựa trên business events
 * Subscribe tới các events quan trọng và tạo notifications tương ứng
 */
class NotificationDispatchListener
{
    /**
     * Xử lý ComponentProgressUpdated event
     */
    public function handleComponentProgressUpdated($event): void
    {
        try {
            // Lấy danh sách users cần notify cho project này
            $projectUsers = $this->getProjectStakeholders($event->projectId);
            
            foreach ($projectUsers as $user) {
                // Kiểm tra notification rules của user
                if ($this->shouldNotifyUser($user['id'], 'component.progress.updated', $event->projectId)) {
                    $channels = $this->getUserNotificationChannels($user['id'], 'component.progress.updated');
                    
                    NotificationTriggered::dispatch(
                        $user['id'],
                        $event->tenantId,
                        'normal',
                        'Component Progress Updated',
                        "Progress updated from {$event->oldProgress}% to {$event->newProgress}%",
                        "/projects/{$event->projectId}/components/{$event->componentId}",
                        $channels,
                        $event->getEventName(),
                        $event->getPayload(),
                        new \DateTime()
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch component progress notifications', [
                'event' => $event->getEventName(),
                'component_id' => $event->componentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý InteractionLogCreated event
     */
    public function handleInteractionLogCreated($event): void
    {
        try {
            // Notify project stakeholders về interaction log mới
            $projectUsers = $this->getProjectStakeholders($event->projectId);
            
            foreach ($projectUsers as $user) {
                if ($this->shouldNotifyUser($user['id'], 'interaction_log.created', $event->projectId)) {
                    $channels = $this->getUserNotificationChannels($user['id'], 'interaction_log.created');
                    
                    $priority = $event->visibility === 'client' ? 'normal' : 'low';
                    
                    NotificationTriggered::dispatch(
                        $user['id'],
                        $event->tenantId,
                        $priority,
                        'New Interaction Log',
                        "New {$event->type} interaction logged for project",
                        "/projects/{$event->projectId}/interactions/{$event->interactionLogId}",
                        $channels,
                        $event->getEventName(),
                        $event->getPayload(),
                        new \DateTime()
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch interaction log notifications', [
                'event' => $event->getEventName(),
                'interaction_log_id' => $event->interactionLogId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý ChangeRequestApproved event
     */
    public function handleChangeRequestApproved($event): void
    {
        try {
            // Notify tất cả stakeholders về CR được approve
            $projectUsers = $this->getProjectStakeholders($event->projectId);
            
            foreach ($projectUsers as $user) {
                if ($this->shouldNotifyUser($user['id'], 'change_request.approved', $event->projectId)) {
                    $channels = $this->getUserNotificationChannels($user['id'], 'change_request.approved');
                    
                    NotificationTriggered::dispatch(
                        $user['id'],
                        $event->tenantId,
                        'critical',
                        'Change Request Approved',
                        "Change request #{$event->changeRequestCode} has been approved",
                        "/projects/{$event->projectId}/change-requests/{$event->changeRequestId}",
                        $channels,
                        $event->getEventName(),
                        $event->getPayload(),
                        new \DateTime()
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch change request notifications', [
                'event' => $event->getEventName(),
                'change_request_id' => $event->changeRequestId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Lấy danh sách stakeholders của project
     */
    private function getProjectStakeholders(string $projectId): array
    {
        return DB::table('project_user_roles')
            ->join('users', 'project_user_roles.user_id', '=', 'users.id')
            ->where('project_user_roles.project_id', $projectId)
            ->select('users.id', 'users.name', 'users.email')
            ->distinct()
            ->get()
            ->toArray();
    }

    /**
     * Kiểm tra user có muốn nhận notification cho event này không
     */
    private function shouldNotifyUser(string $userId, string $eventKey, string $projectId): bool
    {
        $rule = DB::table('notification_rules')
            ->where('user_id', $userId)
            ->where('event_key', $eventKey)
            ->where(function ($query) use ($projectId) {
                $query->whereNull('project_id')
                      ->orWhere('project_id', $projectId);
            })
            ->where('is_enabled', true)
            ->first();
            
        return $rule !== null;
    }

    /**
     * Lấy channels notification của user cho event
     */
    private function getUserNotificationChannels(string $userId, string $eventKey): array
    {
        $rule = DB::table('notification_rules')
            ->where('user_id', $userId)
            ->where('event_key', $eventKey)
            ->where('is_enabled', true)
            ->first();
            
        return $rule ? json_decode($rule->channels, true) : ['inapp'];
    }

    /**
     * Universal handler method để EventBus có thể gọi
     * Định tuyến event đến method cụ thể dựa trên event name
     */
    public function handle($event): void
    {
        $eventName = $event->getEventName();
        
        switch ($eventName) {
            case 'Project.Component.ProgressUpdated':
                $this->handleComponentProgressUpdated($event);
                break;
                
            case 'InteractionLog.Created':
                $this->handleInteractionLogCreated($event);
                break;
                
            case 'ChangeRequest.Approved':
                $this->handleChangeRequestApproved($event);
                break;
                
            default:
                Log::warning('NotificationDispatchListener: Unhandled event', [
                    'event_name' => $eventName,
                    'event_class' => get_class($event)
                ]);
        }
    }
}