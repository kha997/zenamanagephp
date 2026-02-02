<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use App\Models\Project;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly array $originalData,
        public readonly ?User $user = null
    ) {
    }

    public function getEventName(): string
    {
        return 'Project.Project.Updated';
    }

    public function getPayload(): array
    {
        return [
            'project_id' => $this->project->id,
            'actor_id' => $this->user?->id,
            'tenant_id' => $this->project->tenant_id,
            'project_name' => $this->project->name,
            'changes' => array_diff_assoc($this->project->toArray(), $this->originalData),
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];
    }
}
