<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\QcPlan;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QcPlan>
 */
class QcPlanFactory extends Factory
{
    protected $model = QcPlan::class;

    public function definition(): array
    {
        return [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'project_id' => Project::factory(),
            'tenant_id' => Tenant::factory(),
            'title' => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['draft', 'active', 'completed', 'cancelled']),
            'start_date' => $this->faker->optional(0.6)->date(),
            'end_date' => $this->faker->optional(0.5)->dateTimeBetween('now', '+2 months'),
            'created_by' => User::factory(),
            'checklist_items' => [],
        ];
    }
}
