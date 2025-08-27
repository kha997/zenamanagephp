<?php declare(strict_types=1);

namespace Src\InteractionLogs\Listeners;

use Src\InteractionLogs\Events\InteractionLogCreated;
use Src\InteractionLogs\Events\InteractionLogUpdated;
use Src\InteractionLogs\Events\InteractionLogDeleted;
use Src\InteractionLogs\Events\InteractionLogApprovedForClient;
use Illuminate\Support\Facades\Log;

/**
 * Listener xử lý các events của InteractionLog
 */
class InteractionLogEventListener
{
    /**
     * Xử lý event khi tạo interaction log mới
     */
    public function handleCreated(InteractionLogCreated $event): void
    {
        Log::info('Interaction log created', [
            'log_id' => $event->interactionLog->id,
            'project_id' => $event->interactionLog->project_id,
            'type' => $event->interactionLog->type,
            'created_by' => $event->interactionLog->created_by
        ]);

        // TODO: Có thể thêm logic gửi notification cho team members
        // TODO: Có thể thêm logic cập nhật project activity timeline
    }

    /**
     * Xử lý event khi cập nhật interaction log
     */
    public function handleUpdated(InteractionLogUpdated $event): void
    {
        Log::info('Interaction log updated', [
            'log_id' => $event->interactionLog->id,
            'project_id' => $event->interactionLog->project_id,
            'changed_fields' => $event->changedFields
        ]);
    }

    /**
     * Xử lý event khi xóa interaction log
     */
    public function handleDeleted(InteractionLogDeleted $event): void
    {
        Log::info('Interaction log deleted', [
            'log_id' => $event->deletedLogData['id'],
            'project_id' => $event->deletedLogData['project_id']
        ]);
    }

    /**
     * Xử lý event khi phê duyệt log cho client
     */
    public function handleApprovedForClient(InteractionLogApprovedForClient $event): void
    {
        Log::info('Interaction log approved for client', [
            'log_id' => $event->interactionLog->id,
            'project_id' => $event->interactionLog->project_id
        ]);

        // TODO: Có thể thêm logic gửi notification cho client
        // TODO: Có thể thêm logic cập nhật client portal
    }

    /**
     * Đăng ký các event listeners
     */
    public function subscribe($events): void
    {
        $events->listen(
            InteractionLogCreated::class,
            [InteractionLogEventListener::class, 'handleCreated']
        );

        $events->listen(
            InteractionLogUpdated::class,
            [InteractionLogEventListener::class, 'handleUpdated']
        );

        $events->listen(
            InteractionLogDeleted::class,
            [InteractionLogEventListener::class, 'handleDeleted']
        );

        $events->listen(
            InteractionLogApprovedForClient::class,
            [InteractionLogEventListener::class, 'handleApprovedForClient']
        );
    }
}