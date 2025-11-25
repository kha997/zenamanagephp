<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskMoved;
use App\Events\TaskUpdated;
use App\Services\CacheInvalidationService;
use Illuminate\Support\Facades\Log;

/**
 * Invalidate Task Cache Listener
 * 
 * Invalidates cache when task is updated or moved.
 * 
 * Uses CacheInvalidationService for centralized cache invalidation.
 */
class InvalidateTaskCache
{
    public function __construct(
        private CacheInvalidationService $cacheInvalidationService
    ) {}

    /**
     * Handle TaskUpdated event.
     */
    public function handleTaskUpdated(TaskUpdated $event): void
    {
        try {
            $this->cacheInvalidationService->forTaskUpdate($event->task);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate task cache', [
                'task_id' => $event->task->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle TaskMoved event.
     */
    public function handleTaskMoved(TaskMoved $event): void
    {
        try {
            // Task move invalidates task cache and project KPIs
            $this->cacheInvalidationService->forTaskUpdate($event->task);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate task cache on move', [
                'task_id' => $event->task->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }
}

