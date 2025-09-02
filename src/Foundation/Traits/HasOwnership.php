<?php
declare(strict_types=1);

namespace Src\Foundation\Traits;

use Src\Foundation\PermissionContext;

/**
 * Trait để tự động quản lý ownership (tenant_id, project_id, created_by, updated_by)
 */
trait HasOwnership {
    /**
     * Boot trait
     * 
     * @return void
     */
    protected static function bootHasOwnership(): void {
        static::creating(function ($model) {
            // Kiểm tra xem context có được thiết lập hay không
            if (PermissionContext::getContext() !== null) {
                if (!isset($model->tenant_id)) {
                    $tenantId = PermissionContext::getTenantId();
                    if ($tenantId) {
                        $model->tenant_id = $tenantId;
                    }
                }
                
                if (!isset($model->project_id)) {
                    $projectId = PermissionContext::getProjectId();
                    if ($projectId) {
                        $model->project_id = $projectId;
                    }
                }
                
                if (!isset($model->created_by)) {
                    $userId = PermissionContext::getUserId();
                    if ($userId) {
                        $model->created_by = $userId;
                    }
                }
                
                if (!isset($model->updated_by)) {
                    $userId = PermissionContext::getUserId();
                    if ($userId) {
                        $model->updated_by = $userId;
                    }
                }
            }
        });
        
        static::updating(function ($model) {
            // Kiểm tra xem context có được thiết lập hay không
            if (PermissionContext::getContext() !== null) {
                $userId = PermissionContext::getUserId();
                if ($userId) {
                    $model->updated_by = $userId;
                }
            }
        });
    }
    
    /**
     * Scope để lọc theo tenant
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tenantId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTenant($query, string $tenantId) {
        return $query->where('tenant_id', $tenantId);
    }
    
    /**
     * Scope để lọc theo project
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $projectId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForProject($query, string $projectId) {
        return $query->where('project_id', $projectId);
    }
    
    /**
     * Scope để lọc theo người tạo
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCreatedBy($query, string $userId) {
        return $query->where('created_by', $userId);
    }
}