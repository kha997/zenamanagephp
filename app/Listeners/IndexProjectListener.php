<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProjectUpdated;
use App\Jobs\IndexProjectJob;
use App\Services\OutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IndexProjectListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected OutboxService $outboxService;

    public function __construct(OutboxService $outboxService)
    {
        $this->outboxService = $outboxService;
    }

    /**
     * Handle the event.
     */
    public function handle(ProjectUpdated $event): void
    {
        $project = $event->project;

        // Add to outbox for reliable indexing
        DB::transaction(function () use ($project) {
            $this->outboxService->add(
                'Project',
                $project->id,
                'ProjectUpdated',
                [
                    'project_id' => $project->id,
                    'tenant_id' => $project->tenant_id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'status' => $project->status,
                ],
                [
                    'tenant_id' => $project->tenant_id,
                    'user_id' => auth()->id(),
                    'correlation_id' => request()->header('X-Request-Id'),
                ]
            );
        });

        // Also dispatch index job directly (for immediate indexing if needed)
        IndexProjectJob::dispatch($project->id)->onQueue('search');

        Log::info('Project indexing queued', [
            'project_id' => $project->id,
            'tenant_id' => $project->tenant_id,
        ]);
    }
}
