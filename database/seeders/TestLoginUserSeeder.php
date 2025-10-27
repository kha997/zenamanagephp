<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Test Login User Seeder
 * 
 * Creates a test user for login testing (test@example.com)
 * Used for manual testing and automated test scripts
 */
class TestLoginUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔐 Creating test login user...');

        // Get or create a tenant
        $tenant = Tenant::withoutGlobalScopes()->first();
        
        if (!$tenant) {
            $this->command->warn('No tenant found. Creating default tenant...');
            $tenant = Tenant::create([
                'domain' => 'test.local',
                'name' => 'Test Tenant',
                'slug' => 'test-tenant',
                'is_active' => true,
                'status' => 'active',
                'settings' => [
                    'timezone' => 'UTC',
                    'currency' => 'USD',
                    'language' => 'en'
                ]
            ]);
            $this->command->info('✅ Created tenant: Test Tenant');
        }

        // Check if user already exists
        $user = User::where('email', 'test@example.com')->first();

        if ($user) {
            // Update existing user
            $user->update([
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $this->command->info('✅ Updated test user: test@example.com');
        } else {
            // Create new user
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'role' => 'member',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $this->command->info('✅ Created test user: test@example.com');
        }

        $this->command->info('');
        $this->command->info('📋 Test Login Credentials:');
        $this->command->info('   Email: test@example.com');
        $this->command->info('   Password: password');
        $this->command->info('   API Endpoint: POST /api/v1/auth/login');
        $this->command->info('');
    }
}

