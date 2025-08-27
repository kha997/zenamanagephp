<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Main Database Seeder
 * 
 * Điều phối việc chạy tất cả các seeder khác
 * Đảm bảo thứ tự chạy đúng để tránh lỗi foreign key
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Chạy theo thứ tự: Tenant -> User -> Project -> RBAC
        $this->call([
            TenantSeeder::class,
            UserSeeder::class,
            RoleSeeder::class,
            ProjectSeeder::class,
            ComponentSeeder::class,        // Thêm ComponentSeeder
            TaskSeeder::class,             // Thêm TaskSeeder
            WorkTemplateSeeder::class,     // Thêm WorkTemplateSeeder
            UserRoleSeeder::class,
        ]);
    }
}