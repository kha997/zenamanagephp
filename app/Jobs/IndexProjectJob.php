<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\SearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * IndexProjectJob
 * 
 * Indexes a project in the search engine (Meilisearch).
 * Called via Outbox when ProjectUpdated event fires.
 */
class IndexProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $projectId
    ) {}

    public function handle(SearchService $searchService): void
    {
        $project = Project::find($this->projectId);
        
        if (!$project) {
            Log::warning('Project not found for indexing', [
                'project_id' => $this->projectId,
            ]);
            return;
        }
        
        $searchService->indexProject($project);
    }
}
