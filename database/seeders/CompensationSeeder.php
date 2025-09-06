<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\Compensation\Models\Contract;
use Src\Compensation\Models\TaskCompensation;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Compensation Seeder
 * 
 * Tạo dữ liệu mẫu cho contracts và task compensations
 */
class CompensationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();
        $tasks = Task::all();
        $users = User::all();

        foreach ($projects as $project) {
            // Tạo contracts cho project
            $activeContract = Contract::factory()
                ->forProject($project->id)
                ->active()
                ->create();

            $draftContract = Contract::factory()
                ->forProject($project->id)
                ->draft()
                ->create();

            // Tạo task compensations cho active contract
            $projectTasks = $tasks->where('project_id', $project->id);
            foreach ($projectTasks->take(5) as $task) {
                TaskCompensation::factory()
                    ->forTask($task->id)
                    ->forContract($activeContract->id)
                    ->create([
                        'created_by' => $users->random()->id
                    ]);
            }
        }
    }
}