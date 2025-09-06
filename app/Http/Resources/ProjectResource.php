<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform Project model into JSON response
 * 
 * @property \App\Models\Project $resource
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'progress' => round($this->progress, 2),
            'actual_cost' => $this->actual_cost,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Conditional relationships
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                ];
            }),
            
            'components' => $this->whenLoaded('components', function () {
                return $this->components->map(function ($component) {
                    return [
                        'id' => $component->id,
                        'name' => $component->name,
                        'progress_percent' => $component->progress_percent,
                        'planned_cost' => $component->planned_cost,
                        'actual_cost' => $component->actual_cost,
                    ];
                });
            }),
            
            // Performance optimization - chỉ load khi cần
            'tasks_count' => $this->when(
                $this->relationLoaded('tasks'),
                fn() => $this->tasks->count()
            ),
            
            'interaction_logs_count' => $this->when(
                $this->relationLoaded('interactionLogs'),
                fn() => $this->interactionLogs->count()
            ),
        ];
    }
}