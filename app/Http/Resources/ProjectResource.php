<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'code' => $this->code,
            'status' => $this->status,
            'priority' => $this->priority,
            'budget_total' => $this->budget_total,
            'budget' => $this->budget_total, // Alias for convenience
            'budget_planned' => $this->budget_planned,
            'budget_actual' => $this->budget_actual,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'progress_pct' => $this->progress_pct,
            'progress_percent' => $this->progress_pct, // Standardize: progress_pct → progress_percent
            'progress' => $this->progress_pct, // Alias for convenience
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'completion_percentage' => $this->completion_percentage,
            'risk_level' => $this->risk_level,
            'is_template' => $this->is_template,
            'template_id' => $this->template_id,
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'tags' => $this->tags,
            'settings' => $this->settings,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'owner' => $this->whenLoaded('owner', function () {
                return [
                    'id' => $this->owner->id,
                    'name' => $this->owner->name,
                    'email' => $this->owner->email,
                ];
            }),
            'tasks' => $this->whenLoaded('tasks', function () {
                return $this->tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->name, // Standardize: name → title
                        'status' => $task->status,
                        'priority' => $task->priority,
                        'due_date' => $task->end_date?->toDateString(), // Standardize: end_date → due_date
                        'progress_percent' => $task->progress_percent,
                    ];
                });
            }),
            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'name' => $document->original_name,
                        'file_path' => $document->file_path,
                        'mime_type' => $document->mime_type,
                        'file_size' => $document->file_size,
                        'category' => $document->category,
                        'status' => $document->status,
                    ];
                });
            }),
            'teams' => $this->whenLoaded('teams', function () {
                return $this->teams->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'description' => $team->description,
                        'role' => $team->role,
                        'is_active' => $team->is_active,
                    ];
                });
            }),
        ];
    }
}