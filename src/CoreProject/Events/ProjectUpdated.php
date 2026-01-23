<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use App\Models\Project;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

/**
 * Event được dispatch khi project được cập nhật
 */
class ProjectUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly Project $project;
    public readonly array $originalData;
    public readonly array $changedFields;
    public readonly ?User $user;
    public readonly string $projectId;
    public readonly string $actorId;
    public readonly string $tenantId;
    public readonly string $projectName;
    public readonly array $projectData;
    public readonly \DateTime $timestamp;

    public function __construct(
        Project $project,
        array $originalData,
        array $changedFields,
        ?User $user = null,
        ?\DateTime $timestamp = null
    ) {
        $this->project = $project;
        $this->originalData = $originalData;
        $this->changedFields = $changedFields;
        $this->user = $user;
        $actorIdSource = $user?->id ?? Auth::id();
        $this->actorId = $actorIdSource !== null ? (string) $actorIdSource : 'system';
        $this->projectId = (string) $project->id;
        $this->tenantId = (string) ($project->tenant_id ?? '');
        $this->projectName = $project->name;
        $this->projectData = $project->toArray();
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return 'Project.Project.Updated';
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'project_id' => $this->projectId,
            'actor_id' => $this->actorId,
            'tenant_id' => $this->tenantId,
            'project_name' => $this->projectName,
            'changed_fields' => $this->changedFields,
            'original_data' => $this->originalData,
            'project_data' => $this->projectData,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
        ];
    }

    public function toArray(): array
    {
        return $this->getPayload();
    }
}
