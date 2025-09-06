<?php declare(strict_types=1);

namespace Database\Factories\Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\Baseline;
use Src\CoreProject\Models\Project;
use App\Models\User;

/**
 * Factory cho Baseline model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\CoreProject\Models\Baseline>
 */
class BaselineFactory extends Factory
{
    protected $model = Baseline::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-6 months', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+1 year');
        
        return [
            'project_id' => Project::factory(),
            'type' => $this->faker->randomElement(Baseline::VALID_TYPES),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cost' => $this->faker->randomFloat(2, 10000, 1000000),
            'version' => $this->faker->numberBetween(1, 5),
            'note' => $this->faker->optional(0.7)->paragraph(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * State: Contract baseline
     */
    public function contract(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Baseline::TYPE_CONTRACT,
            'version' => 1,
            'note' => 'Initial contract baseline',
        ]);
    }

    /**
     * State: Execution baseline
     */
    public function execution(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Baseline::TYPE_EXECUTION,
        ]);
    }
}