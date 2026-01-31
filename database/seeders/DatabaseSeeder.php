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
        // Chạy theo thứ tự: Tenant -> User -> RBAC -> Project -> Features
        $this->call([
            // Core Data
            TenantSeeder::class,
            UserSeeder::class,
            
            // RBAC System
            RoleSeeder::class,
            PermissionSeeder::class,
            ZenaPermissionsSeeder::class,
            ZenaAdminRolePermissionSeeder::class,
            UserRoleSeeder::class,
            
            // Sidebar Configuration
            // SidebarConfigSeeder::class, // Commented out - table doesn't exist yet
            
            // Test Users
            // TestUsersSeeder::class, // Commented out - needs table name fix
            
            // Project Structure
            // WorkTemplateSeeder::class, // Commented out - may have issues
            // ProjectSeeder::class, // Commented out - may have issues
            // ComponentSeeder::class, // Commented out - may have issues
            // TaskSeeder::class, // Commented out - may have issues
            
            // Feature-Specific Data
            // InteractionLogSeeder::class, // Commented out - may have issues
            // NotificationSeeder::class, // Commented out - may have issues
            // DocumentSeeder::class, // Commented out - may have issues
            // ChangeRequestSeeder::class, // Commented out - may have issues
            // BaselineSeeder::class, // Commented out - may have issues
            // CompensationSeeder::class, // Commented out - may have issues
        ]);
    }
}
