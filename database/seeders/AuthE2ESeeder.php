<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder for E2E Authentication Tests
 * 
 * Creates canonical test users for auth test scenarios
 */
class AuthE2ESeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create tenants if they don't exist
        $zenaTenant = Tenant::firstOrCreate(
            ['slug' => 'zena'],
            ['name' => 'ZENA Company', 'is_active' => true]
        );
        
        $ttfTenant = Tenant::firstOrCreate(
            ['slug' => 'ttf'],
            ['name' => 'TTF Company', 'is_active' => true]
        );
        
        // Define canonical users
        $users = [
            [
                'email' => 'admin@zena.test',
                'name' => 'Admin User',
                'tenant_id' => $zenaTenant->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'two_factor_secret' => 'test',
                'role' => 'admin',
            ],
            [
                'email' => 'manager@zena.test',
                'name' => 'Manager User',
                'tenant_id' => $zenaTenant->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'role' => 'manager',
            ],
            [
                'email' => 'member@zena.test',
                'name' => 'Member User',
                'tenant_id' => $zenaTenant->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
                'role' => 'member',
            ],
            [
                'email' => 'locked@zena.test',
                'name' => 'Locked User',
                'tenant_id' => $zenaTenant->id,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => false, // Locked
                'role' => 'member',
            ],
            [
                'email' => 'unverified@zena.test',
                'name' => 'Unverified User',
                'tenant_id' => $zenaTenant->id,
                'password' => Hash::make('password'),
                'email_verified_at' => null, // Unverified
                'is_active' => true,
                'role' => 'member',
            ],
        ];
        
        // Create users
        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);
            
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            
            // Assign role if Spatie permissions is used
            if (method_exists($user, 'assignRole')) {
                $user->assignRole($role);
            }
        }
    }
}

