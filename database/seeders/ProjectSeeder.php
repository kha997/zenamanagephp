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
        $this->command->info('📋 Seeding projects...');

        // Lấy tất cả tenants để tạo projects cho mỗi tenant
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping projects seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->createProjectsForTenant($tenant);
        }

        $this->command->info('✅ Projects seeded successfully!');
    }

    /**
     * Tạo projects cho một tenant
     */
    private function createProjectsForTenant(Tenant $tenant): void
    {
        // Sử dụng tenant ID để tạo unique code
        $tenantSuffix = substr($tenant->id, -8); // Lấy 8 ký tự cuối của tenant ID
        $projects = [
            [
                'code' => 'PRJ-' . $tenantSuffix . '-001',
                'name' => 'Website Redesign',
                'description' => 'Complete redesign of the company website with modern UI/UX',
                'status' => 'active',
                'progress' => 75,
                'budget_total' => 50000,
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(15),
            ],
            [
                'code' => 'PRJ-' . $tenantSuffix . '-002',
                'name' => 'Mobile App Development',
                'description' => 'Development of iOS and Android mobile application',
                'status' => 'planning',
                'progress' => 25,
                'budget_total' => 80000,
                'start_date' => now()->subDays(10),
                'end_date' => now()->addDays(60),
            ],
            [
                'code' => 'PRJ-' . $tenantSuffix . '-003',
                'name' => 'Marketing Campaign',
                'description' => 'Q1 marketing campaign for new product launch',
                'status' => 'active',
                'progress' => 60,
                'budget_total' => 30000,
                'start_date' => now()->subDays(20),
                'end_date' => now()->addDays(10),
            ],
            [
                'code' => 'PRJ-' . $tenantSuffix . '-004',
                'name' => 'Database Migration',
                'description' => 'Migration from MySQL to PostgreSQL',
                'status' => 'on_hold',
                'progress' => 40,
                'budget_total' => 25000,
                'start_date' => now()->subDays(45),
                'end_date' => now()->addDays(30),
            ],
            [
                'code' => 'PRJ-' . $tenantSuffix . '-005',
                'name' => 'E-commerce Platform',
                'description' => 'Build new e-commerce platform with payment integration',
                'status' => 'completed',
                'progress' => 100,
                'budget_total' => 100000,
                'start_date' => now()->subDays(90),
                'end_date' => now()->subDays(10),
            ],
            [
                'code' => 'PRJ-' . $tenantSuffix . '-006',
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
            Project::create(array_merge($projectData, [
                'tenant_id' => $tenant->id,
            ]));
        }

        $this->command->info("Created " . count($projects) . " projects for tenant: {$tenant->name}");
    }
}