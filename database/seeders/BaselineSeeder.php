<?php declare(strict_types=1);

namespace Database\Seeders;

use Src\CoreProject\Models\Baseline;
use Src\CoreProject\Models\BaselineHistory;
use Src\CoreProject\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Baseline Seeder
 * 
 * Tạo dữ liệu mẫu cho baselines và baseline history
 */
class BaselineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();
        $users = User::all();

        foreach ($projects as $project) {
            // Tạo contract baseline
            $contractBaseline = Baseline::factory()
                ->forProject($project->id)
                ->contract()
                ->create([
                    'created_by' => $users->random()->id
                ]);

            // Tạo execution baseline
            $executionBaseline = Baseline::factory()
                ->forProject($project->id)
                ->execution()
                ->create([
                    'created_by' => $users->random()->id
                ]);

            // Tạo baseline history cho contract baseline
            BaselineHistory::factory(2)
                ->forBaseline($contractBaseline->id)
                ->create([
                    'created_by' => $users->random()->id
                ]);

            // Tạo baseline history cho execution baseline
            BaselineHistory::factory(3)
                ->forBaseline($executionBaseline->id)
                ->create([
                    'created_by' => $users->random()->id
                ]);
        }
    }
}