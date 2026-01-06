<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Project;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ProjectUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public Project $project,
        public array $originalData = [],
        public ?User $actor = null
    ) {}

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
            'originalData' => $this->originalData,
            'actorId' => $this->actor?->id,
            'timestamp' => now()->toISOString()
        ];
    }
}
