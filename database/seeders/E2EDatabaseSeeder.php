<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class E2EDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Round 161: Creates E2E test user with credentials:
     * - Email: admin@zena.local
     * - Password: password (bcrypt hashed)
     * - Role: super_admin
     * - Has tenant_id and user_tenants pivot record with role=owner
     * 
     * This seeder is used by both global-setup.ts and global-auth-setup.ts
     * to ensure E2E tests have a consistent user for login.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting E2E Database Seeding...');

        // Create tenant for E2E testing
        $tenantId = \Str::ulid();
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'E2E Test Tenant',
            'domain' => 'e2e.local',
            'slug' => 'e2e-test',
            'is_active' => true,
            'status' => 'active',
            'plan' => 'basic',
            'settings' => json_encode([
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'language' => 'vi'
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create admin user for smoke tests
        // First, check if user already exists
        $existingUser = DB::table('users')->where('email', 'admin@zena.local')->first();
        $adminUserId = $existingUser?->id ?? \Str::ulid();
        
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@zena.local'],
            [
                'id' => $adminUserId,
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
        
        // Get the actual user ID (in case user already existed)
        $actualUserId = DB::table('users')->where('email', 'admin@zena.local')->value('id');
        
        // Create user_tenants pivot record so MeService can get current_tenant_role
        // Use updateOrInsert to ensure record exists (will update if exists, insert if not)
        DB::table('user_tenants')->updateOrInsert(
            [
                'user_id' => $actualUserId,
                'tenant_id' => $tenantId,
            ],
            [
                'id' => \Str::ulid(),
                'role' => 'owner', // Give admin full tenant permissions
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Create sample projects for E2E testing (E2E-001, E2E-002)
        DB::table('projects')->updateOrInsert(
            ['code' => 'E2E-001'],
            [
                'id' => \Str::ulid(),
                'code' => 'E2E-001',
                'name' => 'E2E Test Project 1',
                'description' => 'Project for E2E testing - Basic functionality',
                'status' => 'active',
                'tenant_id' => $tenantId,
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
        
        DB::table('projects')->updateOrInsert(
            ['code' => 'E2E-002'],
            [
                'id' => \Str::ulid(),
                'code' => 'E2E-002',
                'name' => 'E2E Test Project 2',
                'description' => 'Project for E2E testing - Advanced features',
                'status' => 'planning',
                'tenant_id' => $tenantId,
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Round 169: Create at least one template set for apply-template E2E tests
        // This ensures the happy path scenario can actually select and apply a template
        // Use the same tenant_id as the E2E tenant so it's visible to the E2E user
        $templateSetId = \Str::ulid();
        DB::table('template_sets')->updateOrInsert(
            ['code' => 'E2E-DEMO-TEMPLATE', 'tenant_id' => $tenantId],
            [
                'id' => $templateSetId,
                'tenant_id' => $tenantId, // Tenant-specific template set - matches E2E tenant
                'code' => 'E2E-DEMO-TEMPLATE',
                'name' => 'E2E Demo Template Set',
                'description' => 'Template set created specifically for apply-template E2E tests',
                'version' => '1.0',
                'is_active' => true,
                'is_global' => false,
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Create a phase for the template set (required for template_tasks)
        $phaseId = \Str::ulid();
        DB::table('template_phases')->insert([
            'id' => $phaseId,
            'set_id' => $templateSetId,
            'code' => 'E2E-PHASE-001',
            'name' => 'E2E Test Phase',
            'order_index' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create a discipline for the template set (required for template_tasks)
        $disciplineId = \Str::ulid();
        DB::table('template_disciplines')->insert([
            'id' => $disciplineId,
            'set_id' => $templateSetId,
            'code' => 'E2E-DISC-001',
            'name' => 'E2E Test Discipline',
            'order_index' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create at least one template task inside the set so it's not empty
        $templateTaskId = \Str::ulid();
        DB::table('template_tasks')->insert([
            'id' => $templateTaskId,
            'set_id' => $templateSetId,
            'phase_id' => $phaseId,
            'discipline_id' => $disciplineId,
            'code' => 'E2E-TASK-001',
            'name' => 'E2E Test Task 1',
            'description' => 'Sample task for E2E template testing',
            'order_index' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->command->info('✅ E2E Database Seeding completed successfully!');
        $this->command->info("📊 Created tenant ID: {$tenantId}");
        $this->command->info('👤 Admin user: admin@zena.local / password');
        $this->command->info("📋 Created template set ID: {$templateSetId} (E2E Demo Template Set)");
    }
}