<?php

namespace App\Events;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChangeRequest $changeRequest;
    public User $user;
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(ChangeRequest $changeRequest, User $user, array $metadata = [])
    {
        $this->changeRequest = $changeRequest;
        $this->user = $user;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->changeRequest->tenant_id),
            new PrivateChannel('project.' . $this->changeRequest->project_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'change_requested',
            'change_request' => [
                'id' => $this->changeRequest->id,
                'title' => $this->changeRequest->title,
                'type' => $this->changeRequest->type,
                'priority' => $this->changeRequest->priority,
                'status' => $this->changeRequest->status,
                'project_id' => $this->changeRequest->project_id,
                'task_id' => $this->changeRequest->task_id,
                'component_id' => $this->changeRequest->component_id,
                'cost_impact' => $this->changeRequest->cost_impact,
                'time_impact' => $this->changeRequest->time_impact,
                'created_at' => $this->changeRequest->created_at?->toISOString(),
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
        return 'change.requested';
    }
}