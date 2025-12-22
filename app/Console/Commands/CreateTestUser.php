<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    protected $signature = 'test:create-user';
    protected $description = 'Create a test user for development';

    public function handle()
    {
        // Create or get tenant
        $tenant = Tenant::firstOrCreate(
            ['id' => 'test-tenant-1'],
            [
                'name' => 'Test Tenant',
                'domain' => 'test.local',
                'status' => 'active'
            ]
        );

        // Create test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'id' => 'test-user-1',
                'name' => 'Test User',
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'role' => 'project_manager',
                'status' => 'active',
                'is_active' => true,
                'email_verified' => true,
                'email_verified_at' => now()
            ]
        );

        $this->info("Test user created:");
        $this->info("Email: test@example.com");
        $this->info("Password: password");
        $this->info("Tenant: {$tenant->name}");

        return 0;
    }
}