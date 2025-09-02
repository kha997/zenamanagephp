<?php declare(strict_types=1);

namespace Src\Compensation\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi compensation được preview trước khi apply contract
 */
class CompensationPreviewed extends BaseEvent
{
    /**
     * Dữ liệu preview compensation
     * 
     * @var array
     */
    public array $previewData;
    
    /**
     * ID của contract được áp dụng
     * 
     * @var string
     */
    public string $contractId;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của project
     * @param string $projectId ID của Project
     * @param string $actorId ID của người thực hiện preview
     * @param string $contractId ID của contract
     * @param array $previewData Dữ liệu preview compensation
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        string $contractId,
        array $previewData,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->contractId = $contractId;
        $this->previewData = $previewData;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Compensation.Compensation.Previewed';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'contractId' => $this->contractId,
            'previewData' => $this->previewData,
        ]);
    }
    
    /**
     * Lấy payload cho event
     * 
     * @return array
     */
    public function getPayload(): array
    {
        return $this->toArray();
    }
}