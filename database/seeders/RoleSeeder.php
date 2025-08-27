<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
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
            ['name' => 'System Admin', 'scope' => 'system'],
            ['description' => 'Quản trị viên hệ thống']
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'Project Manager', 'scope' => 'project'],
            ['description' => 'Quản lý dự án']
        );

        $memberRole = Role::firstOrCreate(
            ['name' => 'Project Member', 'scope' => 'project'],
            ['description' => 'Thành viên dự án']
        );

        // Tạo permissions - sử dụng firstOrCreate để tránh trùng lặp
        $permissions = [
            ['code' => 'project.create', 'module' => 'project', 'action' => 'create'],
            ['code' => 'project.read', 'module' => 'project', 'action' => 'read'],
            ['code' => 'project.update', 'module' => 'project', 'action' => 'update'],
            ['code' => 'project.delete', 'module' => 'project', 'action' => 'delete'],
            ['code' => 'user.manage', 'module' => 'user', 'action' => 'manage'],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate(
                ['code' => $permData['code']],
                [
                    'module' => $permData['module'],
                    'action' => $permData['action']
                ]
            );
        }
    }
}