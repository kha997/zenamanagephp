<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\UserSession;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UATDatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Seeding UAT environment...');
        
        // Create tenants
        $this->createTenants();
        
        // Create users with different roles
        $this->createUsers();
        
        // Create projects and tasks
        $this->createProjectsAndTasks();
        
        // Create sessions and login attempts
        $this->createSecurityTestData();
        
        // Create performance test data
        $this->createPerformanceTestData();
        
        $this->command->info('UAT environment seeded successfully!');
    }
    
    private function createTenants()
    {
        $tenants = [
            [
                'domain' => 'uat-security.test',
                'name' => 'UAT Security Test Tenant',
                'slug' => 'uat-security',
                'is_active' => true,
                'status' => 'active',
                'settings' => [
                    'timezone' => 'UTC',
                    'currency' => 'USD',
                    'language' => 'en'
                ]
            ],
            [
                'domain' => 'uat-performance.test',
                'name' => 'UAT Performance Test Tenant',
                'slug' => 'uat-performance',
                'is_active' => true,
                'status' => 'active',
                'settings' => [
                    'timezone' => 'America/New_York',
                    'currency' => 'USD',
                    'language' => 'en'
                ]
            ],
            [
                'domain' => 'uat-i18n.test',
                'name' => 'UAT i18n Test Tenant',
                'slug' => 'uat-i18n',
                'is_active' => true,
                'status' => 'active',
                'settings' => [
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'currency' => 'VND',
                    'language' => 'vi'
                ]
            ]
        ];
        
        foreach ($tenants as $tenantData) {
            Tenant::create($tenantData);
        }
        
        $this->command->info('Created 3 UAT tenants');
    }
    
    private function createUsers()
    {
        $tenant = Tenant::first();
        
        $users = [
            [
                'name' => 'UAT Super Admin',
                'email' => 'uat-superadmin@test.com',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'tenant_id' => $tenant->id,
                'email_verified_at' => now()
            ],
            [
                'name' => 'UAT Project Manager',
                'email' => 'uat-pm@test.com',
                'password' => Hash::make('password'),
                'role' => 'PM',
                'tenant_id' => $tenant->id,
                'email_verified_at' => now()
            ],
            [
                'name' => 'UAT Team Member',
                'email' => 'uat-member@test.com',
                'password' => Hash::make('password'),
                'role' => 'Member',
                'tenant_id' => $tenant->id,
                'email_verified_at' => now()
            ],
            [
                'name' => 'UAT Client',
                'email' => 'uat-client@test.com',
                'password' => Hash::make('password'),
                'role' => 'Client',
                'tenant_id' => $tenant->id,
                'email_verified_at' => now()
            ],
            [
                'name' => 'UAT Test User',
                'email' => 'uat-test@test.com',
                'password' => Hash::make('password'),
                'role' => 'Member',
                'tenant_id' => $tenant->id,
                'email_verified_at' => now()
            ]
        ];
        
        foreach ($users as $userData) {
            User::create($userData);
        }
        
        $this->command->info('Created 5 UAT users with different roles');
    }
    
    private function createProjectsAndTasks()
    {
        $tenant = Tenant::first();
        $pm = User::where('role', 'PM')->first();
        
        // Create projects
        for ($i = 1; $i <= 20; $i++) {
            $project = Project::create([
                'name' => "UAT Project {$i}",
                'code' => "UAT-PROJ-{$i}",
                'description' => "UAT test project {$i} for comprehensive testing",
                'status' => ['planning', 'active', 'on_hold', 'completed'][array_rand(['planning', 'active', 'on_hold', 'completed'])],
                'budget_total' => rand(10000, 100000),
                'start_date' => now()->subDays(rand(1, 30)),
                'end_date' => now()->addDays(rand(1, 60)),
                'tenant_id' => $tenant->id,
                'created_by' => $pm->id
            ]);
            
            // Create tasks for each project
            for ($j = 1; $j <= 10; $j++) {
                Task::create([
                    'name' => "UAT Task {$i}-{$j}",
                    'title' => "UAT Task {$i}-{$j}",
                    'description' => "UAT test task {$i}-{$j} for project testing",
                    'status' => ['pending', 'in_progress', 'completed', 'cancelled'][array_rand(['pending', 'in_progress', 'completed', 'cancelled'])],
                    'priority' => ['low', 'medium', 'high', 'urgent'][array_rand(['low', 'medium', 'high', 'urgent'])],
                    'project_id' => $project->id,
                    'assigned_to' => User::where('role', 'Member')->inRandomOrder()->first()->id,
                    'created_by' => $pm->id
                ]);
            }
        }
        
        $this->command->info('Created 20 projects with 200 tasks');
    }
    
    private function createSecurityTestData()
    {
        $tenant = Tenant::first();
        $users = User::all();
        
        // Create user sessions
        foreach ($users as $user) {
            for ($i = 0; $i < 5; $i++) {
                UserSession::create([
                    'user_id' => $user->id,
                    'session_id' => Str::random(40),
                    'ip_address' => '192.168.1.' . rand(1, 255)
                ]);
            }
        }
        
        // Create login attempts (including failed ones for brute force testing)
        foreach ($users as $user) {
            for ($i = 0; $i < 10; $i++) {
                LoginAttempt::create([
                    'email' => $user->email,
                    'ip_address' => '192.168.1.' . rand(1, 255),
                    'user_agent' => 'Mozilla/5.0 (UAT Test Browser)',
                    'status' => 'attempted',
                    'tenant_id' => $tenant->id
                ]);
            }
        }
        
        $this->command->info('Created security test data (sessions and login attempts)');
    }
    
    private function createPerformanceTestData()
    {
        // Create additional data for performance testing
        $tenant = Tenant::first();
        
        // Create more users for load testing
        for ($i = 1; $i <= 100; $i++) {
            User::create([
                'name' => "UAT Load User {$i}",
                'email' => "uat-load-{$i}@test.com",
                'password' => Hash::make('password'),
                'role' => 'Member',
                'tenant_id' => $tenant->id,
                'email_verified_at' => now()
            ]);
        }
        
        $this->command->info('Created 100 additional users for performance testing');
    }
}
