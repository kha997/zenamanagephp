<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi cập nhật project
 * Tuân thủ Z.E.N.A architecture với event-driven design
 */
class ProjectUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Constructor
     *
     * @param Project $project Project vừa được cập nhật
     */
    public function __construct(
        public Project $project
    ) {
        //
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     *
     * @return string
     */
    public function getEventName(): string
    {
        return 'Project.Project.Updated';
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
            'actorId' => auth()->id(),
            'tenantId' => $this->project->tenant_id,
            'projectName' => $this->project->name,
            'projectData' => $this->project->toArray(),
            'changedFields' => array_keys($this->project->getDirty()),
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];
    }
}