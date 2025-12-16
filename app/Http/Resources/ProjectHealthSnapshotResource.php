<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Project Health Snapshot Resource
 * 
 * Round 86: Project Health History (snapshots + history API, backend-only)
 * 
 * Transforms ProjectHealthSnapshot model to API response format.
 */
class ProjectHealthSnapshotResource extends JsonResource
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
            'snapshot_date' => $this->snapshot_date->toDateString(),
            'overall_status' => $this->overall_status,
            'schedule_status' => $this->schedule_status,
            'cost_status' => $this->cost_status,
            'tasks_completion_rate' => $this->tasks_completion_rate,
            'blocked_tasks_ratio' => $this->blocked_tasks_ratio,
            'overdue_tasks' => $this->overdue_tasks,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
