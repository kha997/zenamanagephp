<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\ChangeRequest\Models\ChangeRequest;
use Src\ChangeRequest\Models\CrLink;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Component;
use Src\DocumentManagement\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ChangeRequest Seeder
 * 
 * Tạo dữ liệu mẫu cho change requests và cr links
 */
class ChangeRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();
        $users = User::all();
        $tasks = Task::all();
        $components = Component::all();
        $documents = Document::all();

        foreach ($projects as $project) {
            // Tạo change requests với các trạng thái khác nhau
            $draftCr = ChangeRequest::factory()
                ->forProject($project->id)
                ->draft()
                ->create([
                    'created_by' => $users->random()->id
                ]);

            $awaitingCr = ChangeRequest::factory()
                ->forProject($project->id)
                ->awaitingApproval()
                ->create([
                    'created_by' => $users->random()->id
                ]);

            $approvedCr = ChangeRequest::factory()
                ->forProject($project->id)
                ->approved()
                ->create([
                    'created_by' => $users->random()->id,
                    'decided_by' => $users->random()->id,
                    'decided_at' => now()->subDays(rand(1, 7))
                ]);

            $rejectedCr = ChangeRequest::factory()
                ->forProject($project->id)
                ->rejected()
                ->create([
                    'created_by' => $users->random()->id,
                    'decided_by' => $users->random()->id,
                    'decided_at' => now()->subDays(rand(1, 5))
                ]);

            // Tạo CrLinks cho approved change request
            $projectTasks = $tasks->where('project_id', $project->id);
            $projectComponents = $components->where('project_id', $project->id);
            $projectDocuments = $documents->where('project_id', $project->id);

            // Link với tasks
            foreach ($projectTasks->take(2) as $task) {
                CrLink::factory()
                    ->forChangeRequest($approvedCr->id)
                    ->forTask($task->id)
                    ->create();
            }

            // Link với components
            foreach ($projectComponents->take(1) as $component) {
                CrLink::factory()
                    ->forChangeRequest($approvedCr->id)
                    ->forComponent($component->id)
                    ->create();
            }

            // Link với documents
            foreach ($projectDocuments->take(1) as $document) {
                CrLink::factory()
                    ->forChangeRequest($approvedCr->id)
                    ->forDocument($document->id)
                    ->create();
            }
        }
    }
}