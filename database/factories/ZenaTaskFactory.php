<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ZenaTask;
use App\Models\ZenaProject;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ZenaTask>
 */
class ZenaTaskFactory extends Factory
{
    protected $model = ZenaTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'tenant_id' => Tenant::factory(),
            'project_id' => ZenaProject::factory(),
            'parent_id' => null,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'cancelled', 'on_hold']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'assignee_id' => User::factory(),
            'created_by' => User::factory(),
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+1 month', '+3 months'),
            'completed_at' => null,
            'estimated_hours' => $this->faker->randomFloat(2, 1, 40),
            'actual_hours' => $this->faker->randomFloat(2, 0, 40),
            'progress' => $this->faker->numberBetween(0, 100),
            'tags' => $this->faker->words(2),
            'watchers' => [],
            'dependencies' => [],
            'order' => $this->faker->numberBetween(1, 100),
            'visibility' => $this->faker->randomElement(['public', 'team', 'private']),
            'is_hidden' => false,
            'client_approved' => $this->faker->boolean(20),
        ];
    }

    /**
     * Indicate that the task is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'progress' => 0,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'progress' => $this->faker->numberBetween(10, 90),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the task is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'end_date' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the task has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Indicate that the task has low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
        ]);
    }

    /**
     * Indicate that the task is a subtask.
     */
    public function subtask(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => ZenaTask::factory(),
        ]);
    }

    /**
     * Indicate that the task is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }

    /**
     * Indicate that the task is client approved.
     */
    public function clientApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_approved' => true,
        ]);
    }
}