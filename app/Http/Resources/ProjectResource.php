<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'progress' => $this->progress_percentage,
            'dates' => [
                'start' => $this->start_date?->toISOString(),
                'end' => $this->end_date?->toISOString(),
                'created' => $this->created_at->toISOString(),
                'updated' => $this->updated_at->toISOString()
            ],
            'team' => UserResource::collection($this->whenLoaded('users')),
            'tasks_count' => $this->whenCounted('tasks'),
            'links' => [
                'self' => route('api.projects.show', $this->id),
                'tasks' => route('api.projects.tasks.index', $this->id)
            ]
        ];
    }
}
