<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Role và Permission Seeder
 * 
 * Tạo dữ liệu mẫu cho hệ thống RBAC
 * Sử dụng ULID cho tất cả các bảng
 */
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo system roles - sử dụng firstOrCreate để tránh trùng lặp
        $adminRole = Role::firstOrCreate(
            ['name' => 'System Admin'],
            [
                'scope' => 'system',
                'allow_override' => true,
                'description' => 'Quản trị viên hệ thống',
                'is_active' => true
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'Project Manager'],
            [
                'scope' => 'system',
                'allow_override' => true,
                'description' => 'Quản lý dự án',
                'is_active' => true
            ]
        );

        $memberRole = Role::firstOrCreate(
            ['name' => 'Project Member'],
            [
                'scope' => 'system',
                'allow_override' => false,
                'description' => 'Thành viên dự án',
                'is_active' => true
            ]
        );

        // Tạo permissions - sử dụng firstOrCreate để tránh trùng lặp
        $permissions = [
            ['code' => 'project.create', 'module' => 'project', 'action' => 'create', 'description' => 'Create Project'],
            ['code' => 'project.read', 'module' => 'project', 'action' => 'read', 'description' => 'Read Project'],
            ['code' => 'project.update', 'module' => 'project', 'action' => 'update', 'description' => 'Update Project'],
            ['code' => 'project.delete', 'module' => 'project', 'action' => 'delete', 'description' => 'Delete Project'],
            ['code' => 'user.manage', 'module' => 'user', 'action' => 'manage', 'description' => 'Manage Users'],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate(
                ['code' => $permData['code']],
                [
                    'module' => $permData['module'],
                    'action' => $permData['action'],
                    'description' => $permData['description'],
                    'is_active' => true
                ]
            );
        }
    }
}