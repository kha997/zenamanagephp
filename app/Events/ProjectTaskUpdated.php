<?php declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * ProjectTaskUpdated Event - Broadcast khi task được cập nhật
 */
class ProjectTaskUpdated implements ShouldBroadcast
{

    public Task $task;
    public Project $project;
    public string $updatedBy;
    public array $changes;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, string $updatedBy, array $changes = [])
    {
        $this->task = $task;
        $this->project = $task->project;
        $this->updatedBy = $updatedBy;
        $this->changes = $changes;
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
            'type' => 'task_updated',
            'task' => [
                'id' => $this->task->id,
                'name' => $this->task->name,
                'description' => $this->task->description,
                'status' => $this->task->status,
                'priority' => $this->task->priority,
                'due_date' => $this->task->due_date?->toISOString(),
                'assigned_to' => $this->task->assigned_to,
                'progress' => $this->task->progress ?? 0
            ],
            'project' => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
                'progress' => $this->project->progress
            ],
            'updated_by' => $this->updatedBy,
            'changes' => $this->changes,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'task.updated';
    }
}