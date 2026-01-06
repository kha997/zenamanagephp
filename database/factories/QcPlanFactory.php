<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\QcPlan;
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
        $title = $this->faker->sentence(4);
        $status = $this->faker->randomElement(['draft', 'active', 'completed', 'cancelled']);

        return [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'project_id' => Project::factory(),
            'tenant_id' => Tenant::factory(),
            'title' => $title,
            'name' => $title,
            'description' => $this->faker->paragraph(),
            'status' => $status,
            'type' => $status,
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+1 week', '+2 months'),
            'created_by' => User::factory(),
            'checklist_items' => [
                ['item' => 'Design review', 'checked' => $this->faker->boolean()],
                ['item' => 'Quality acceptance', 'checked' => $this->faker->boolean()],
            ],
        ];
    }
}
