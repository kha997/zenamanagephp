<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\Task;
use App\Models\User;

/**
 * Seeder cho Task model
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
        // Lấy tất cả components để tạo tasks
        $components = Component::with('project')->get();
        
        foreach ($components as $component) {
            $this->createTasksForComponent($component);
        }
        
        // Tạo một số tasks không thuộc component nào (project-level tasks)
        $projects = Project::all();
        foreach ($projects as $project) {
            $this->createProjectLevelTasks($project);
        }
    }

    /**
     * Tạo tasks cho một component
     */
    private function createTasksForComponent(Component $component): void
    {
        $tasksCount = fake()->numberBetween(2, 6);
        
        for ($i = 1; $i <= $tasksCount; $i++) {
            $task = Task::factory()
                ->forComponent($component)
                ->create([
                    'name' => $this->getTaskName($component->name, $i),
                ]);
            
            // Assign task cho users (70% chance)
            if (fake()->boolean(70)) {
                $this->assignTaskToUsers($task);
            }
        }
    }

    /**
     * Tạo project-level tasks
     */
    private function createProjectLevelTasks(Project $project): void
    {
        $tasksCount = fake()->numberBetween(1, 3);
        
        for ($i = 1; $i <= $tasksCount; $i++) {
            $task = Task::factory()
                ->forProject($project)
                ->create([
                    'name' => $this->getProjectLevelTaskName($i),
                    'priority' => Task::PRIORITY_HIGH,
                ]);
            
            $this->assignTaskToUsers($task);
        }
    }

    /**
     * Assign task cho users
     */
    private function assignTaskToUsers(Task $task): void
    {
        // Lấy users trong cùng tenant với project
        $users = User::where('tenant_id', $task->project->tenant_id)
                    ->inRandomOrder()
                    ->limit(fake()->numberBetween(1, 3))
                    ->get();
        
        if ($users->isEmpty()) {
            return;
        }
        
        $totalPercentage = 100;
        $assignedUsers = $users->count();
        
        foreach ($users as $index => $user) {
            $percentage = ($index === $assignedUsers - 1) 
                ? $totalPercentage // Assign remaining percentage to last user
                : fake()->numberBetween(20, 60);
            
            $task->assignments()->create([
                'user_id' => $user->id,
                'split_percentage' => $percentage,
                'role' => fake()->randomElement(['lead', 'member', 'reviewer']),
            ]);
            
            $totalPercentage -= $percentage;
            
            if ($totalPercentage <= 0) {
                break;
            }
        }
    }

    /**
     * Lấy tên task dựa trên component
     */
    private function getTaskName(string $componentName, int $index): string
    {
        $taskTemplates = [
            'Khảo sát địa hình' => [
                'Đo đạc địa hình',
                'Phân tích đất đá',
                'Lập báo cáo khảo sát',
                'Đánh giá rủi ro địa chất'
            ],
            'Thiết kế kiến trúc' => [
                'Phác thảo ý tưởng',
                'Vẽ bản thiết kế sơ bộ',
                'Hoàn thiện bản vẽ',
                'Thuyết trình với khách hàng'
            ],
            'Đào móng' => [
                'Đánh dấu vị trí móng',
                'Đào hố móng',
                'Kiểm tra độ sâu',
                'Vệ sinh hố móng'
            ],
            'Sơn tường' => [
                'Chuẩn bị bề mặt',
                'Sơn lót',
                'Sơn hoàn thiện',
                'Kiểm tra chất lượng'
            ]
        ];
        
        foreach ($taskTemplates as $component => $tasks) {
            if (str_contains($componentName, $component) && isset($tasks[$index - 1])) {
                return $tasks[$index - 1];
            }
        }
        
        return "{$componentName} - Công việc {$index}";
    }

    /**
     * Lấy tên cho project-level tasks
     */
    private function getProjectLevelTaskName(int $index): string
    {
        $names = [
            'Họp kick-off dự án',
            'Báo cáo tiến độ hàng tuần',
            'Đánh giá rủi ro dự án',
            'Họp tổng kết giai đoạn'
        ];
        
        return $names[$index - 1] ?? "Công việc dự án #{$index}";
    }
}