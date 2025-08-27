<?php declare(strict_types=1);

namespace Src\Notification\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Src\Notification\Events\NotificationRuleCreated;
use Src\Notification\Events\NotificationRuleUpdated;
use Src\Notification\Events\NotificationRuleDeleted;
use Src\Notification\Models\NotificationRule;

/**
 * Event Listener cho các sự kiện của Notification Rules
 * Xử lý business logic khi có thay đổi trong notification rules
 */
class NotificationRuleEventListener
{
    /**
     * Đăng ký các event listeners
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            NotificationRuleCreated::class,
            [NotificationRuleEventListener::class, 'handleRuleCreated']
        );

        $events->listen(
            NotificationRuleUpdated::class,
            [NotificationRuleEventListener::class, 'handleRuleUpdated']
        );

        $events->listen(
            NotificationRuleDeleted::class,
            [NotificationRuleEventListener::class, 'handleRuleDeleted']
        );
    }

    /**
     * Xử lý khi Notification Rule được tạo
     *
     * @param NotificationRuleCreated $event
     * @return void
     */
    public function handleRuleCreated(NotificationRuleCreated $event): void
    {
        Log::info('Notification rule created', [
            'rule_id' => $event->notificationRule->ulid,
            'user_id' => $event->notificationRule->user_id,
            'project_id' => $event->notificationRule->project_id,
            'event_key' => $event->notificationRule->event_key,
            'channels' => $event->notificationRule->channels
        ]);

        // TODO: Có thể thêm logic validation hoặc setup bổ sung
        // TODO: Có thể gửi confirmation notification cho user
    }

    /**
     * Xử lý khi Notification Rule được cập nhật
     *
     * @param NotificationRuleUpdated $event
     * @return void
     */
    public function handleRuleUpdated(NotificationRuleUpdated $event): void
    {
        Log::info('Notification rule updated', [
            'rule_id' => $event->notificationRule->ulid,
            'user_id' => $event->notificationRule->user_id,
            'changes' => $event->notificationRule->getChanges()
        ]);

        // TODO: Có thể thêm logic xử lý khi rule thay đổi
        // TODO: Invalidate cache nếu có
    }

    /**
     * Xử lý khi Notification Rule bị xóa
     *
     * @param NotificationRuleDeleted $event
     * @return void
     */
    public function handleRuleDeleted(NotificationRuleDeleted $event): void
    {
        Log::info('Notification rule deleted', [
            'rule_id' => $event->ruleId,
            'user_id' => $event->userId,
            'deleted_by' => $event->deletedBy
        ]);

        // TODO: Cleanup related data nếu cần
        // TODO: Có thể gửi confirmation notification
    }
}