<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class E2EDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting E2E Database Seeding...');

        // Create tenant for E2E testing
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'E2E Test Tenant',
            'domain' => 'e2e.local',
            'slug' => 'e2e-test',
            'is_active' => true,
            'status' => 'active',
            'settings' => json_encode([
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'language' => 'vi'
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create admin user for smoke tests
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@zena.local'],
            [
                'name' => 'Admin User',
                'email' => 'admin@zena.local',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'tenant_id' => $tenantId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Create sample project
        DB::table('projects')->updateOrInsert(
            ['name' => 'Test Project'],
            [
                'name' => 'Test Project',
                'description' => 'Sample project for E2E testing',
                'status' => 'active',
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        $this->command->info('✅ E2E Database Seeding completed successfully!');
        $this->command->info("📊 Created tenant ID: {$tenantId}");
        $this->command->info('👤 Admin user: admin@zena.local / password');
    }
}