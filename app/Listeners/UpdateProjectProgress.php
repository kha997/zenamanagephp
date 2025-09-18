<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use App\Services\CalculationService;
use App\Services\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateProjectProgress implements ShouldQueue
{
    use InteractsWithQueue;

    private CalculationService $calculationService;
    private AuditService $auditService;

    /**
     * Create the event listener.
     */
    public function __construct(CalculationService $calculationService, AuditService $auditService)
    {
        $this->calculationService = $calculationService;
        $this->auditService = $auditService;
    }

    /**
     * Handle the event.
     */
    public function handle(TaskCompleted $event): void
    {
        try {
            $task = $event->task;
            
            // Update project progress
            $this->calculationService->calculateProjectProgress($task->project_id);
            
            // Update component progress if task belongs to a component
            if ($task->component_id) {
                $this->calculationService->calculateComponentProgress($task->component_id);
            }
            
            // Log the calculation update
            $this->auditService->logSystemEvent('project_progress_updated', [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'component_id' => $task->component_id,
                'triggered_by' => 'task_completed',
                'user_id' => $event->user->id,
            ]);
            
            Log::info("Project progress updated after task completion", [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to update project progress after task completion", [
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
    public function failed(TaskCompleted $event, $exception): void
    {
        Log::error("UpdateProjectProgress listener failed", [
            'task_id' => $event->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}