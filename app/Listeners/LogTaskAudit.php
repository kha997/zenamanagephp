<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogTaskAudit implements ShouldQueue
{

    private AuditService $auditService;

    /**
     * Create the event listener.
     */
    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the event.
     */
    public function handle(TaskUpdated $event): void
    {
        try {
            $task = $event->task;
            
            // Log the task update in audit trail
            $this->auditService->logAction([
                'tenant_id' => $task->tenant_id,
                'user_id' => $event->user->id,
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'component_id' => $task->component_id,
                'type' => 'task_updated',
                'content' => "Task '{$task->name}' was updated",
                'metadata' => [
                    'old_data' => $event->oldData,
                    'changes' => $event->changes,
                    'user_name' => $event->user->name,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
                'is_internal' => false,
            ]);
            
            // Log CRUD operation
            $this->auditService->logCrudOperation('update', 'Task', $task->id, [
                'tenant_id' => $task->tenant_id,
                'user_id' => $event->user->id,
                'project_id' => $task->project_id,
                'old_data' => $event->oldData,
                'changes' => $event->changes,
            ]);
            
            Log::info("Task audit logged", [
                'task_id' => $task->id,
                'user_id' => $event->user->id,
                'changes' => array_keys($event->changes),
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to log task audit", [
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
    public function failed(TaskUpdated $event, $exception): void
    {
        Log::error("LogTaskAudit listener failed", [
            'task_id' => $event->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}