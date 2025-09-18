<?php declare(strict_types=1);

namespace Database\Factories\Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\Project;

/**
 * Factory cho Component model
 * 
 * Tạo test data cho project components với hierarchy support
 */
class ComponentFactory extends Factory
{
    protected $model = Component::class;

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        $plannedCost = $this->faker->randomFloat(2, 1000, 50000);
        $progressPercent = $this->faker->numberBetween(0, 100);
        $actualCost = $plannedCost * ($progressPercent / 100) * $this->faker->randomFloat(2, 0.8, 1.2);
        
        return [
            'project_id' => Project::factory(),
            'parent_component_id' => null,
            'name' => $this->faker->randomElement([
                'Foundation Work',
                'Structural Framework',
                'Electrical Installation',
                'Plumbing System',
                'Interior Design',
                'Quality Assurance',
                'Documentation',
                'Testing Phase'
            ]),
            'progress_percent' => $progressPercent,
            'planned_cost' => $plannedCost,
            'actual_cost' => $actualCost,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Root component state (no parent)
     */
    public function root(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_component_id' => null,
            'name' => $this->faker->randomElement([
                'Phase 1 - Planning',
                'Phase 2 - Development',
                'Phase 3 - Testing',
                'Phase 4 - Deployment'
            ])
        ]);
    }

    /**
     * Child component state (has parent)
     */
    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_component_id' => Component::factory(),
            'planned_cost' => $this->faker->randomFloat(2, 500, 10000)
        ]);
    }

    /**
     * Completed component state
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $plannedCost = $attributes['planned_cost'] ?? $this->faker->randomFloat(2, 1000, 50000);
            return [
                'progress_percent' => 100,
                'actual_cost' => $plannedCost * $this->faker->randomFloat(2, 0.9, 1.1)
            ];
        });
    }

    /**
     * In progress component state
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $progressPercent = $this->faker->numberBetween(25, 75);
            $plannedCost = $attributes['planned_cost'] ?? $this->faker->randomFloat(2, 1000, 50000);
            return [
                'progress_percent' => $progressPercent,
                'actual_cost' => $plannedCost * ($progressPercent / 100) * $this->faker->randomFloat(2, 0.8, 1.2)
            ];
        });
    }
}