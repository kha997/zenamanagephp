<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi hai baseline được so sánh
 */
class BaselineCompared
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int $projectId ID của project
     * @param int $baseline1Id ID của baseline thứ nhất
     * @param int $baseline2Id ID của baseline thứ hai
     * @param array $comparisonResult Kết quả so sánh
     * @param int $actorId ID của user thực hiện so sánh
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $baseline1Id,
        public readonly int $baseline2Id,
        public readonly array $comparisonResult,
        public readonly int $actorId,
        public readonly \DateTime $timestamp
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return 'Project.Baseline.Compared';
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'project_id' => $this->projectId,
            'baseline1_id' => $this->baseline1Id,
            'baseline2_id' => $this->baseline2Id,
            'comparison_result' => $this->comparisonResult,
            'actor_id' => $this->actorId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}