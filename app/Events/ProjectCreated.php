<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Auth;

/**
 * Event được dispatch khi tạo project mới
 * Tuân thủ Z.E.N.A architecture với event-driven design
 */
class ProjectCreated implements Arrayable
{
    use Dispatchable;

    public function __construct(
        public Project $project,
        public ?User $actor = null
    ) {}

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     *
     * @return string
     */
    public function getEventName(): string
    {
        return 'Project.Project.Created';
    }

    /**
     * Lấy payload đầy đủ của event
     *
     * @return array
     */
    public function getPayload(): array
    {
        return [
            'entityId' => $this->project->id,
            'projectId' => $this->project->id,
            'actorId' => $this->actor?->id ?? Auth::id(),
            'tenantId' => $this->project->tenant_id,
            'projectName' => $this->project->name,
            'projectData' => $this->project->toArray(),
            'changedFields' => ['created'],
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];
    }

    public function toArray(): array
    {
        return $this->getPayload();
    }
}
