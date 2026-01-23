<?php

namespace Database\Factories;

use App\Models\QcPlan;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QcPlanFactory extends Factory
{
    protected $model = QcPlan::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 week', '+1 week');
        $endDate = $this->faker->dateTimeBetween($startDate, '+3 weeks');

        return [
            'project_id' => Project::factory(),
            'tenant_id' => Tenant::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'status' => $this->faker->randomElement(['draft', 'active', 'completed']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_by' => User::factory(),
            'checklist_items' => [
                [
                    'item' => 'Sample inspection item',
                    'specification' => 'Default spec',
                    'method' => 'Visual',
                    'acceptance_criteria' => 'Pass',
                ],
            ],
        ];
    }
}
