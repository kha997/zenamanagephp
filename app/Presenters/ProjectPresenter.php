<?php

namespace App\Presenters;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProjectPresenter
{
    /**
     * Format projects for view display
     */
    public static function formatForView($projects): array
    {
        $projectsPaginator = ($projects instanceof LengthAwarePaginator) ? $projects : null;
        
        $projectsCollection = $projectsPaginator
            ? collect($projectsPaginator->items())
            : collect($projects ?? []);
            
        $projectItems = $projectsCollection->map(function ($project) {
            return [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'priority' => $project->priority,
                'progress_pct' => $project->progress_pct ?? $project->progress ?? 0,
                'tasks_completed' => data_get($project, 'tasks_completed', data_get($project, 'completed_tasks', 0)),
                'tasks_total' => data_get($project, 'tasks_total', data_get($project, 'total_tasks', 0)),
                'members_count' => data_get($project, 'members_count', 0),
                'due_date' => optional($project->due_date)->toDateString(),
                'start_date' => optional($project->start_date)->toDateString(),
                'end_date' => optional($project->end_date)->toDateString(),
                'client_id' => $project->client_id,
                'owner_name' => optional($project->owner)->name,
                'updated_at' => optional($project->updated_at)->toDateTimeString(),
                'created_at' => optional($project->created_at)->toDateTimeString(),
            ];
        })->values()->toArray();
        
        return $projectItems;
    }
    
    /**
     * Format pagination metadata
     */
    public static function formatPaginationMeta($projects): array
    {
        if ($projects instanceof LengthAwarePaginator) {
            return [
                'currentPage' => $projects->currentPage(),
                'lastPage' => $projects->lastPage(),
                'perPage' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem() ?? 0,
                'to' => $projects->lastItem() ?? 0,
            ];
        }
        
        $total = count($projects ?? []);
        return [
            'currentPage' => 1,
            'lastPage' => 1,
            'perPage' => 12,
            'total' => $total,
            'from' => $total ? 1 : 0,
            'to' => $total,
        ];
    }
    
    /**
     * Format client options
     */
    public static function formatClientOptions($clients): array
    {
        if (!isset($clients) || !method_exists($clients, 'map')) {
            return [];
        }
        
        return $clients->map(fn ($client) => [
            'id' => $client->id,
            'name' => $client->name
        ])->values()->toArray();
    }
}

