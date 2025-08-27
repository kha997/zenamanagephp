<?php declare(strict_types=1);

namespace Src\Foundation\Traits;

use Illuminate\Database\Eloquent\Model;
use Src\Foundation\PermissionContext;

/**
 * Trait HasAuditFields
 * 
 * Cung cấp các field audit tracking cho models
 * Tự động set created_by và updated_by khi model được tạo/cập nhật
 * 
 * @property string|null $created_by
 * @property string|null $updated_by
 */
trait HasAuditFields
{
    /**
     * Boot trait để tự động set audit fields
     */
    protected static function bootHasAuditFields(): void
    {
        static::creating(function (Model $model) {
            $context = PermissionContext::getContext();
            $userId = $context['user_id'] ?? null;
            
            if ($userId && !$model->created_by) {
                $model->created_by = $userId;
            }
            
            if ($userId && !$model->updated_by) {
                $model->updated_by = $userId;
            }
        });
        
        static::updating(function (Model $model) {
            $context = PermissionContext::getContext();
            $userId = $context['user_id'] ?? null;
            
            if ($userId) {
                $model->updated_by = $userId;
            }
        });
    }
    
    /**
     * Relationship với user tạo record
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    
    /**
     * Relationship với user cập nhật record cuối cùng
     */
    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}