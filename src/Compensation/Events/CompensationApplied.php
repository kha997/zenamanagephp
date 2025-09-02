<?php declare(strict_types=1);

namespace Src\Compensation\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi compensation được apply với contract và locked
 */
class CompensationApplied extends BaseEvent
{
    /**
     * Dữ liệu compensation đã được apply
     * 
     * @var array
     */
    public array $compensationData;
    
    /**
     * ID của contract được áp dụng
     * 
     * @var string
     */
    public string $contractId;
    
    /**
     * Danh sách task compensations đã được apply
     * 
     * @var array
     */
    public array $appliedCompensations;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của project
     * @param string $projectId ID của Project
     * @param string $actorId ID của người thực hiện apply
     * @param string $contractId ID của contract
     * @param array $compensationData Dữ liệu compensation
     * @param array $appliedCompensations Danh sách compensations đã apply
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        string $contractId,
        array $compensationData,
        array $appliedCompensations,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->contractId = $contractId;
        $this->compensationData = $compensationData;
        $this->appliedCompensations = $appliedCompensations;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Compensation.Compensation.Applied';
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
            'compensationData' => $this->compensationData,
            'appliedCompensations' => $this->appliedCompensations,
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