<?php declare(strict_types=1);

namespace App\Events;

use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TaskComment $comment;
    public User $user;
    public array $changes;

    /**
     * Create a new event instance.
     */
    public function __construct(TaskComment $comment, User $user, array $changes = [])
    {
        $this->comment = $comment;
        $this->user = $user;
        $this->changes = $changes;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('task.' . $this->comment->task_id),
            new PrivateChannel('project.' . $this->comment->task->project_id),
            new PrivateChannel('tenant.' . $this->comment->task->tenant_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'task_id' => $this->comment->task_id,
            'user_id' => $this->comment->user_id,
            'content' => $this->comment->content,
            'type' => $this->comment->type,
            'is_internal' => $this->comment->is_internal,
            'parent_id' => $this->comment->parent_id,
            'updated_at' => $this->comment->updated_at->toISOString(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'changes' => $this->changes,
            'action' => 'updated',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'comment.updated';
    }
}