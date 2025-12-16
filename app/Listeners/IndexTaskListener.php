<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskUpdated;
use App\Events\TaskMoved;
use App\Jobs\IndexTaskJob;
use App\Services\OutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IndexTaskListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected OutboxService $outboxService;

    public function __construct(OutboxService $outboxService)
    {
        $this->outboxService = $outboxService;
    }

    /**
     * Handle TaskUpdated event.
     */
    public function handleTaskUpdated(TaskUpdated $event): void
    {
        $task = $event->task;

        // Add to outbox for reliable indexing
        DB::transaction(function () use ($task) {
            $this->outboxService->add(
                'Task',
                $task->id,
                'TaskUpdated',
                [
                    'task_id' => $task->id,
                    'tenant_id' => $task->tenant_id,
                    'project_id' => $task->project_id,
                    'name' => $task->name,
                    'status' => $task->status->value ?? $task->status,
                ],
                [
                    'tenant_id' => $task->tenant_id,
                    'user_id' => auth()->id(),
                    'correlation_id' => request()->header('X-Request-Id'),
                ]
            );
        });

        // Also dispatch index job directly
        IndexTaskJob::dispatch($task->id)->onQueue('search');

        Log::info('Task indexing queued', [
            'task_id' => $task->id,
            'tenant_id' => $task->tenant_id,
        ]);
    }

    /**
     * Handle TaskMoved event.
     */
    public function handleTaskMoved(TaskMoved $event): void
    {
        $task = $event->task;

        // Add to outbox for reliable indexing
        DB::transaction(function () use ($task, $event) {
            $this->outboxService->add(
                'Task',
                $task->id,
                'TaskMoved',
                [
                    'task_id' => $task->id,
                    'tenant_id' => $task->tenant_id,
                    'project_id' => $task->project_id,
                    'old_status' => $event->oldStatus,
                    'new_status' => $event->newStatus->value ?? $event->newStatus,
                ],
                [
                    'tenant_id' => $task->tenant_id,
                    'user_id' => auth()->id(),
                    'correlation_id' => request()->header('X-Request-Id'),
                ]
            );
        });

        // Also dispatch index job directly
        IndexTaskJob::dispatch($task->id)->onQueue('search');

        Log::info('Task indexing queued (moved)', [
            'task_id' => $task->id,
            'tenant_id' => $task->tenant_id,
        ]);
    }
}
