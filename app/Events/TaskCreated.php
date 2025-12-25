<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TaskCreated implements ShouldBroadcast
{

    public Task $task;
    public User $user;
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(Task $task, User $user, array $metadata = [])
    {
        $this->task = $task;
        $this->user = $user;
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
            'event_type' => 'task_created',
            'task' => [
                'id' => $this->task->id,
                'name' => $this->task->name,
                'status' => $this->task->status,
                'project_id' => $this->task->project_id,
                'component_id' => $this->task->component_id,
                'user_id' => $this->task->user_id,
                'start_date' => $this->task->start_date?->toISOString(),
                'end_date' => $this->task->end_date?->toISOString(),
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'task.created';
    }
}
