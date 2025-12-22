<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SecurityTestDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds for security testing.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('🔐 Starting Security Test Database Seeding...');

        // Create security test users
        $this->createSecurityTestUsers();
        
        // Create security test data
        $this->createSecurityTestData();

        $this->command->info('✅ Security Test Database Seeding completed successfully!');
    }

    /**
     * Ensure tenants exist before creating users.
     */
    private function ensureTenantsExist()
    {
        $tenantCount = DB::table('tenants')->count();
        
        if ($tenantCount === 0) {
            // Create a default tenant for security tests
            $tenantId = Str::ulid();
            DB::table('tenants')->insert([
                'id' => $tenantId,
                'domain' => 'security.test',
                'name' => 'ZenaManage Security Test Tenant',
                'slug' => 'security-test',
                'is_active' => true,
                'status' => 'active',
                'settings' => json_encode([
                    'timezone' => 'UTC',
                    'currency' => 'USD',
                    'language' => 'en'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info('✅ Created default tenant for security tests.');
        } else {
            $this->command->info('✅ Found ' . $tenantCount . ' existing tenant(s).');
        }
    }

    /**
     * Create security test users with various roles and states
     */
    private function createSecurityTestUsers()
    {
        $this->command->info('👥 Creating security test users...');

        // Ensure tenants exist first
        $this->ensureTenantsExist();

        // Get first tenant for security tests
        $tenant = DB::table('tenants')->first();
        if (!$tenant) {
            $this->command->error('Failed to create tenant. Cannot proceed with user creation.');
            return;
        }

        $securityUsers = [
            // Brute force test user
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Brute Force Test User',
                'email' => 'bruteforce.test@security.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Session expiry test user
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Session Test User',
                'email' => 'session.test@security.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Password reset test user
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Password Reset User',
                'email' => 'passwordreset.test@security.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Multi-device session test user
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Multi Device User',
                'email' => 'multidevice.test@security.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // CSRF test user
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'CSRF Test User',
                'email' => 'csrf.test@security.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Input validation test user
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Input Validation User',
                'email' => 'inputvalidation.test@security.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Admin user for security tests
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Security Admin',
                'email' => 'securityadmin.test@security.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($securityUsers as $user) {
            // Check if user already exists
            $existingUser = DB::table('users')->where('email', $user['email'])->first();
            if (!$existingUser) {
                DB::table('users')->insert($user);
            }
        }

        $this->command->info('✅ Created ' . count($securityUsers) . ' security test users');
    }

    /**
     * Create security test data
     */
    private function createSecurityTestData()
    {
        $this->command->info('🔒 Creating security test data...');

        // Create security test projects
        $this->createSecurityTestProjects();
        
        // Create security test sessions
        $this->createSecurityTestSessions();
        
        // Create security test login attempts
        $this->createSecurityTestLoginAttempts();

        $this->command->info('✅ Security test data created');
    }

    /**
     * Create security test projects
     */
    private function createSecurityTestProjects()
    {
        $tenant = DB::table('tenants')->first();
        $adminUser = DB::table('users')->where('email', 'securityadmin.test@security.com')->first();

        if (!$adminUser) {
            $this->command->warn('Security admin user not found, skipping project creation');
            return;
        }

        $securityProjects = [
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Security Test Project 1',
                'code' => 'SEC-TEST-001',
                'description' => 'Project for testing security features',
                'status' => 'active',
                'progress' => 0,
                'start_date' => now(),
                'end_date' => now()->addMonths(3),
                'created_by' => $adminUser->id,
                'updated_by' => $adminUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Security Test Project 2',
                'code' => 'SEC-TEST-002',
                'description' => 'Another project for security testing',
                'status' => 'planning',
                'progress' => 0,
                'start_date' => now()->addDays(7),
                'end_date' => now()->addMonths(2),
                'created_by' => $adminUser->id,
                'updated_by' => $adminUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($securityProjects as $project) {
            // Check if project already exists
            $existingProject = DB::table('projects')->where('code', $project['code'])->first();
            if (!$existingProject) {
                DB::table('projects')->insert($project);
            }
        }

        $this->command->info('✅ Created ' . count($securityProjects) . ' security test projects');
    }

    /**
     * Create security test sessions
     */
    private function createSecurityTestSessions()
    {
        $sessionUser = DB::table('users')->where('email', 'session.test@security.com')->first();
        
        if (!$sessionUser) {
            $this->command->warn('Session test user not found, skipping session creation');
            return;
        }

        $testSessions = [
            [
                'user_id' => $sessionUser->id,
                'session_id' => Str::random(40),
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(5),
            ],
            [
                'user_id' => $sessionUser->id,
                'session_id' => Str::random(40),
                'ip_address' => '192.168.1.100',
                'created_at' => now()->subMinutes(10),
                'updated_at' => now(),
            ],
        ];

        foreach ($testSessions as $session) {
            DB::table('user_sessions')->insert($session);
        }

        $this->command->info('✅ Created ' . count($testSessions) . ' security test sessions');
    }

    /**
     * Create security test login attempts
     */
    private function createSecurityTestLoginAttempts()
    {
        $bruteForceUser = DB::table('users')->where('email', 'bruteforce.test@security.com')->first();
        
        if (!$bruteForceUser) {
            $this->command->warn('Brute force test user not found, skipping login attempts creation');
            return;
        }

        $loginAttempts = [];
        
        // Create failed login attempts for brute force testing
        for ($i = 1; $i <= 5; $i++) {
            $loginAttempts[] = [
                'id' => Str::ulid(),
                'email' => $bruteForceUser->email,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Security Test Browser',
                'status' => 'failed',
                'created_at' => now()->subMinutes($i * 2),
                'updated_at' => now()->subMinutes($i * 2),
            ];
        }

        foreach ($loginAttempts as $attempt) {
            DB::table('login_attempts')->insert($attempt);
        }

        $this->command->info('✅ Created ' . count($loginAttempts) . ' security test login attempts');
    }
}
