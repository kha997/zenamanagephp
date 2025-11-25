<?php declare(strict_types=1);

namespace App\Listeners;

use App\Services\CacheInvalidationService;
use App\Events\TaskMoved;
use App\Events\TaskUpdated;
use App\Events\ProjectUpdated;
use App\Events\ProjectCreated;
use Illuminate\Support\Facades\Log;

/**
 * Invalidate Cache on Domain Event
 * 
 * Listens to domain events and triggers cache invalidation.
 * Ensures cache consistency when data changes.
 */
class InvalidateCacheOnDomainEvent
{
    public function __construct(
        private CacheInvalidationService $cacheInvalidationService
    ) {}

    /**
     * Handle TaskMoved event
     */
    public function handleTaskMoved(TaskMoved $event): void
    {
        $payload = [
            'task_id' => $event->task->id,
            'project_id' => $event->task->project_id,
            'tenant_id' => $event->task->tenant_id,
            'old_status' => $event->oldStatus,
            'new_status' => $event->newStatus->value,
        ];

        $this->cacheInvalidationService->invalidateOnEvent('TaskMoved', $payload);
    }

    /**
     * Handle TaskUpdated event
     */
    public function handleTaskUpdated(TaskUpdated $event): void
    {
        $payload = [
            'task_id' => $event->task->id,
            'project_id' => $event->task->project_id ?? null,
            'tenant_id' => $event->task->tenant_id,
        ];

        $this->cacheInvalidationService->invalidateOnEvent('TaskUpdated', $payload);
    }

    /**
     * Handle ProjectUpdated event
     */
    public function handleProjectUpdated(ProjectUpdated $event): void
    {
        $payload = [
            'project_id' => $event->project->id,
            'tenant_id' => $event->project->tenant_id,
        ];

        $this->cacheInvalidationService->invalidateOnEvent('ProjectUpdated', $payload);
    }

    /**
     * Handle ProjectCreated event
     */
    public function handleProjectCreated(ProjectCreated $event): void
    {
        $payload = [
            'project_id' => $event->project->id,
            'tenant_id' => $event->project->tenant_id,
        ];

        $this->cacheInvalidationService->invalidateOnEvent('ProjectCreated', $payload);
    }
}

