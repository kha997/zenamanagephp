<?php

namespace App\Events;

use App\Models\Component;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComponentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Component $component;
    public User $user;
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(Component $component, User $user, array $metadata = [])
    {
        $this->component = $component;
        $this->user = $user;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->component->tenant_id),
            new PrivateChannel('project.' . $this->component->project_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'component_created',
            'component' => [
                'id' => $this->component->id,
                'name' => $this->component->name,
                'type' => $this->component->type,
                'status' => $this->component->status,
                'progress' => $this->component->progress,
                'project_id' => $this->component->project_id,
                'parent_id' => $this->component->parent_id,
                'created_at' => $this->component->created_at?->toISOString(),
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
        return 'component.created';
    }
}