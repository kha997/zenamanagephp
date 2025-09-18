<?php declare(strict_types=1);

namespace Database\Factories\Src\ChangeRequest\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\CoreProject\Models\Project;
use App\Models\User;

/**
 * Factory cho ChangeRequest model
 * 
 * Tạo test data cho change requests với workflow states
 */
class ChangeRequestFactory extends Factory
{
    protected $model = ChangeRequest::class;

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => 'CR-' . $this->faker->unique()->numberBetween(1000, 9999),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(4),
            'status' => $this->faker->randomElement(['draft', 'awaiting_approval', 'approved', 'rejected']),
            'impact_days' => $this->faker->numberBetween(0, 30),
            'impact_cost' => $this->faker->randomFloat(2, 0, 25000),
            'impact_kpi' => json_encode([
                'quality' => $this->faker->randomElement(['+5%', '+10%', '-2%']),
                'timeline' => $this->faker->randomElement(['+5 days', '+10 days', 'no impact']),
                'budget' => $this->faker->randomElement(['+$5000', '+$10000', 'within budget'])
            ]),
            'created_by' => User::factory(),
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null,
            'created_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'updated_at' => now()
        ];
    }

    /**
     * Draft change request state
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null
        ]);
    }

    /**
     * Approved change request state
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'decided_by' => User::factory(),
            'decided_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'decision_note' => 'Approved for ' . $this->faker->randomElement([
                'quality improvement',
                'client requirements',
                'technical necessity',
                'business value'
            ])
        ]);
    }

    /**
     * Rejected change request state
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'decided_by' => User::factory(),
            'decided_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'decision_note' => 'Rejected due to ' . $this->faker->randomElement([
                'budget constraints',
                'timeline impact',
                'technical complexity',
                'scope limitations'
            ])
        ]);
    }

    /**
     * High impact change request
     */
    public function highImpact(): static
    {
        return $this->state(fn (array $attributes) => [
            'impact_days' => $this->faker->numberBetween(15, 45),
            'impact_cost' => $this->faker->randomFloat(2, 15000, 50000),
            'impact_kpi' => json_encode([
                'quality' => '+15%',
                'timeline' => '+' . $this->faker->numberBetween(15, 30) . ' days',
                'budget' => '+$' . $this->faker->numberBetween(15000, 50000)
            ])
        ]);
    }
}