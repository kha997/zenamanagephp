<?php declare(strict_types=1);

namespace Src\RBAC\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Permission API Resource
 * Transform Permission model data for API responses
 */
class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'module' => $this->module,
            'action' => $this->action,
            'description' => $this->description,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'roles_count' => $this->whenCounted('roles'),
            
            // Computed properties
            'display_name' => $this->getDisplayName(),
            'module_label' => $this->getModuleLabel(),
            'action_label' => $this->getActionLabel(),
        ];
    }
    
    /**
     * Get display name for permission
     */
    private function getDisplayName(): string
    {
        return $this->getModuleLabel() . ' - ' . $this->getActionLabel();
    }
    
    /**
     * Get module label in Vietnamese
     */
    private function getModuleLabel(): string
    {
        $labels = [
            'project' => 'Dự án',
            'task' => 'Công việc',
            'component' => 'Thành phần',
            'document' => 'Tài liệu',
            'cr' => 'Yêu cầu thay đổi',
            'user' => 'Người dùng',
            'role' => 'Vai trò',
            'permission' => 'Quyền hạn',
            'audit' => 'Kiểm toán',
            'notification' => 'Thông báo',
            'interaction' => 'Tương tác',
            'baseline' => 'Baseline',
            'compensation' => 'Bồi thường'
        ];
        
        return $labels[$this->module] ?? $this->module;
    }
    
    /**
     * Get action label in Vietnamese
     */
    private function getActionLabel(): string
    {
        $labels = [
            'view' => 'Xem',
            'create' => 'Tạo',
            'edit' => 'Sửa',
            'delete' => 'Xóa',
            'assign' => 'Gán',
            'approve' => 'Phê duyệt',
            'export' => 'Xuất',
            'import' => 'Nhập',
            'upload' => 'Tải lên',
            'download' => 'Tải xuống'
        ];
        
        return $labels[$this->action] ?? $this->action;
    }
}