<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QcInspection>
 */
class QcInspectionFactory extends Factory
{
    protected $model = QcInspection::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);
        $status = $this->faker->randomElement(['scheduled', 'in_progress', 'completed', 'failed']);

        return [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'qc_plan_id' => QcPlan::factory(),
            'project_id' => Project::factory(),
            'tenant_id' => Tenant::factory(),
            'title' => $title,
            'name' => $title,
            'description' => $this->faker->paragraph(),
            'status' => $status,
            'type' => $status,
            'inspection_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'inspector_id' => User::factory(),
            'findings' => $this->faker->paragraph(),
            'recommendations' => $this->faker->paragraph(),
            'checklist_results' => [
                ['item' => 'Safety review', 'result' => $this->faker->boolean() ? 'pass' : 'fail'],
                ['item' => 'Documentation', 'result' => $this->faker->boolean() ? 'pass' : 'fail'],
            ],
            'photos' => [],
        ];
    }
}
