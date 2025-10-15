<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;

class CreateTestProjects extends Command
{
    protected $signature = 'test:create-projects';
    protected $description = 'Create test projects for development';

    public function handle()
    {
        $tenant = Tenant::where('id', 'test-tenant-1')->first();
        $user = User::where('email', 'test@example.com')->first();

        if (!$tenant || !$user) {
            $this->error('Please run test:create-user first');
            return 1;
        }

        $projects = [
            [
                'id' => 'project-1',
                'name' => 'Website Redesign',
                'description' => 'Complete website redesign project',
                'status' => 'active',
                'progress' => 75,
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(30),
                'budget' => 50000,
                'actual_cost' => 37500
            ],
            [
                'id' => 'project-2',
                'name' => 'Mobile App Development',
                'description' => 'iOS and Android mobile application',
                'status' => 'planning',
                'progress' => 25,
                'start_date' => now()->subDays(10),
                'end_date' => now()->addDays(60),
                'budget' => 80000,
                'actual_cost' => 20000
            ],
            [
                'id' => 'project-3',
                'name' => 'Database Migration',
                'description' => 'Migrate legacy database to new system',
                'status' => 'completed',
                'progress' => 100,
                'start_date' => now()->subDays(90),
                'end_date' => now()->subDays(10),
                'budget' => 30000,
                'actual_cost' => 28000
            ],
            [
                'id' => 'project-4',
                'name' => 'API Integration',
                'description' => 'Integrate third-party APIs',
                'status' => 'on_hold',
                'progress' => 45,
                'start_date' => now()->subDays(20),
                'end_date' => now()->addDays(40),
                'budget' => 25000,
                'actual_cost' => 11250
            ]
        ];

        foreach ($projects as $projectData) {
            $project = Project::firstOrCreate(
                ['id' => $projectData['id']],
                array_merge($projectData, [
                    'tenant_id' => $tenant->id,
                    'owner_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );

            $this->info("Created project: {$project->name}");
        }

        $this->info("Created " . count($projects) . " test projects");
        return 0;
    }
}