<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TaskAssignment API Resource.
 *
 * Transforms TaskAssignment model for JSON responses according to JSend specification
 *
 * @property \App\Models\TaskAssignment $resource
 */
class TaskAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            // OWN-2026-024 SPLIT-PERCENT: stored column split_percent (legacy key kept for compatibility).
            'split_percent' => $this->storedSplitPercent(),
            'split_percentage' => $this->storedSplitPercent(),

            // Computed fields
            'workload_hours' => $this->getWorkloadHours(),
            'is_primary_assignee' => $this->isPrimaryAssignee(),

            // Relationships - conditional loading
            'task' => new TaskResource($this->whenLoaded('task')),
            'user' => new UserResource($this->whenLoaded('user')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /** OWN-2026-024 SPLIT-PERCENT: the stored share (NULL = not recorded). */
    private function storedSplitPercent(): ?float
    {
        $value = $this->resource->getAttribute('split_percent');

        return $value === null ? null : (float) $value;
    }
}
