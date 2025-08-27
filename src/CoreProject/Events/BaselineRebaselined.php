<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi baseline được re-baseline
 */
class BaselineRebaselined
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int $oldBaselineId ID của baseline cũ
     * @param int $newBaselineId ID của baseline mới
     * @param int $projectId ID của project
     * @param string $type Loại baseline (contract/execution)
     * @param int $oldVersion Version cũ
     * @param int $newVersion Version mới
     * @param string $reason Lý do re-baseline
     * @param int $actorId ID của user thực hiện re-baseline
     * @param array $varianceData Dữ liệu variance trước khi re-baseline
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly int $oldBaselineId,
        public readonly int $newBaselineId,
        public readonly int $projectId,
        public readonly string $type,
        public readonly int $oldVersion,
        public readonly int $newVersion,
        public readonly string $reason,
        public readonly int $actorId,
        public readonly array $varianceData,
        public readonly \DateTime $timestamp
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return 'Project.Baseline.Rebaselined';
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'old_baseline_id' => $this->oldBaselineId,
            'new_baseline_id' => $this->newBaselineId,
            'project_id' => $this->projectId,
            'type' => $this->type,
            'old_version' => $this->oldVersion,
            'new_version' => $this->newVersion,
            'reason' => $this->reason,
            'actor_id' => $this->actorId,
            'variance_data' => $this->varianceData,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}