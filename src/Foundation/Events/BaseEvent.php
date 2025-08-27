<?php declare(strict_types=1);

namespace Src\Foundation\Events;

use Illuminate\Support\Str;

/**
 * Base class cho tất cả các events trong hệ thống
 */
abstract class BaseEvent {
    /**
     * ID của entity liên quan
     * 
     * @var string
     */
    public string $entityId;
    
    /**
     * ID của project
     * 
     * @var string
     */
    public string $projectId;
    
    /**
     * ID của người thực hiện hành động
     * 
     * @var string
     */
    public string $actorId;
    
    /**
     * Các trường đã thay đổi
     * 
     * @var array
     */
    public array $changedFields;
    
    /**
     * Thời gian xảy ra sự kiện
     * 
     * @var string
     */
    public string $timestamp;
    
    /**
     * ID duy nhất của sự kiện
     * 
     * @var string
     */
    public string $eventId;
    
    /**
     * Constructor
     * 
     * @param string $entityId
     * @param string $projectId
     * @param string $actorId
     * @param array $changedFields
     */
    public function __construct(
        string $entityId = '',
        string $projectId = '',
        string $actorId = '',
        array $changedFields = []
    ) {
        $this->entityId = $entityId;
        $this->projectId = $projectId;
        $this->actorId = $actorId;
        $this->changedFields = $changedFields;
        $this->timestamp = now()->toISOString();
        $this->eventId = (string) Str::ulid();
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    abstract public function getEventName(): string;
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'entityId' => $this->entityId,
            'projectId' => $this->projectId,
            'actorId' => $this->actorId,
            'changedFields' => $this->changedFields,
            'timestamp' => $this->timestamp,
            'eventId' => $this->eventId,
            'eventName' => $this->getEventName()
        ];
    }
}