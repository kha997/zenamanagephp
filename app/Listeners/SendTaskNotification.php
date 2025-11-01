<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendTaskNotification implements ShouldQueue
{

    private NotificationRuleService $notificationService;
    private AuditService $auditService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationRuleService $notificationService, AuditService $auditService)
    {
        $this->notificationService = $notificationService;
        $this->auditService = $auditService;
    }

    /**
     * Handle the event.
     */
    public function handle(TaskCreated $event): void
    {
        try {
            $task = $event->task;
            
            // Prepare event data for notification rules
            $eventData = [
                'task_id' => $task->id,
                'task_name' => $task->name,
                'task_status' => $task->status,
                'project_id' => $task->project_id,
                'component_id' => $task->component_id,
                'user_id' => $task->user_id,
                'assigned_to' => $task->user_id,
                'created_by' => $event->user->id,
                'created_by_name' => $event->user->name,
                'start_date' => $task->start_date?->format('Y-m-d'),
                'end_date' => $task->end_date?->format('Y-m-d'),
                'priority' => $task->priority ?? 'medium',
            ];
            
            // Evaluate and trigger notification rules
            $this->notificationService->evaluateRules('task_created', $eventData);
            
            // Log the notification
            $this->auditService->logSystemEvent('task_notification_sent', [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'event_type' => 'task_created',
                'user_id' => $event->user->id,
            ]);
            
            Log::info("Task notification sent", [
                'task_id' => $task->id,
                'event_type' => 'task_created',
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to send task notification", [
                'task_id' => $event->task->id,
                'error' => $e->getMessage(),
            ]);
            
            // Don't fail the job, just log the error
            $this->release(30); // Retry in 30 seconds
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(TaskCreated $event, $exception): void
    {
        Log::error("SendTaskNotification listener failed", [
            'task_id' => $event->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}