<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

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
            'code' => 'PRJ-' . strtoupper(bin2hex(random_bytes(6))),
            'name' => $this->faker->company() . ' Project',
            'description' => $this->faker->paragraph(),
            'client_id' => null,
            'pm_id' => User::factory(),
            'created_by' => User::factory(),
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+1 month', '+6 months'),
            'status' => $this->faker->randomElement(['planning', 'active', 'in_progress', 'on_hold', 'completed', 'cancelled']),
            'progress' => $this->faker->randomFloat(2, 0, 100),
            'budget_planned' => $this->faker->randomFloat(2, 10000, 1000000),
            'budget_actual' => $this->faker->randomFloat(2, 0, 1000000),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'tags' => $this->faker->words(3),
            'settings' => [
                'notifications' => $this->faker->boolean(),
                'auto_assign' => $this->faker->boolean(),
                'require_approval' => $this->faker->boolean(),
            ],
        ];
    }

    /**
     * Indicate that the project is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'progress' => $this->faker->randomFloat(2, 10, 90),
        ]);
    }

    /**
     * Indicate that the project is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress' => 100,
            'end_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the project is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'end_date' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the project has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Indicate that the project has low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
        ]);
    }
}
