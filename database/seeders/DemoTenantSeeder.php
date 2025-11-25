<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DemoTenantSeeder
 * 
 * Creates demo data for pilot testing:
 * - 1 tenant: "Zena Demo"
 * - 5 users: Admin, PM, Designer, QC, Member
 * - Assigns appropriate roles and permissions
 */
class DemoTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating demo tenant and users...');

        // Create or get demo tenant
        $tenant = Tenant::firstOrCreate(
            ['name' => 'Zena Demo'],
            [
                'slug' => 'zena-demo',
                'is_active' => true,
                'status' => 'active',
            ]
        );

        $this->command->info("Tenant created: {$tenant->name} (ID: {$tenant->id})");

        // Create users with different roles
        $users = [
            [
                'name' => 'Demo Admin',
                'email' => 'admin@zena-demo.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'name' => 'Demo PM',
                'email' => 'pm@zena-demo.com',
                'password' => Hash::make('password123'),
                'role' => 'pm',
                'is_active' => true,
            ],
            [
                'name' => 'Demo Designer',
                'email' => 'designer@zena-demo.com',
                'password' => Hash::make('password123'),
                'role' => 'member', // Designer is a member with design permissions
                'is_active' => true,
            ],
            [
                'name' => 'Demo QC',
                'email' => 'qc@zena-demo.com',
                'password' => Hash::make('password123'),
                'role' => 'member', // QC is a member with QC permissions
                'is_active' => true,
            ],
            [
                'name' => 'Demo Member',
                'email' => 'member@zena-demo.com',
                'password' => Hash::make('password123'),
                'role' => 'member',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                [
                    'email' => $userData['email'],
                ],
                array_merge($userData, [
                    'tenant_id' => $tenant->id,
                    'email_verified_at' => now(),
                ])
            );

            $this->command->info("User created: {$user->name} ({$user->email}) - Role: {$user->role}");
        }

        $this->command->info('Demo tenant and users created successfully!');
        $this->command->info('');
        $this->command->info('Login credentials:');
        $this->command->info('  Admin:    admin@zena-demo.com / password123');
        $this->command->info('  PM:       pm@zena-demo.com / password123');
        $this->command->info('  Designer: designer@zena-demo.com / password123');
        $this->command->info('  QC:       qc@zena-demo.com / password123');
        $this->command->info('  Member:   member@zena-demo.com / password123');
    }
}

