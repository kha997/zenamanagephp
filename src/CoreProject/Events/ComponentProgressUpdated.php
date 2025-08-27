<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi progress của component được cập nhật
 * Trigger tính toán lại progress của project
 */
class ComponentProgressUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int $componentId ID của component
     * @param int $projectId ID của project
     * @param int $actorId ID của user thực hiện
     * @param int $tenantId ID của tenant
     * @param float $oldProgress Progress cũ
     * @param float $newProgress Progress mới
     * @param float|null $oldCost Cost cũ
     * @param float|null $newCost Cost mới
     * @param array $changedFields Các field đã thay đổi
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly int $componentId,
        public readonly int $projectId,
        public readonly int $actorId,
        public readonly int $tenantId,
        public readonly float $oldProgress,
        public readonly float $newProgress,
        public readonly ?float $oldCost,
        public readonly ?float $newCost,
        public readonly array $changedFields,
        public readonly \DateTime $timestamp
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return 'Project.Component.ProgressUpdated';
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'component_id' => $this->componentId,
            'project_id' => $this->projectId,
            'actor_id' => $this->actorId,
            'tenant_id' => $this->tenantId,
            'old_progress' => $this->oldProgress,
            'new_progress' => $this->newProgress,
            'old_cost' => $this->oldCost,
            'new_cost' => $this->newCost,
            'changed_fields' => $this->changedFields,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}