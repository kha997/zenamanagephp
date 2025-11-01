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

class TaskCommentDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $commentId;
    public string $taskId;
    public string $projectId;
    public string $tenantId;
    public User $user;

    /**
     * Create a new event instance.
     */
    public function __construct(string $commentId, string $taskId, string $projectId, string $tenantId, User $user)
    {
        $this->commentId = $commentId;
        $this->taskId = $taskId;
        $this->projectId = $projectId;
        $this->tenantId = $tenantId;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('task.' . $this->taskId),
            new PrivateChannel('project.' . $this->projectId),
            new PrivateChannel('tenant.' . $this->tenantId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->commentId,
            'task_id' => $this->taskId,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'action' => 'deleted',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'comment.deleted';
    }
}