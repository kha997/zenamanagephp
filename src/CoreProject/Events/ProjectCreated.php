<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi project mới được tạo
 */
class ProjectCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int $projectId ID của project
     * @param int $actorId ID của user tạo project
     * @param int $tenantId ID của tenant
     * @param string $projectName Tên project
     * @param int|null $templateId ID của template (nếu tạo từ template)
     * @param array $projectData Dữ liệu project
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $actorId,
        public readonly int $tenantId,
        public readonly string $projectName,
        public readonly ?int $templateId,
        public readonly array $projectData,
        public readonly \DateTime $timestamp
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
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
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}