<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\RBAC\Models\Permission;
use Src\RBAC\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permission Seeder
 * 
 * Tạo đầy đủ permissions cho hệ thống và gán cho roles
 */
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Project Management
            ['code' => 'project.create', 'module' => 'project', 'action' => 'create', 'description' => 'Tạo dự án mới'],
            ['code' => 'project.read', 'module' => 'project', 'action' => 'read', 'description' => 'Xem thông tin dự án'],
            ['code' => 'project.update', 'module' => 'project', 'action' => 'update', 'description' => 'Cập nhật dự án'],
            ['code' => 'project.delete', 'module' => 'project', 'action' => 'delete', 'description' => 'Xóa dự án'],
            
            // Task Management
            ['code' => 'task.create', 'module' => 'task', 'action' => 'create', 'description' => 'Tạo công việc'],
            ['code' => 'task.read', 'module' => 'task', 'action' => 'read', 'description' => 'Xem công việc'],
            ['code' => 'task.update', 'module' => 'task', 'action' => 'update', 'description' => 'Cập nhật công việc'],
            ['code' => 'task.delete', 'module' => 'task', 'action' => 'delete', 'description' => 'Xóa công việc'],
            ['code' => 'task.assign', 'module' => 'task', 'action' => 'assign', 'description' => 'Phân công công việc'],
            
            // Component Management
            ['code' => 'component.create', 'module' => 'component', 'action' => 'create', 'description' => 'Tạo thành phần'],
            ['code' => 'component.read', 'module' => 'component', 'action' => 'read', 'description' => 'Xem thành phần'],
            ['code' => 'component.update', 'module' => 'component', 'action' => 'update', 'description' => 'Cập nhật thành phần'],
            ['code' => 'component.delete', 'module' => 'component', 'action' => 'delete', 'description' => 'Xóa thành phần'],
            
            // Document Management
            ['code' => 'document.create', 'module' => 'document', 'action' => 'create', 'description' => 'Tạo tài liệu'],
            ['code' => 'document.read', 'module' => 'document', 'action' => 'read', 'description' => 'Xem tài liệu'],
            ['code' => 'document.update', 'module' => 'document', 'action' => 'update', 'description' => 'Cập nhật tài liệu'],
            ['code' => 'document.delete', 'module' => 'document', 'action' => 'delete', 'description' => 'Xóa tài liệu'],
            ['code' => 'document.approve', 'module' => 'document', 'action' => 'approve', 'description' => 'Phê duyệt tài liệu'],
            
            // Change Request Management
            ['code' => 'change_request.create', 'module' => 'change_request', 'action' => 'create', 'description' => 'Tạo yêu cầu thay đổi'],
            ['code' => 'change_request.read', 'module' => 'change_request', 'action' => 'read', 'description' => 'Xem yêu cầu thay đổi'],
            ['code' => 'change_request.update', 'module' => 'change_request', 'action' => 'update', 'description' => 'Cập nhật yêu cầu thay đổi'],
            ['code' => 'change_request.approve', 'module' => 'change_request', 'action' => 'approve', 'description' => 'Phê duyệt yêu cầu thay đổi'],
            ['code' => 'change_request.reject', 'module' => 'change_request', 'action' => 'reject', 'description' => 'Từ chối yêu cầu thay đổi'],
            
            // User Management
            ['code' => 'user.create', 'module' => 'user', 'action' => 'create', 'description' => 'Tạo người dùng'],
            ['code' => 'user.read', 'module' => 'user', 'action' => 'read', 'description' => 'Xem thông tin người dùng'],
            ['code' => 'user.update', 'module' => 'user', 'action' => 'update', 'description' => 'Cập nhật người dùng'],
            ['code' => 'user.delete', 'module' => 'user', 'action' => 'delete', 'description' => 'Xóa người dùng'],
            ['code' => 'user.manage_roles', 'module' => 'user', 'action' => 'manage_roles', 'description' => 'Quản lý vai trò người dùng'],
            
            // System Administration
            ['code' => 'system.admin', 'module' => 'system', 'action' => 'admin', 'description' => 'Quản trị hệ thống'],
            ['code' => 'system.settings', 'module' => 'system', 'action' => 'settings', 'description' => 'Cấu hình hệ thống'],
            ['code' => 'system.audit', 'module' => 'system', 'action' => 'audit', 'description' => 'Xem nhật ký hệ thống'],
            
            // Notification Management
            ['code' => 'notification.read', 'module' => 'notification', 'action' => 'read', 'description' => 'Xem thông báo'],
            ['code' => 'notification.manage_rules', 'module' => 'notification', 'action' => 'manage_rules', 'description' => 'Quản lý quy tắc thông báo'],
            
            // Interaction Logs
            ['code' => 'interaction_log.create', 'module' => 'interaction_log', 'action' => 'create', 'description' => 'Tạo nhật ký tương tác'],
            ['code' => 'interaction_log.read', 'module' => 'interaction_log', 'action' => 'read', 'description' => 'Xem nhật ký tương tác'],
            ['code' => 'interaction_log.approve', 'module' => 'interaction_log', 'action' => 'approve', 'description' => 'Phê duyệt nhật ký cho khách hàng'],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate(
                ['code' => $permData['code']],
                $permData
            );
        }

        // Gán permissions cho roles
        $this->assignPermissionsToRoles();
    }

    /**
     * Gán permissions cho các roles
     */
    private function assignPermissionsToRoles(): void
    {
        $adminRole = Role::where('name', 'System Admin')->first();
        $managerRole = Role::where('name', 'Project Manager')->first();
        $memberRole = Role::where('name', 'Project Member')->first();

        if ($adminRole) {
            // Admin có tất cả permissions
            $allPermissions = Permission::all();
            $adminRole->permissions()->sync($allPermissions->pluck('id'));
        }

        if ($managerRole) {
            // Manager có permissions quản lý dự án
            $managerPermissions = Permission::whereIn('code', [
                'project.create', 'project.read', 'project.update',
                'task.create', 'task.read', 'task.update', 'task.assign',
                'component.create', 'component.read', 'component.update',
                'document.create', 'document.read', 'document.update', 'document.approve',
                'change_request.create', 'change_request.read', 'change_request.approve', 'change_request.reject',
                'user.read', 'user.manage_roles',
                'notification.read', 'notification.manage_rules',
                'interaction_log.create', 'interaction_log.read', 'interaction_log.approve'
            ])->get();
            $managerRole->permissions()->sync($managerPermissions->pluck('id'));
        }

        if ($memberRole) {
            // Member có permissions cơ bản
            $memberPermissions = Permission::whereIn('code', [
                'project.read',
                'task.read', 'task.update',
                'component.read',
                'document.read', 'document.create',
                'change_request.create', 'change_request.read',
                'notification.read',
                'interaction_log.create', 'interaction_log.read'
            ])->get();
            $memberRole->permissions()->sync($memberPermissions->pluck('id'));
        }
    }
}