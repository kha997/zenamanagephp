<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Template;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds for production environment.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('🌱 Seeding production data...');

        // Create production tenant
        $tenant = Tenant::create([
            'id' => '01K6W8JTMFKREMQ7EPDRJ8CQDZ',
            'name' => 'Production Tenant',
            'domain' => 'production.zenamanage.com',
            'is_active' => true,
            'settings' => [
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'currency' => 'USD',
                'features' => [
                    'templates' => true,
                    'team_management' => true,
                    'advanced_reporting' => false,
                    'api_access' => true,
                ]
            ],
        ]);

        $this->command->info('✅ Production tenant created');

        // Create production admin user
        $adminUser = User::create([
            'id' => '01K6W8JTPE4H7CB8YVFW5BKF8X',
            'name' => 'Production Admin',
            'email' => 'admin@zenamanage.com',
            'password' => Hash::make('SecureProductionPassword123!'),
            'role' => 'super_admin',
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'profile' => [
                'phone' => '+1-555-0123',
                'department' => 'IT',
                'position' => 'System Administrator',
            ],
        ]);

        $this->command->info('✅ Production admin user created');

        // Create sample production users
        $users = [
            [
                'name' => 'John Project Manager',
                'email' => 'john.pm@zenamanage.com',
                'role' => 'pm',
                'department' => 'Project Management',
                'position' => 'Senior Project Manager',
            ],
            [
                'name' => 'Jane Developer',
                'email' => 'jane.dev@zenamanage.com',
                'role' => 'member',
                'department' => 'Development',
                'position' => 'Senior Developer',
            ],
            [
                'name' => 'Bob Designer',
                'email' => 'bob.design@zenamanage.com',
                'role' => 'member',
                'department' => 'Design',
                'position' => 'UI/UX Designer',
            ],
        ];

        foreach ($users as $userData) {
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('DefaultPassword123!'),
                'role' => $userData['role'],
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
                'is_active' => true,
                'profile' => [
                    'department' => $userData['department'],
                    'position' => $userData['position'],
                ],
            ]);
        }

        $this->command->info('✅ Production users created');

        // Create sample production clients
        $clients = [
            [
                'name' => 'Acme Corporation',
                'email' => 'contact@acme.com',
                'phone' => '+1-555-0100',
                'company' => 'Acme Corporation',
                'industry' => 'Technology',
            ],
            [
                'name' => 'Beta Industries',
                'email' => 'info@beta.com',
                'phone' => '+1-555-0200',
                'company' => 'Beta Industries',
                'industry' => 'Manufacturing',
            ],
        ];

        foreach ($clients as $clientData) {
            Client::create([
                'name' => $clientData['name'],
                'email' => $clientData['email'],
                'phone' => $clientData['phone'],
                'company' => $clientData['company'],
                'industry' => $clientData['industry'],
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);
        }

        $this->command->info('✅ Production clients created');

        // Create sample production projects
        $projects = [
            [
                'name' => 'Website Redesign',
                'description' => 'Complete redesign of corporate website',
                'status' => 'active',
                'budget' => 50000,
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(60),
            ],
            [
                'name' => 'Mobile App Development',
                'description' => 'iOS and Android mobile application',
                'status' => 'planning',
                'budget' => 75000,
                'start_date' => now()->addDays(7),
                'end_date' => now()->addDays(120),
            ],
        ];

        foreach ($projects as $projectData) {
            Project::create([
                'name' => $projectData['name'],
                'description' => $projectData['description'],
                'status' => $projectData['status'],
                'budget' => $projectData['budget'],
                'start_date' => $projectData['start_date'],
                'end_date' => $projectData['end_date'],
                'tenant_id' => $tenant->id,
                'created_by' => $adminUser->id,
            ]);
        }

        $this->command->info('✅ Production projects created');

        // Create sample production templates
        $templates = [
            [
                'name' => 'Web Development Template',
                'description' => 'Standard template for web development projects',
                'category' => 'web_development',
                'structure' => [
                    'phases' => [
                        'Planning' => ['requirements', 'design', 'architecture'],
                        'Development' => ['frontend', 'backend', 'database'],
                        'Testing' => ['unit_tests', 'integration_tests', 'user_tests'],
                        'Deployment' => ['staging', 'production', 'monitoring'],
                    ],
                ],
            ],
            [
                'name' => 'Mobile App Template',
                'description' => 'Template for mobile application development',
                'category' => 'mobile_development',
                'structure' => [
                    'phases' => [
                        'Planning' => ['requirements', 'wireframes', 'prototypes'],
                        'Development' => ['ios', 'android', 'backend'],
                        'Testing' => ['device_tests', 'performance_tests'],
                        'Release' => ['app_store', 'play_store', 'updates'],
                    ],
                ],
            ],
        ];

        foreach ($templates as $templateData) {
            Template::create([
                'name' => $templateData['name'],
                'description' => $templateData['description'],
                'category' => $templateData['category'],
                'structure' => $templateData['structure'],
                'is_active' => true,
                'created_by' => $adminUser->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        $this->command->info('✅ Production templates created');

        $this->command->info('🎉 Production data seeding completed successfully!');
        $this->command->info('📧 Admin login: admin@zenamanage.com');
        $this->command->info('🔑 Default password: SecureProductionPassword123!');
        $this->command->warn('⚠️  Please change the admin password after first login!');
    }
}