<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TaskUpdated implements ShouldBroadcast
{

    public Task $task;
    public User $user;
    public array $oldData;
    public array $changes;
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, User $user, array $oldData = [], array $changes = [], array $metadata = [])
    {
        $this->task = $task;
        $this->user = $user;
        $this->oldData = $oldData;
        $this->changes = $changes;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    /**
     * @return array<\Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->task->tenant_id),
            new PrivateChannel('project.' . $this->task->project_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'task_updated',
            'task' => [
                'id' => $this->task->id,
                'name' => $this->task->name,
                'status' => $this->task->status,
                'progress' => $this->task->progress,
                'project_id' => $this->task->project_id,
                'component_id' => $this->task->component_id,
                'user_id' => $this->task->user_id,
                'updated_at' => $this->task->updated_at?->toISOString(),
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'old_data' => $this->oldData,
            'changes' => $this->changes,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'task.updated';
    }
}
