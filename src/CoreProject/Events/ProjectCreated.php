<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use App\Models\Project as AppProject;
use App\Models\User;
use Src\CoreProject\Models\Project as CoreProject;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi project mới được tạo
 */
class ProjectCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const EVENT_NAME = 'Project.Project.Created';

    public readonly \DateTime $timestamp;
    public readonly string $projectId;
    public readonly ?string $actorId;

    public function __construct(
        public readonly AppProject|CoreProject $project,
        public readonly ?User $user = null,
        public readonly ?int $templateId = null,
        public readonly array $projectData = [],
        ?\DateTime $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
        $this->projectId = $project->id;
        $this->actorId = $this->user?->id;
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'project_id' => $this->project->id,
            'actor_id' => $this->user?->id,
            'tenant_id' => $this->project->tenant_id,
            'project_name' => $this->project->name,
            'template_id' => $this->templateId,
            'project_data' => $this->projectData,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }

    public function toArray(): array
    {
        return $this->getPayload();
    }
}
