<?php declare(strict_types=1);

namespace Database\Factories\Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\BaselineHistory;
use Src\CoreProject\Models\Baseline;
use App\Models\User;

/**
 * Factory cho BaselineHistory model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\CoreProject\Models\BaselineHistory>
 */
class BaselineHistoryFactory extends Factory
{
    protected $model = BaselineHistory::class;

    public function definition(): array
    {
        return [
            'baseline_id' => Baseline::factory(),
            'action' => $this->faker->randomElement(BaselineHistory::VALID_ACTIONS),
            'old_data' => [
                'cost' => $this->faker->randomFloat(2, 10000, 500000),
                'end_date' => $this->faker->date(),
            ],
            'new_data' => [
                'cost' => $this->faker->randomFloat(2, 15000, 600000),
                'end_date' => $this->faker->date(),
            ],
            'reason' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * State: Created action
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => BaselineHistory::ACTION_CREATED,
            'old_data' => null,
        ]);
    }

    /**
     * State: Updated action
     */
    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => BaselineHistory::ACTION_UPDATED,
        ]);
    }
}