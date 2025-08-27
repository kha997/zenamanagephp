<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi status của task thay đổi
 */
class TaskStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param int $taskId ID của task
     * @param int $projectId ID của project
     * @param int $actorId ID của user thực hiện
     * @param int $tenantId ID của tenant
     * @param string $oldStatus Status cũ
     * @param string $newStatus Status mới
     * @param array $changedFields Các field đã thay đổi
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly int $taskId,
        public readonly int $projectId,
        public readonly int $actorId,
        public readonly int $tenantId,
        public readonly string $oldStatus,
        public readonly string $newStatus,
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
        return 'Project.Task.StatusChanged';
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'task_id' => $this->taskId,
            'project_id' => $this->projectId,
            'actor_id' => $this->actorId,
            'tenant_id' => $this->tenantId,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'changed_fields' => $this->changedFields,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}