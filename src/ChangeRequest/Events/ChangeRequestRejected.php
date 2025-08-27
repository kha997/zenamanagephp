<?php declare(strict_types=1);

namespace Src\ChangeRequest\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Change Request bị từ chối
 */
class ChangeRequestRejected extends BaseEvent
{
    /**
     * Dữ liệu của Change Request bị từ chối
     * 
     * @var array
     */
    public array $changeRequestData;
    
    /**
     * ID của người từ chối
     * 
     * @var string
     */
    public string $deciderId;
    
    /**
     * Lý do từ chối
     * 
     * @var string|null
     */
    public ?string $rejectionReason;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Change Request
     * @param string $projectId ID của Project
     * @param string $actorId ID của người thực hiện action
     * @param array $changeRequestData Dữ liệu của Change Request
     * @param string $deciderId ID của người từ chối
     * @param string|null $rejectionReason Lý do từ chối
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $changeRequestData,
        string $deciderId,
        ?string $rejectionReason = null,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->changeRequestData = $changeRequestData;
        $this->deciderId = $deciderId;
        $this->rejectionReason = $rejectionReason;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'ChangeRequest.ChangeRequest.Rejected';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'changeRequestData' => $this->changeRequestData,
            'deciderId' => $this->deciderId,
            'rejectionReason' => $this->rejectionReason,
        ]);
    }
}