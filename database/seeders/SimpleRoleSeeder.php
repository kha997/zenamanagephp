<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class SimpleRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'scope' => 'system',
                'allow_override' => true,
                'description' => 'Super Administrator - Full system access',
            ],
            [
                'name' => 'admin',
                'scope' => 'system',
                'allow_override' => false,
                'description' => 'Administrator - System management',
            ],
            [
                'name' => 'project_manager',
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ],
            [
                'name' => 'designer',
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Designer - Design and creative work',
            ],
            [
                'name' => 'site_engineer',
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Site Engineer - On-site engineering',
            ],
            [
                'name' => 'qc_engineer',
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'QC Engineer - Quality control',
            ],
            [
                'name' => 'procurement',
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Procurement - Material and vendor management',
            ],
            [
                'name' => 'finance',
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Finance Manager - Financial management',
            ],
            [
                'name' => 'client',
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Client - Project stakeholder',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
            $this->command->info("Created/Updated role: {$roleData['name']}");
        }

        $this->command->info('✅ All roles created successfully!');
    }
}