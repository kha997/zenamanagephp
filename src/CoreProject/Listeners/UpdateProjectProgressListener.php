<?php declare(strict_types=1);

namespace Src\CoreProject\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Jobs\RecalculateProjectRollupJob;
use Illuminate\Support\Facades\Log;

/**
 * Listener xử lý event ComponentProgressUpdated
 * Dispatch job để tính toán lại project progress và cost
 */
class UpdateProjectProgressListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Xử lý event ComponentProgressUpdated
     */
    public function handle(ComponentProgressUpdated $event): void
    {
        try {
            // Dispatch job để tính toán roll-up trong background
            RecalculateProjectRollupJob::dispatch(
                $event->projectId,
                $event->componentId,
                $event->actorId,
                $event->tenantId
            )->delay(now()->addSeconds(5)); // Delay 5 giây để tránh race condition
            
            Log::info('Dispatched project rollup calculation job', [
                'project_id' => $event->projectId,
                'component_id' => $event->componentId,
                'actor_id' => $event->actorId
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to dispatch project rollup job', [
                'project_id' => $event->projectId,
                'component_id' => $event->componentId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Xử lý khi listener failed
     */
    public function failed(ComponentProgressUpdated $event, \Throwable $exception): void
    {
        Log::error('UpdateProjectProgressListener failed', [
            'project_id' => $event->projectId,
            'component_id' => $event->componentId,
            'error' => $exception->getMessage()
        ]);
    }
}