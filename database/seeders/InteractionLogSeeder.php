<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\InteractionLogs\Models\InteractionLog;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * InteractionLog Seeder
 * 
 * Tạo dữ liệu mẫu cho interaction logs
 */
class InteractionLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();
        $users = User::all();
        $tasks = Task::all();

        foreach ($projects as $project) {
            // Tạo interaction logs cho project
            InteractionLog::factory(5)
                ->forProject($project->id)
                ->create([
                    'created_by' => $users->random()->id
                ]);

            // Tạo interaction logs liên kết với tasks
            $projectTasks = $tasks->where('project_id', $project->id);
            if ($projectTasks->count() > 0) {
                foreach ($projectTasks->take(3) as $task) {
                    InteractionLog::factory(2)
                        ->forProject($project->id)
                        ->forTask($task->id)
                        ->create([
                            'created_by' => $users->random()->id
                        ]);
                }
            }

            // Tạo một số logs đã được approve cho client
            InteractionLog::factory(3)
                ->forProject($project->id)
                ->clientVisible()  // ✅ Đã thay từ clientApproved() thành clientVisible()
                ->create([
                    'created_by' => $users->random()->id
                ]);
        }
    }
}