<?php
declare(strict_types=1);

namespace Src\Foundation\Traits;

use Src\Foundation\Foundation;

/**
 * Trait cung cấp các phương thức Foundation cho các model
 */
trait FoundationTrait {
    /**
     * Tự động tạo ULID cho trường id khi tạo mới
     */
    protected static function bootFoundationTrait(): void {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Foundation::generateUlid();
            }
        });
    }
    
    /**
     * Tạo tag structure cho model
     * 
     * @param string $tagPath
     * @return array
     */
    public function createTagStructure(string $tagPath): array {
        return Foundation::createTagStructure($tagPath);
    }
    
    /**
     * Tạo visibility structure cho model
     * 
     * @param string $visibility
     * @param bool $clientApproved
     * @return array
     */
    public function createVisibilityStructure(string $visibility, bool $clientApproved = false): array {
        return Foundation::createVisibilityStructure($visibility, $clientApproved);
    }
    
    /**
     * Phát sự kiện khi model thay đổi
     * 
     * @param string $action
     * @param array $changedFields
     * @return void
     */
    public function emitFoundationEvent(string $action, array $changedFields = []): void {
        $modelName = strtolower(class_basename($this));
        $eventName = Foundation::createEventName($modelName, $action, 'performed');
        
        $payload = Foundation::createEventPayload(
            $this->id,
            $this->project_id ?? null,
            auth()->id() ?? 'system',
            $changedFields,
            $this->tenant_id ?? null
        );
        
        // Phát sự kiện (sẽ implement EventBus sau)
        // EventBus::publish($eventName, $payload);
    }
}