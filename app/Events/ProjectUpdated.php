<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ProjectUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(public Project $project) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->project->tenant_id}.projects"),
            new Channel("project.{$this->project->id}")
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'project' => $this->project->load('users', 'tasks'),
            'timestamp' => now()->toISOString()
        ];
    }
}
