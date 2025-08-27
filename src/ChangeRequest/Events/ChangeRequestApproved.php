<?php declare(strict_types=1);

namespace Src\ChangeRequest\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Change Request được phê duyệt
 * Event này rất quan trọng vì các module khác sẽ lắng nghe để áp dụng thay đổi
 */
class ChangeRequestApproved extends BaseEvent
{
    /**
     * Dữ liệu của Change Request được phê duyệt
     * 
     * @var array
     */
    public array $changeRequestData;
    
    /**
     * Dữ liệu impact để các module khác áp dụng
     * 
     * @var array
     */
    public array $impactData;
    
    /**
     * ID của người phê duyệt
     * 
     * @var string
     */
    public string $deciderId;
    
    /**
     * Ghi chú quyết định
     * 
     * @var string|null
     */
    public ?string $decisionNote;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Change Request
     * @param string $projectId ID của Project
     * @param string $actorId ID của người thực hiện action
     * @param array $changeRequestData Dữ liệu của Change Request
     * @param array $impactData Dữ liệu impact
     * @param string $deciderId ID của người phê duyệt
     * @param string|null $decisionNote Ghi chú quyết định
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $changeRequestData,
        array $impactData,
        string $deciderId,
        ?string $decisionNote = null,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->changeRequestData = $changeRequestData;
        $this->impactData = $impactData;
        $this->deciderId = $deciderId;
        $this->decisionNote = $decisionNote;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'ChangeRequest.ChangeRequest.Approved';
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
            'impactData' => $this->impactData,
            'deciderId' => $this->deciderId,
            'decisionNote' => $this->decisionNote,
        ]);
    }
}