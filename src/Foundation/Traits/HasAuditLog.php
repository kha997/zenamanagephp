<?php
declare(strict_types=1);

namespace Src\Foundation\Traits;

use Src\Foundation\EventBus;
use Src\Foundation\Events\BaseEvent;
use Src\Foundation\PermissionContext;

/**
 * Trait để tự động tạo audit log khi model thay đổi
 */
trait HasAuditLog {
    /**
     * Dữ liệu gốc trước khi thay đổi
     * 
     * @var array
     */
    protected array $originalData = [];
    
    /**
     * Boot trait
     * 
     * @return void
     */
    protected static function bootHasAuditLog(): void {
        static::retrieved(function ($model) {
            $model->originalData = $model->getAttributes();
        });
        
        static::created(function ($model) {
            $model->fireAuditEvent('Created', []);
        });
        
        static::updated(function ($model) {
            $changedFields = $model->getChangedFields();
            if (!empty($changedFields)) {
                $model->fireAuditEvent('Updated', $changedFields);
            }
        });
        
        static::deleted(function ($model) {
            $model->fireAuditEvent('Deleted', []);
        });
    }
    
    /**
     * Lấy các trường đã thay đổi
     * 
     * @return array
     */
    protected function getChangedFields(): array {
        $changed = [];
        $current = $this->getAttributes();
        
        foreach ($current as $key => $value) {
            $oldValue = $this->originalData[$key] ?? null;
            
            if ($oldValue !== $value) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $value
                ];
            }
        }
        
        return $changed;
    }
    
    /**
     * Phát sự kiện audit
     * 
     * @param string $action
     * @param array $changedFields
     * @return void
     */
    protected function fireAuditEvent(string $action, array $changedFields): void {
        $context = PermissionContext::getContext();
        
        if (!$context) {
            return;
        }
        
        $entityName = class_basename($this);
        $eventName = "Audit.{$entityName}.{$action}";
        
        $payload = [
            'entityId' => $this->getKey(),
            'projectId' => $this->project_id ?? $context['project_id'] ?? 'system',
            'actorId' => $context['user_id'] ?? null,
            'changedFields' => $changedFields,
            'entityType' => get_class($this),
            'action' => $action
        ];
        
        EventBus::publish($eventName, $payload);
    }
}