<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Tenant;

/**
 * Task Seeder
 * 
 * Tạo dữ liệu mẫu cho tasks với assignments
 */
class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📋 Seeding tasks...');

        // Lấy tất cả tenants để tạo tasks cho mỗi tenant
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping tasks seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->createTasksForTenant($tenant);
        }

        $this->command->info('✅ Tasks seeded successfully!');
    }

    /**
     * Tạo tasks cho một tenant
     */
    private function createTasksForTenant(Tenant $tenant): void
    {
        // Lấy projects của tenant này
        $projects = Project::where('tenant_id', $tenant->id)->withoutGlobalScopes()->get();
        
        if ($projects->isEmpty()) {
            $this->command->warn("No projects found for tenant: {$tenant->name}. Skipping tasks.");
            return;
        }

        // Lấy users của tenant này
        $users = User::where('tenant_id', $tenant->id)->withoutGlobalScopes()->get();
        
        if ($users->isEmpty()) {
            $this->command->warn("No users found for tenant: {$tenant->name}. Skipping tasks.");
            return;
        }

        $taskTemplates = [
            'Website Redesign' => [
                'Phân tích yêu cầu thiết kế',
                'Tạo wireframe và mockup',
                'Thiết kế UI/UX',
                'Code frontend',
                'Test và debug',
                'Deploy lên production'
            ],
            'Mobile App Development' => [
                'Phân tích requirements',
                'Thiết kế database',
                'Code backend API',
                'Code mobile app',
                'Test trên các thiết bị',
                'Submit lên app store'
            ],
            'Marketing Campaign' => [
                'Nghiên cứu thị trường',
                'Tạo content marketing',
                'Thiết kế banner quảng cáo',
                'Chạy campaign trên social media',
                'Theo dõi và phân tích kết quả',
                'Báo cáo ROI'
            ],
            'Database Migration' => [
                'Backup database hiện tại',
                'Thiết kế schema mới',
                'Viết script migration',
                'Test migration trên staging',
                'Chạy migration production',
                'Verify dữ liệu sau migration'
            ],
            'E-commerce Platform' => [
                'Thiết kế hệ thống',
                'Code backend',
                'Code frontend',
                'Tích hợp payment gateway',
                'Test toàn bộ flow',
                'Deploy và monitor'
            ],
            'API Development' => [
                'Thiết kế API endpoints',
                'Code authentication',
                'Code business logic',
                'Viết documentation',
                'Test API với Postman',
                'Deploy và monitor'
            ]
        ];

        foreach ($projects as $project) {
            $projectTasks = $taskTemplates[$project->name] ?? [
                'Phân tích yêu cầu',
                'Thiết kế giải pháp',
                'Triển khai',
                'Test',
                'Deploy',
                'Bảo trì'
            ];

            foreach ($projectTasks as $index => $taskName) {
                $task = Task::create([
                    'tenant_id' => $tenant->id,
                    'project_id' => $project->id,
                    'name' => $taskName,
                    'title' => $taskName,
                    'description' => "Chi tiết công việc: {$taskName} cho dự án {$project->name}",
                    'status' => $this->getRandomStatus(),
                    'priority' => $this->getRandomPriority(),
                    'progress_percent' => rand(0, 100),
                    'assigned_to' => $users->random()->id,
                    'start_date' => now()->subDays(rand(1, 10)),
                    'end_date' => now()->addDays(rand(1, 30)),
                    'estimated_hours' => rand(4, 40),
                    'actual_hours' => rand(0, 40),
                ]);

                // Assign task cho users (70% chance)
                if (fake()->boolean(70)) {
                    $this->assignTaskToUsers($task, $users);
                }
            }
        }

        $this->command->info("Created tasks for tenant: {$tenant->name}");
    }

    /**
     * Assign task cho users
     */
    private function assignTaskToUsers(Task $task, $users): void
    {
        $assignedUsers = $users->random(rand(1, min(3, $users->count())));
        
        foreach ($assignedUsers as $user) {
            $task->assignments()->create([
                'user_id' => $user->id,
                'role' => fake()->randomElement(['assignee', 'reviewer', 'observer']),
            ]);
        }
    }

    /**
     * Lấy status ngẫu nhiên
     */
    private function getRandomStatus(): string
    {
        $statuses = ['pending', 'in_progress', 'completed', 'on_hold', 'cancelled'];
        return fake()->randomElement($statuses);
    }

    /**
     * Lấy priority ngẫu nhiên
     */
    private function getRandomPriority(): string
    {
        $priorities = ['low', 'medium', 'high', 'urgent'];
        return fake()->randomElement($priorities);
    }
}




