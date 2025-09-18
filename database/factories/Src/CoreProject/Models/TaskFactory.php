<?php declare(strict_types=1);

namespace Database\Factories\Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;

/**
 * Factory cho Task model
 * 
 * Tạo test data cho tasks với dependencies và conditional logic
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-3 months', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+6 months');
        
        return [
            'project_id' => Project::factory(),
            'component_id' => null, // Will be set by component() state if needed
            'phase_id' => null,
            'name' => $this->faker->randomElement([
                'Requirements Analysis',
                'System Design',
                'Database Schema Design',
                'API Development',
                'Frontend Implementation',
                'Unit Testing',
                'Integration Testing',
                'User Acceptance Testing',
                'Documentation',
                'Deployment Preparation'
            ]),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'blocked', 'cancelled']),
            'dependencies' => json_encode([]), // Empty dependencies by default
            'conditional_tag' => null,
            'is_hidden' => false,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Task with component assignment
     */
    public function withComponent(): static
    {
        return $this->state(fn (array $attributes) => [
            'component_id' => Component::factory()
        ]);
    }

    /**
     * Task with dependencies
     */
    public function withDependencies(array $taskIds = []): static
    {
        return $this->state(fn (array $attributes) => [
            'dependencies' => json_encode($taskIds ?: [$this->faker->numberBetween(1, 100)])
        ]);
    }

    /**
     * Conditional task state
     */
    public function conditional(string $tag = null): static
    {
        return $this->state(fn (array $attributes) => [
            'conditional_tag' => $tag ?: $this->faker->randomElement([
                'premium_features',
                'advanced_reporting',
                'mobile_app',
                'api_integration'
            ])
        ]);
    }

    /**
     * Hidden task state
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
            'conditional_tag' => 'disabled_feature'
        ]);
    }

    /**
     * Completed task state
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'end_date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d')
        ]);
    }

    /**
     * In progress task state
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'start_date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d')
        ]);
    }

    /**
     * Blocked task state
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked'
        ]);
    }
}