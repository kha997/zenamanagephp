<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProjectUpdated;
use App\Services\CacheInvalidationService;
use Illuminate\Support\Facades\Log;

/**
 * Invalidate Project Cache Listener
 * 
 * Invalidates cache when project is updated.
 * 
 * Uses CacheInvalidationService for centralized cache invalidation.
 */
class InvalidateProjectCache
{
    public function __construct(
        private CacheInvalidationService $cacheInvalidationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ProjectUpdated $event): void
    {
        try {
            $this->cacheInvalidationService->forProjectUpdate($event->project);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate project cache', [
                'project_id' => $event->project->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }
}

