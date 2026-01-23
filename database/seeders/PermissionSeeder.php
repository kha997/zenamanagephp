<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
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
            ['code' => 'project.archive', 'module' => 'project', 'action' => 'archive', 'description' => 'Lưu trữ dự án'],
            ['code' => 'project.restore', 'module' => 'project', 'action' => 'restore', 'description' => 'Khôi phục dự án'],
            ['code' => 'project.duplicate', 'module' => 'project', 'action' => 'duplicate', 'description' => 'Nhân bản dự án'],
            ['code' => 'project.manage_team', 'module' => 'project', 'action' => 'manage_team', 'description' => 'Quản lý nhóm dự án'],
            ['code' => 'project.view_budget', 'module' => 'project', 'action' => 'view_budget', 'description' => 'Xem ngân sách dự án'],
            ['code' => 'project.edit_budget', 'module' => 'project', 'action' => 'edit_budget', 'description' => 'Chỉnh sửa ngân sách dự án'],
            ['code' => 'project.view_files', 'module' => 'project', 'action' => 'view_files', 'description' => 'Xem tài liệu dự án'],
            ['code' => 'project.upload_files', 'module' => 'project', 'action' => 'upload_files', 'description' => 'Tải tài liệu lên dự án'],
            ['code' => 'project.assign', 'module' => 'project', 'action' => 'assign', 'description' => 'Phân công dự án'],
            ['code' => 'project.write', 'module' => 'project', 'action' => 'write', 'description' => 'Tạo hoặc cập nhật dự án'],

            // Task Management
            ['code' => 'task.create', 'module' => 'task', 'action' => 'create', 'description' => 'Tạo công việc'],
            ['code' => 'task.read', 'module' => 'task', 'action' => 'read', 'description' => 'Xem công việc'],
            ['code' => 'task.update', 'module' => 'task', 'action' => 'update', 'description' => 'Cập nhật công việc'],
            ['code' => 'task.delete', 'module' => 'task', 'action' => 'delete', 'description' => 'Xóa công việc'],
            ['code' => 'task.assign', 'module' => 'task', 'action' => 'assign', 'description' => 'Phân công công việc'],
            ['code' => 'task.comment', 'module' => 'task', 'action' => 'comment', 'description' => 'Bình luận công việc'],
            ['code' => 'task.attach_files', 'module' => 'task', 'action' => 'attach_files', 'description' => 'Đính kèm tài liệu cho công việc'],
            ['code' => 'task.change_status', 'module' => 'task', 'action' => 'change_status', 'description' => 'Thay đổi trạng thái công việc'],
            ['code' => 'task.view_time_tracking', 'module' => 'task', 'action' => 'view_time_tracking', 'description' => 'Xem thời gian thực hiện công việc'],
            ['code' => 'task.edit_time_tracking', 'module' => 'task', 'action' => 'edit_time_tracking', 'description' => 'Chỉnh sửa thời gian công việc'],
            ['code' => 'task.write', 'module' => 'task', 'action' => 'write', 'description' => 'Tạo hoặc cập nhật công việc'],

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
            ['code' => 'document.upload_files', 'module' => 'document', 'action' => 'upload_files', 'description' => 'Tải tài liệu lên hệ thống'],

            // Change Request Management
            ['code' => 'change_request.create', 'module' => 'change_request', 'action' => 'create', 'description' => 'Tạo yêu cầu thay đổi'],
            ['code' => 'change_request.read', 'module' => 'change_request', 'action' => 'read', 'description' => 'Xem yêu cầu thay đổi'],
            ['code' => 'change_request.update', 'module' => 'change_request', 'action' => 'update', 'description' => 'Cập nhật yêu cầu thay đổi'],
            ['code' => 'change_request.delete', 'module' => 'change_request', 'action' => 'delete', 'description' => 'Xóa yêu cầu thay đổi'],
            ['code' => 'change_request.approve', 'module' => 'change_request', 'action' => 'approve', 'description' => 'Phê duyệt yêu cầu thay đổi'],
            ['code' => 'change_request.reject', 'module' => 'change_request', 'action' => 'reject', 'description' => 'Từ chối yêu cầu thay đổi'],
            ['code' => 'change_request.view', 'module' => 'change_request', 'action' => 'view', 'description' => 'Xem yêu cầu thay đổi (alias)'],
            ['code' => 'change_request.edit', 'module' => 'change_request', 'action' => 'edit', 'description' => 'Chỉnh sửa yêu cầu thay đổi (alias)'],
            ['code' => 'change_request.submit', 'module' => 'change_request', 'action' => 'submit', 'description' => 'Gửi yêu cầu thay đổi'],
            ['code' => 'change_request.stats', 'module' => 'change_request', 'action' => 'stats', 'description' => 'Xem số liệu yêu cầu thay đổi'],

            // RFI Management
            ['code' => 'rfi.read', 'module' => 'rfi', 'action' => 'read', 'description' => 'Xem RFI'],
            ['code' => 'rfi.create', 'module' => 'rfi', 'action' => 'create', 'description' => 'Tạo RFI'],
            ['code' => 'rfi.answer', 'module' => 'rfi', 'action' => 'answer', 'description' => 'Trả lời RFI'],
            ['code' => 'rfi.assign', 'module' => 'rfi', 'action' => 'assign', 'description' => 'Phân công RFI'],

            // Submittal Management
            ['code' => 'submittal.read', 'module' => 'submittal', 'action' => 'read', 'description' => 'Xem hồ sơ submittal'],
            ['code' => 'submittal.create', 'module' => 'submittal', 'action' => 'create', 'description' => 'Tạo hồ sơ submittal'],
            ['code' => 'submittal.approve', 'module' => 'submittal', 'action' => 'approve', 'description' => 'Phê duyệt submittal'],
            ['code' => 'submittal.review', 'module' => 'submittal', 'action' => 'review', 'description' => 'Xem xét submittal'],

            // Inspection Management
            ['code' => 'inspection.read', 'module' => 'inspection', 'action' => 'read', 'description' => 'Xem kết quả kiểm tra'],

            // Dashboard
            ['code' => 'dashboard.view', 'module' => 'dashboard', 'action' => 'view', 'description' => 'Xem bảng điều khiển'],

            // Admin Management
            ['code' => 'admin.user.manage', 'module' => 'admin', 'action' => 'user.manage', 'description' => 'Quản lý người dùng hệ thống'],
            ['code' => 'admin.role.manage', 'module' => 'admin', 'action' => 'role.manage', 'description' => 'Quản lý vai trò hệ thống'],
            ['code' => 'admin.system.manage', 'module' => 'admin', 'action' => 'system.manage', 'description' => 'Quản trị hệ thống'],
            ['code' => 'admin.sidebar.manage', 'module' => 'admin', 'action' => 'sidebar.manage', 'description' => 'Xem và cập nhật thanh điều hướng'],

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
                'project.create', 'project.read', 'project.update', 'project.delete',
                'project.manage_team', 'project.view_budget', 'project.edit_budget',
                'project.view_files', 'project.upload_files', 'project.assign', 'project.write',
                'task.create', 'task.read', 'task.update', 'task.delete', 'task.assign',
                'task.comment', 'task.attach_files', 'task.change_status', 'task.view_time_tracking', 'task.edit_time_tracking', 'task.write',
                'component.create', 'component.read', 'component.update',
                'document.create', 'document.read', 'document.update', 'document.delete', 'document.approve', 'document.upload_files',
                'change_request.create', 'change_request.read', 'change_request.update', 'change_request.delete',
                'change_request.approve', 'change_request.reject', 'change_request.submit', 'change_request.stats',
                'rfi.read', 'rfi.create', 'rfi.answer', 'rfi.assign',
                'submittal.read', 'submittal.create', 'submittal.approve', 'submittal.review',
                'inspection.read',
                'dashboard.view',
                'admin.user.manage', 'admin.role.manage', 'admin.system.manage', 'admin.sidebar.manage',
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
                'task.read', 'task.update', 'task.comment', 'task.attach_files',
                'component.read',
                'document.read', 'document.create', 'document.upload_files',
                'change_request.create', 'change_request.read',
                'notification.read',
                'interaction_log.create', 'interaction_log.read',
                'dashboard.view'
            ])->get();
            $memberRole->permissions()->sync($memberPermissions->pluck('id'));
        }
    }
}
