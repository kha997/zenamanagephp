<?php declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * ProjectMilestoneCompleted Event - Broadcast khi milestone được hoàn thành
 */
class ProjectMilestoneCompleted implements ShouldBroadcast
{

    public ProjectMilestone $milestone;
    public Project $project;
    public string $completedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(ProjectMilestone $milestone, string $completedBy)
    {
        $this->milestone = $milestone;
        $this->project = $milestone->project;
        $this->completedBy = $completedBy;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->project->id),
            new PrivateChannel('tenant.' . $this->project->tenant_id)
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'milestone_completed',
            'milestone' => [
                'id' => $this->milestone->id,
                'name' => $this->milestone->name,
                'description' => $this->milestone->description,
                'target_date' => $this->milestone->target_date?->toISOString(),
                'completed_date' => $this->milestone->completed_date?->toISOString(),
                'status' => $this->milestone->status,
                'order' => $this->milestone->order
            ],
            'project' => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
                'progress' => $this->project->progress
            ],
            'completed_by' => $this->completedBy,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'milestone.completed';
    }
}