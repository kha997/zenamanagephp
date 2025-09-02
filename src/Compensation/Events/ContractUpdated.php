<?php declare(strict_types=1);

namespace Src\Compensation\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi contract được cập nhật
 * Trigger preview compensation cho các task chưa hoàn thành
 */
class ContractUpdated extends BaseEvent
{
    /**
     * Dữ liệu contract đã được cập nhật
     * 
     * @var array
     */
    public array $contractData;
    
    /**
     * Dữ liệu contract cũ
     * 
     * @var array
     */
    public array $oldContractData;
    
    /**
     * Danh sách task IDs bị ảnh hưởng (chưa hoàn thành)
     * 
     * @var array
     */
    public array $affectedTaskIds;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của contract
     * @param string $projectId ID của Project
     * @param string $actorId ID của người cập nhật contract
     * @param array $contractData Dữ liệu contract mới
     * @param array $oldContractData Dữ liệu contract cũ
     * @param array $affectedTaskIds Danh sách task IDs bị ảnh hưởng
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $contractData,
        array $oldContractData,
        array $affectedTaskIds,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->contractData = $contractData;
        $this->oldContractData = $oldContractData;
        $this->affectedTaskIds = $affectedTaskIds;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Compensation.Contract.Updated';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'contractData' => $this->contractData,
            'oldContractData' => $this->oldContractData,
            'affectedTaskIds' => $this->affectedTaskIds,
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
    
    /**
     * Kiểm tra có task nào bị ảnh hưởng không
     * 
     * @return bool
     */
    public function hasAffectedTasks(): bool
    {
        return !empty($this->affectedTaskIds);
    }
    
    /**
     * Lấy số lượng task bị ảnh hưởng
     * 
     * @return int
     */
    public function getAffectedTasksCount(): int
    {
        return count($this->affectedTaskIds);
    }
}