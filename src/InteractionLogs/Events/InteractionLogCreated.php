<?php declare(strict_types=1);

namespace Src\InteractionLogs\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi interaction log được tạo
 * Trigger notifications và audit logging
 */
class InteractionLogCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $interactionLogId ID của interaction log
     * @param string $projectId ID của project
     * @param string|null $linkedTaskId ID của task liên quan
     * @param string $type Loại interaction
     * @param string $visibility Visibility level
     * @param string $actorId ID của user tạo
     * @param string $tenantId ID của tenant
     * @param array $attachments Danh sách file đính kèm
     * @param \DateTime $timestamp Thời gian event
     */
    public function __construct(
        public readonly string $interactionLogId,
        public readonly string $projectId,
        public readonly ?string $linkedTaskId,
        public readonly string $type,
        public readonly string $visibility,
        public readonly string $actorId,
        public readonly string $tenantId,
        public readonly array $attachments,
        public readonly \DateTime $timestamp
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     */
    public function getEventName(): string
    {
        return 'InteractionLogs.InteractionLog.Created';
    }

    /**
     * Lấy payload đầy đủ của event
     */
    public function getPayload(): array
    {
        return [
            'interaction_log_id' => $this->interactionLogId,
            'project_id' => $this->projectId,
            'linked_task_id' => $this->linkedTaskId,
            'type' => $this->type,
            'visibility' => $this->visibility,
            'actor_id' => $this->actorId,
            'tenant_id' => $this->tenantId,
            'attachments_count' => count($this->attachments),
            'has_attachments' => !empty($this->attachments),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}