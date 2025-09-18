<?php declare(strict_types=1);

namespace App\Listeners;

use App\Services\NotificationService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Event listener for creating notifications from various system events
 * Subscribes to multiple events and creates appropriate notifications
 */
class NotificationEventListener
{
    protected NotificationService $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Handle task assignment events
     *
     * @param object $event Task assignment event
     * @return void
     */
    public function handleTaskAssigned($event): void
    {
        try {
            $this->notificationService->createNotificationFromEvent(
                'task.assigned',
                [
                    'task_id' => $event->taskId,
                    'task_name' => $event->taskName ?? 'N/A',
                    'project_name' => $event->projectName ?? 'N/A',
                    'assigned_to' => $event->assignedTo ?? 'N/A'
                ],
                $event->projectId,
                'normal'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create task assignment notification', [
                'event' => get_class($event),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle task completion events
     *
     * @param object $event Task completion event
     * @return void
     */
    public function handleTaskCompleted($event): void
    {
        try {
            $this->notificationService->createNotificationFromEvent(
                'task.completed',
                [
                    'task_id' => $event->taskId,
                    'task_name' => $event->taskName ?? 'N/A',
                    'project_name' => $event->projectName ?? 'N/A',
                    'completed_by' => $event->completedBy ?? 'N/A'
                ],
                $event->projectId,
                'normal'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create task completion notification', [
                'event' => get_class($event),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle project progress update events
     *
     * @param object $event Project progress event
     * @return void
     */
    public function handleProjectProgressUpdated($event): void
    {
        try {
            $this->notificationService->createNotificationFromEvent(
                'project.progress_updated',
                [
                    'project_name' => $event->projectName ?? 'N/A',
                    'progress' => $event->progress ?? 0,
                    'previous_progress' => $event->previousProgress ?? 0
                ],
                $event->projectId,
                'normal'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create project progress notification', [
                'event' => get_class($event),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle change request approval events
     *
     * @param object $event Change request approval event
     * @return void
     */
    public function handleChangeRequestApproved($event): void
    {
        try {
            $this->notificationService->createNotificationFromEvent(
                'change_request.approved',
                [
                    'cr_id' => $event->changeRequestId,
                    'cr_title' => $event->title ?? 'N/A',
                    'approved_by' => $event->approvedBy ?? 'N/A'
                ],
                $event->projectId,
                'normal'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create change request approval notification', [
                'event' => get_class($event),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle change request rejection events
     *
     * @param object $event Change request rejection event
     * @return void
     */
    public function handleChangeRequestRejected($event): void
    {
        try {
            $this->notificationService->createNotificationFromEvent(
                'change_request.rejected',
                [
                    'cr_id' => $event->changeRequestId,
                    'cr_title' => $event->title ?? 'N/A',
                    'rejected_by' => $event->rejectedBy ?? 'N/A',
                    'reason' => $event->reason ?? 'N/A'
                ],
                $event->projectId,
                'normal'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create change request rejection notification', [
                'event' => get_class($event),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle document version creation events
     *
     * @param object $event Document version event
     * @return void
     */
    public function handleDocumentVersionCreated($event): void
    {
        try {
            $this->notificationService->createNotificationFromEvent(
                'document.version_created',
                [
                    'document_id' => $event->documentId,
                    'document_title' => $event->title ?? 'N/A',
                    'version_number' => $event->versionNumber ?? 1,
                    'created_by' => $event->createdBy ?? 'N/A'
                ],
                $event->projectId,
                'low'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create document version notification', [
                'event' => get_class($event),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle interaction log creation events
     *
     * @param object $event Interaction log event
     * @return void
     */
    public function handleInteractionLogCreated($event): void
    {
        try {
            // Only create notifications for client-visible interactions
            if ($event->visibility === 'client' && $event->clientApproved) {
                $this->notificationService->createNotificationFromEvent(
                    'interaction_log.created',
                    [
                        'log_id' => $event->logId,
                        'type' => $event->type ?? 'N/A',
                        'description' => $event->description ?? 'N/A',
                        'created_by' => $event->createdBy ?? 'N/A'
                    ],
                    $event->projectId,
                    'low'
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create interaction log notification', [
                'event' => get_class($event),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Register event listeners
     *
     * @param Dispatcher $events Event dispatcher
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            'App\\Events\\TaskAssigned',
            [NotificationEventListener::class, 'handleTaskAssigned']
        );
        
        $events->listen(
            'App\\Events\\TaskCompleted',
            [NotificationEventListener::class, 'handleTaskCompleted']
        );
        
        $events->listen(
            'App\\Events\\ProjectProgressUpdated',
            [NotificationEventListener::class, 'handleProjectProgressUpdated']
        );
        
        $events->listen(
            'App\\Events\\ChangeRequestApproved',
            [NotificationEventListener::class, 'handleChangeRequestApproved']
        );
        
        $events->listen(
            'App\\Events\\ChangeRequestRejected',
            [NotificationEventListener::class, 'handleChangeRequestRejected']
        );
        
        $events->listen(
            'App\\Events\\DocumentVersionCreated',
            [NotificationEventListener::class, 'handleDocumentVersionCreated']
        );
        
        $events->listen(
            'App\\Events\\InteractionLogCreated',
            [NotificationEventListener::class, 'handleInteractionLogCreated']
        );
    }
}