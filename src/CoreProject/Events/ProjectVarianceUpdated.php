<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi variance của project được cập nhật
 */
class ProjectVarianceUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int $projectId ID của project
     * @param int $baselineId ID của baseline được so sánh
     * @param string $baselineType Loại baseline (contract/execution)
     * @param array $scheduleVariance Dữ liệu variance về lịch trình
     * @param array $costVariance Dữ liệu variance về chi phí
     * @param array $earnedValueData Dữ liệu Earned Value Management
     * @param string $overallHealth Tình trạng tổng thể (good/warning/critical)
     * @param array $recommendations Các khuyến nghị
     * @param int $actorId ID của user trigger event
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $baselineId,
        public readonly string $baselineType,
        public readonly array $scheduleVariance,
        public readonly array $costVariance,
        public readonly array $earnedValueData,
        public readonly string $overallHealth,
        public readonly array $recommendations,
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
        return 'Project.Variance.Updated';
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'project_id' => $this->projectId,
            'baseline_id' => $this->baselineId,
            'baseline_type' => $this->baselineType,
            'schedule_variance' => $this->scheduleVariance,
            'cost_variance' => $this->costVariance,
            'earned_value_data' => $this->earnedValueData,
            'overall_health' => $this->overallHealth,
            'recommendations' => $this->recommendations,
            'actor_id' => $this->actorId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}