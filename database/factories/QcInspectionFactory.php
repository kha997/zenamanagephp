<?php declare(strict_types=1);

namespace Database\Factories;

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
        return [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'qc_plan_id' => QcPlan::factory(),
            'tenant_id' => Tenant::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['scheduled', 'in_progress', 'completed', 'failed']),
            'inspection_date' => $this->faker->date(),
            'inspector_id' => User::factory(),
            'findings' => $this->faker->paragraph(),
            'recommendations' => $this->faker->paragraph(),
            'checklist_results' => [],
            'photos' => [],
        ];
    }
}
