<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\DocumentManagement\Models\Document;
use Src\DocumentManagement\Models\DocumentVersion;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Document Seeder
 * 
 * Tạo dữ liệu mẫu cho documents và document versions
 */
class DocumentSeeder extends Seeder
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
            // Tạo documents cho project
            $documents = Document::factory(3)
                ->forProject($project->id)
                ->create();

            foreach ($documents as $document) {
                // Tạo version đầu tiên
                $firstVersion = DocumentVersion::factory()
                    ->forDocument($document->id)
                    ->create([
                        'version_number' => 1,
                        'created_by' => $users->random()->id
                    ]);

                // Cập nhật current_version_id
                $document->update(['current_version_id' => $firstVersion->id]);

                // Tạo thêm 1-2 versions
                for ($i = 2; $i <= rand(2, 3); $i++) {
                    $version = DocumentVersion::factory()
                        ->forDocument($document->id)
                        ->create([
                            'version_number' => $i,
                            'created_by' => $users->random()->id
                        ]);

                    // Cập nhật current version
                    $document->update(['current_version_id' => $version->id]);
                }
            }

            // Tạo documents liên kết với tasks
            $projectTasks = $tasks->where('project_id', $project->id);
            foreach ($projectTasks->take(2) as $task) {
                $taskDocument = Document::factory()
                    ->forProject($project->id)
                    ->forTask($task->id)
                    ->create();

                $version = DocumentVersion::factory()
                    ->forDocument($taskDocument->id)
                    ->create([
                        'version_number' => 1,
                        'created_by' => $users->random()->id
                    ]);

                $taskDocument->update(['current_version_id' => $version->id]);
            }
        }
    }
}