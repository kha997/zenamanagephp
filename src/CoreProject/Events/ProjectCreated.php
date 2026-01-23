<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use App\Models\Project as AppProject;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Src\CoreProject\Models\Project as CoreProjectProject;

/**
 * Event được dispatch khi project mới được tạo
 */
class ProjectCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly AppProject|CoreProjectProject $project;
    public readonly ?User $user;
    public readonly string $projectId;
    public readonly string $actorId;
    public readonly string $tenantId;
    public readonly string $projectName;
    public readonly ?string $templateId;
    public readonly array $projectData;
    public readonly \DateTime $timestamp;
    public readonly string $ownerId;

    public function __construct(
        AppProject|CoreProjectProject $project,
        ?User $user = null,
        ?string $templateId = null,
        ?\DateTime $timestamp = null,
        string $ownerId = ''
    ) {
        $this->project = $project;
        $this->user = $user;
        $actorIdSource = $user?->id ?? Auth::id();
        $this->actorId = $actorIdSource !== null ? (string) $actorIdSource : 'system';
        $this->projectId = (string) $project->id;
        $this->tenantId = (string) ($project->tenant_id ?? '');
        $this->projectName = $project->name;
        $this->projectData = $project->toArray();
        $this->templateId = $templateId;
        $this->timestamp = $timestamp ?? new \DateTime();
        $ownerCandidate = $ownerId !== '' ? $ownerId : ($project->created_by ?? $this->actorId);
        $this->ownerId = (string) $ownerCandidate;
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return 'Project.Project.Created';
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
            'template_id' => $this->templateId,
            'project_data' => $this->projectData,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'owner_id' => $this->ownerId,
        ];
    }

    public function toArray(): array
    {
        return $this->getPayload();
    }
}
