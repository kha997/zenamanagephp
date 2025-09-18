<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ZenaChangeRequest;
use App\Models\ZenaProject;
use App\Models\ZenaTask;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ZenaChangeRequest>
 */
class ZenaChangeRequestFactory extends Factory
{
    protected $model = ZenaChangeRequest::class;

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
            'task_id' => null,
            'change_number' => 'CR-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{8}')),
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraphs(3, true),
            'change_type' => $this->faker->randomElement(['scope', 'schedule', 'cost', 'quality', 'risk', 'resource']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected', 'implemented']),
            'impact_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'requested_by' => User::factory(),
            'assigned_to' => User::factory(),
            'approved_by' => null,
            'rejected_by' => null,
            'requested_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'due_date' => $this->faker->dateTimeBetween('+1 week', '+1 month'),
            'approved_at' => null,
            'rejected_at' => null,
            'implemented_at' => null,
            'estimated_cost' => $this->faker->randomFloat(2, 100, 50000),
            'actual_cost' => $this->faker->randomFloat(2, 0, 50000),
            'estimated_days' => $this->faker->numberBetween(1, 30),
            'actual_days' => $this->faker->numberBetween(0, 30),
            'approval_notes' => null,
            'rejection_reason' => null,
            'implementation_notes' => null,
            'attachments' => [],
            'impact_analysis' => [
                'scope_impact' => $this->faker->paragraph(),
                'schedule_impact' => $this->faker->paragraph(),
                'cost_impact' => $this->faker->paragraph(),
                'quality_impact' => $this->faker->paragraph(),
            ],
            'risk_assessment' => [
                'risk_level' => $this->faker->randomElement(['low', 'medium', 'high']),
                'mitigation_plan' => $this->faker->paragraph(),
                'contingency_plan' => $this->faker->paragraph(),
            ],
        ];
    }

    /**
     * Indicate that the change request is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_by' => null,
            'rejected_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'implemented_at' => null,
        ]);
    }

    /**
     * Indicate that the change request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'rejected_by' => null,
            'rejected_at' => null,
            'implemented_at' => null,
            'approval_notes' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Indicate that the change request is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejected_by' => User::factory(),
            'rejected_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'approved_by' => null,
            'approved_at' => null,
            'implemented_at' => null,
            'rejection_reason' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Indicate that the change request is implemented.
     */
    public function implemented(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'implemented',
            'approved_by' => User::factory(),
            'approved_at' => $this->faker->dateTimeBetween('-2 months', '-1 month'),
            'implemented_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'rejected_by' => null,
            'rejected_at' => null,
            'implementation_notes' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Indicate that the change request has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Indicate that the change request has low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
        ]);
    }

    /**
     * Indicate that the change request has high impact.
     */
    public function highImpact(): static
    {
        return $this->state(fn (array $attributes) => [
            'impact_level' => 'high',
        ]);
    }

    /**
     * Indicate that the change request has low impact.
     */
    public function lowImpact(): static
    {
        return $this->state(fn (array $attributes) => [
            'impact_level' => 'low',
        ]);
    }

    /**
     * Indicate that the change request is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'due_date' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the change request is related to a task.
     */
    public function withTask(): static
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => ZenaTask::factory(),
        ]);
    }
}
