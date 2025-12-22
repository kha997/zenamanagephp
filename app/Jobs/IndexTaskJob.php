<?php

namespace App\Jobs;

use App\Models\Task;
use App\Services\SearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * IndexTaskJob
 * 
 * Indexes a task in the search engine (Meilisearch).
 * Called via Outbox when TaskUpdated event fires.
 */
class IndexTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $taskId
    ) {}

    public function handle(SearchService $searchService): void
    {
        $task = Task::find($this->taskId);
        
        if (!$task) {
            Log::warning('Task not found for indexing', [
                'task_id' => $this->taskId,
            ]);
            return;
        }
        
        $searchService->indexTask($task);
    }
}
