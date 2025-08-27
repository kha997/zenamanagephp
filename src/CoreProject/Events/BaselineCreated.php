<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi baseline mới được tạo
 */
class BaselineCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int $baselineId ID của baseline
     * @param int $projectId ID của project
     * @param string $type Loại baseline (contract/execution)
     * @param int $version Version của baseline
     * @param int $actorId ID của user tạo baseline
     * @param array $baselineData Dữ liệu baseline
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly int $baselineId,
        public readonly int $projectId,
        public readonly string $type,
        public readonly int $version,
        public readonly int $actorId,
        public readonly array $baselineData,
        public readonly \DateTime $timestamp
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return 'Project.Baseline.Created';
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'baseline_id' => $this->baselineId,
            'project_id' => $this->projectId,
            'type' => $this->type,
            'version' => $this->version,
            'actor_id' => $this->actorId,
            'baseline_data' => $this->baselineData,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}