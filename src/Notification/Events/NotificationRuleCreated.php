<?php declare(strict_types=1);

namespace Src\Notification\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Notification Rule được tạo mới
 */
class NotificationRuleCreated extends BaseEvent
{
    /**
     * Dữ liệu của Notification Rule được tạo
     * 
     * @var array
     */
    public array $ruleData;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Notification Rule
     * @param string $projectId ID của Project (có thể null cho system rules)
     * @param string $actorId ID của người tạo
     * @param array $ruleData Dữ liệu của Notification Rule
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $ruleData,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->ruleData = $ruleData;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Notification.NotificationRule.Created';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'ruleData' => $this->ruleData,
        ]);
    }
}