<?php declare(strict_types=1);

namespace Database\Factories\Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\ComponentKpi;
use Src\CoreProject\Models\Component;

/**
 * Factory cho ComponentKpi model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\CoreProject\Models\ComponentKpi>
 */
class ComponentKpiFactory extends Factory
{
    protected $model = ComponentKpi::class;

    public function definition(): array
    {
        return [
            'component_id' => Component::factory(),
            'kpi_name' => $this->faker->randomElement(['Quality Score', 'Completion Rate', 'Budget Efficiency', 'Timeline Adherence']),
            'target_value' => $this->faker->randomFloat(2, 80, 100),
            'current_value' => $this->faker->randomFloat(2, 60, 95),
            'unit' => $this->faker->randomElement(['%', 'score', 'days', 'USD']),
            'measurement_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'notes' => $this->faker->optional(0.6)->sentence(),
        ];
    }

    /**
     * State: Quality KPI
     */
    public function quality(): static
    {
        return $this->state(fn (array $attributes) => [
            'kpi_name' => 'Quality Score',
            'target_value' => 95.0,
            'unit' => 'score',
        ]);
    }

    /**
     * State: Budget KPI
     */
    public function budget(): static
    {
        return $this->state(fn (array $attributes) => [
            'kpi_name' => 'Budget Efficiency',
            'target_value' => 100.0,
            'unit' => '%',
        ]);
    }
}