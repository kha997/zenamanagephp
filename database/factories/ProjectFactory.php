<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<App\Models\Project>
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
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company() . ' Project',
            'code' => 'PRJ-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{8}')),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['active', 'archived', 'completed', 'on_hold', 'cancelled', 'planning']),
            'owner_id' => function (array $attributes) {
                return User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id;
            },
            'tags' => json_encode($this->faker->words(3)),
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+1 month', '+6 months'),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'progress_pct' => $this->faker->numberBetween(0, 100),
            'budget_total' => $this->faker->randomFloat(2, 10000, 1000000),
            'budget_planned' => $this->faker->randomFloat(2, 10000, 1000000),
            'budget_actual' => $this->faker->randomFloat(2, 0, 1000000),
            'estimated_hours' => $this->faker->randomFloat(2, 40, 1000),
            'actual_hours' => $this->faker->randomFloat(2, 0, 1000),
            'risk_level' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'is_template' => false,
            'template_id' => null,
            'last_activity_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'completion_percentage' => $this->faker->randomFloat(2, 0, 100),
            'settings' => json_encode([
                'notifications' => $this->faker->boolean(),
                'auto_assign' => $this->faker->boolean(),
                'require_approval' => $this->faker->boolean(),
            ]),
        ];
    }

    /**
     * Indicate that the project is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'progress_pct' => $this->faker->numberBetween(10, 90),
            'completion_percentage' => $this->faker->randomFloat(2, 10, 90),
        ]);
    }

    /**
     * Indicate that the project is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress_pct' => 100,
            'completion_percentage' => 100.0,
            'end_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the project is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
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