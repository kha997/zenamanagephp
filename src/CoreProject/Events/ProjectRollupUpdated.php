<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi project progress và cost được tính toán lại
 * từ các components con (roll-up calculation)
 */
class ProjectRollupUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int $projectId ID của project
     * @param int $actorId ID của user thực hiện
     * @param int $tenantId ID của tenant
     * @param float $oldProgress Progress cũ của project
     * @param float $newProgress Progress mới của project
     * @param float $oldCost Cost cũ của project
     * @param float $newCost Cost mới của project
     * @param int $triggerComponentId ID của component trigger event này
     * @param array $affectedComponents Danh sách components bị ảnh hưởng
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $actorId,
        public readonly int $tenantId,
        public readonly float $oldProgress,
        public readonly float $newProgress,
        public readonly float $oldCost,
        public readonly float $newCost,
        public readonly int $triggerComponentId,
        public readonly array $affectedComponents,
        public readonly \DateTime $timestamp
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return 'Project.Project.RollupUpdated';
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
            'old_progress' => $this->oldProgress,
            'new_progress' => $this->newProgress,
            'old_cost' => $this->oldCost,
            'new_cost' => $this->newCost,
            'trigger_component_id' => $this->triggerComponentId,
            'affected_components' => $this->affectedComponents,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Kiểm tra xem có thay đổi đáng kể không
     */
    public function hasSignificantChange(): bool
    {
        $progressChange = abs($this->newProgress - $this->oldProgress);
        $costChange = abs($this->newCost - $this->oldCost);
        
        return $progressChange >= 0.01 || $costChange >= 0.01; // Thay đổi >= 1%
    }
}