<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin role if not exists
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with no restrictions',
                'is_system_role' => true,
                'is_active' => true,
            ]
        );

        // Create super admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@zenamanage.com'],
            [
                'name' => 'Super Administrator',
                'email' => 'admin@zenamanage.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
                'tenant_id' => null, // Super admin không thuộc tenant nào
            ]
        );

        // Assign super admin role to user
        if (!$superAdmin->hasRole('super_admin')) {
            $superAdmin->roles()->attach($superAdminRole->id);
        }

        $this->command->info('Super Admin created successfully!');
        $this->command->info('Email: admin@zenamanage.com');
        $this->command->info('Password: password');
    }
}