<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get default tenant (assuming tenant_id = 1 or first tenant)
        $defaultTenant = DB::table('tenants')->first();
        if (!$defaultTenant) {
            $this->command->error('No tenant found. Please run TenantSeeder first.');
            return;
        }

        $testUsers = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@zena.com',
                'role_name' => 'super_admin',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@zena.com',
                'role_name' => 'admin',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Project Manager',
                'email' => 'pm@zena.com',
                'role_name' => 'project_manager',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Designer',
                'email' => 'designer@zena.com',
                'role_name' => 'designer',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Site Engineer',
                'email' => 'site@zena.com',
                'role_name' => 'site_engineer',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'QC Engineer',
                'email' => 'qc@zena.com',
                'role_name' => 'qc_engineer',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Procurement',
                'email' => 'procurement@zena.com',
                'role_name' => 'procurement',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Finance Manager',
                'email' => 'finance@zena.com',
                'role_name' => 'finance',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Client User',
                'email' => 'client@zena.com',
                'role_name' => 'client',
                'password' => Hash::make('zena1234'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($testUsers as $userData) {
            $roleName = $userData['role_name'];
            unset($userData['role_name']); // Remove role_name from user data
            
            // Check if user already exists
            $existingUser = User::where('email', $userData['email'])->first();
            
            if (!$existingUser) {
                // Create user with tenant_id
                $userData['tenant_id'] = $defaultTenant->id;
                $user = User::create($userData);
                $this->command->info("Created user: {$userData['name']} ({$userData['email']})");
            } else {
                // Update existing user
                $existingUser->update([
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                ]);
                $user = $existingUser;
                $this->command->info("Updated user: {$userData['name']} ({$userData['email']})");
            }

            // Assign role to user
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                // Check if user already has this role
                $existingRole = DB::table('user_roles')
                    ->where('user_id', $user->id)
                    ->where('role_id', $role->id)
                    ->first();
                
                if (!$existingRole) {
                    DB::table('user_roles')->insert([
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->command->info("  -> Assigned role: {$roleName}");
                } else {
                    $this->command->info("  -> Role already assigned: {$roleName}");
                }
            } else {
                $this->command->warn("  -> Role not found: {$roleName}");
            }
        }

        $this->command->info('');
        $this->command->info('✅ Test users created/updated successfully!');
        $this->command->info('🔑 All users have password: zena1234');
        $this->command->info('');
        $this->command->info('📋 Test Users Summary:');
        foreach ($testUsers as $userData) {
            $this->command->info("  • {$userData['name']} ({$userData['email']}) - Role: {$userData['role_name']}");
        }
    }
}