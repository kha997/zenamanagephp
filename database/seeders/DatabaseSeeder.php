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
            UserRoleSeeder::class,
            
            // Test Data (comprehensive)
            TestDataSeeder::class,
            
            // Project Structure
            ProjectSeeder::class,
            TaskSeeder::class,
            
            // Business Domains
            ClientSeeder::class,
            QuoteSeeder::class,
            
            // Feature-Specific Data
            // NotificationSeeder::class,
            // DocumentSeeder::class,
        ]);
    }
}