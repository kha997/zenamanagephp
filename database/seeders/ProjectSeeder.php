<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Project Seeder
 * 
 * Tạo dữ liệu mẫu cho projects
 */
class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo sample projects
        $projects = [
            [
                'tenant_id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'code' => 'PRJ-001',
                'name' => 'Website Redesign',
                'description' => 'Complete redesign of the company website with modern UI/UX',
                'status' => 'active',
                'progress' => 75,
                'budget_total' => 50000,
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(15),
            ],
            [
                'tenant_id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'code' => 'PRJ-002',
                'name' => 'Mobile App Development',
                'description' => 'Development of iOS and Android mobile application',
                'status' => 'planning',
                'progress' => 25,
                'budget_total' => 80000,
                'start_date' => now()->subDays(10),
                'end_date' => now()->addDays(60),
            ],
            [
                'tenant_id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'code' => 'PRJ-003',
                'name' => 'Marketing Campaign',
                'description' => 'Q1 marketing campaign for new product launch',
                'status' => 'active',
                'progress' => 60,
                'budget_total' => 30000,
                'start_date' => now()->subDays(20),
                'end_date' => now()->addDays(10),
            ],
            [
                'tenant_id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'code' => 'PRJ-004',
                'name' => 'Database Migration',
                'description' => 'Migration from MySQL to PostgreSQL',
                'status' => 'on_hold',
                'progress' => 40,
                'budget_total' => 25000,
                'start_date' => now()->subDays(45),
                'end_date' => now()->addDays(30),
            ],
            [
                'tenant_id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'code' => 'PRJ-005',
                'name' => 'E-commerce Platform',
                'description' => 'Build new e-commerce platform with payment integration',
                'status' => 'completed',
                'progress' => 100,
                'budget_total' => 100000,
                'start_date' => now()->subDays(90),
                'end_date' => now()->subDays(10),
            ],
            [
                'tenant_id' => '01k5kzpfwd618xmwdwq3rej3jz',
                'code' => 'PRJ-006',
                'name' => 'API Development',
                'description' => 'RESTful API development for mobile and web applications',
                'status' => 'active',
                'progress' => 85,
                'budget_total' => 40000,
                'start_date' => now()->subDays(25),
                'end_date' => now()->addDays(5),
            ],
        ];

        foreach ($projects as $projectData) {
            Project::create($projectData);
        }
    }
}