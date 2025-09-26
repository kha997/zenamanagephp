<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo tenant
        $tenant = Tenant::create([
            'name' => 'ABC Corporation',
            'domain' => 'abc-corp.zena.com',
            'is_active' => true,
        ]);

        // Create roles
        $roles = [
            ['name' => 'super_admin', 'scope' => 'system', 'description' => 'Super Administrator'],
            ['name' => 'admin', 'scope' => 'tenant', 'description' => 'Tenant Administrator'],
            ['name' => 'project_manager', 'scope' => 'tenant', 'description' => 'Project Manager'],
            ['name' => 'designer', 'scope' => 'tenant', 'description' => 'Designer'],
            ['name' => 'site_engineer', 'scope' => 'tenant', 'description' => 'Site Engineer'],
            ['name' => 'qc_engineer', 'scope' => 'tenant', 'description' => 'QC Engineer'],
            ['name' => 'procurement', 'scope' => 'tenant', 'description' => 'Procurement'],
            ['name' => 'finance', 'scope' => 'tenant', 'description' => 'Finance'],
            ['name' => 'client', 'scope' => 'tenant', 'description' => 'Client'],
        ];

        foreach ($roles as $roleData) {
            Role::create($roleData);
        }

        // Create super admin user
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@zena.com',
            'password' => Hash::make('zena1234'),
            'tenant_id' => null, // Super admin không thuộc tenant nào
            'is_active' => true,
        ]);

        // Assign super admin role
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $superAdmin->roles()->attach($superAdminRole);

        // Create tenant users
        $tenantUsers = [
            [
                'name' => 'John Doe',
                'email' => 'pm@zena.com',
                'role' => 'project_manager',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'designer@zena.com',
                'role' => 'designer',
            ],
            [
                'name' => 'Mike Johnson',
                'email' => 'site@zena.com',
                'role' => 'site_engineer',
            ],
            [
                'name' => 'Sarah Wilson',
                'email' => 'qc@zena.com',
                'role' => 'qc_engineer',
            ],
            [
                'name' => 'David Brown',
                'email' => 'procurement@zena.com',
                'role' => 'procurement',
            ],
            [
                'name' => 'Lisa Davis',
                'email' => 'finance@zena.com',
                'role' => 'finance',
            ],
            [
                'name' => 'Client User',
                'email' => 'client@zena.com',
                'role' => 'client',
            ],
        ];

        foreach ($tenantUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('zena1234'),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);

            // Assign role
            $role = Role::where('name', $userData['role'])->first();
            $user->roles()->attach($role);
        }

        $this->command->info('Demo users and roles created successfully!');
        $this->command->info('Super Admin: superadmin@zena.com / zena1234');
        $this->command->info('Tenant Users: pm@zena.com, designer@zena.com, etc. / zena1234');
    }
}
