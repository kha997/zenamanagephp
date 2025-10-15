<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event được dispatch khi tạo project mới
 * Tuân thủ Z.E.N.A architecture với event-driven design
 */
class ProjectCreated
{

    /**
     * Constructor
     *
     * @param Project $project Project vừa được tạo
     */
    public function __construct(
        public Project $project
    ) {
        
    }

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
            'actorId' => Auth::id(),
            'tenantId' => $this->project->tenant_id,
            'projectName' => $this->project->name,
            'projectData' => $this->project->toArray(),
            'changedFields' => ['created'],
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];
    }
}