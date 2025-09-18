<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Project;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ProjectTeamMemberJoined Event - Broadcast khi team member join project
 */
class ProjectTeamMemberJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Project $project;
    public User $user;
    public string $role;
    public string $addedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(Project $project, User $user, string $role, string $addedBy)
    {
        $this->project = $project;
        $this->user = $user;
        $this->role = $role;
        $this->addedBy = $addedBy;
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
            'type' => 'team_member_joined',
            'project' => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'avatar' => $this->user->avatar
            ],
            'role' => $this->role,
            'added_by' => $this->addedBy,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'team.member_joined';
    }
}