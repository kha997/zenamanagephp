<?php declare(strict_types=1);

namespace Database\Factories\Src\ChangeRequest\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\CoreProject\Models\Project;
use App\Models\User;

/**
 * Factory cho ChangeRequest model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\ChangeRequest\Models\ChangeRequest>
 */
class ChangeRequestFactory extends Factory
{
    protected $model = ChangeRequest::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => 'CR-' . $this->faker->unique()->numberBetween(1000, 9999),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(3),
            'status' => $this->faker->randomElement(ChangeRequest::VALID_STATUSES),
            'impact_days' => $this->faker->numberBetween(0, 30),
            'impact_cost' => $this->faker->randomFloat(2, 0, 100000),
            'impact_kpi' => [
                'schedule_delay' => $this->faker->numberBetween(0, 15),
                'budget_increase' => $this->faker->randomFloat(2, 0, 50000),
                'quality_impact' => $this->faker->randomElement(['none', 'low', 'medium', 'high']),
            ],
            'created_by' => User::factory(),
            'decided_by' => $this->faker->optional(0.6)->randomElement([User::factory()]),
            'decided_at' => $this->faker->optional(0.6)->dateTimeBetween('-1 month', 'now'),
            'decision_note' => $this->faker->optional(0.5)->paragraph(),
        ];
    }

    /**
     * State: Draft status
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChangeRequest::STATUS_DRAFT,
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null,
        ]);
    }

    /**
     * State: Approved status
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChangeRequest::STATUS_APPROVED,
            'decided_by' => User::factory(),
            'decided_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'decision_note' => 'Approved after review',
        ]);
    }

    /**
     * State: High impact
     */
    public function highImpact(): static
    {
        return $this->state(fn (array $attributes) => [
            'impact_days' => $this->faker->numberBetween(15, 60),
            'impact_cost' => $this->faker->randomFloat(2, 50000, 200000),
        ]);
    }
}