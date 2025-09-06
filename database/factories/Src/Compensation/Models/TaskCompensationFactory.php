<?php declare(strict_types=1);

namespace Database\Factories\Src\Compensation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Compensation\Models\TaskCompensation;
use Src\Compensation\Models\Contract;
use Src\CoreProject\Models\Task;
use App\Models\User;

/**
 * Factory cho TaskCompensation model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\Compensation\Models\TaskCompensation>
 */
class TaskCompensationFactory extends Factory
{
    protected $model = TaskCompensation::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'compensation_type' => $this->faker->randomElement(TaskCompensation::VALID_TYPES),
            'rate' => $this->faker->randomFloat(2, 50, 500),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'total_amount' => function (array $attributes) {
                return $attributes['rate'] * $attributes['quantity'];
            },
            'status' => $this->faker->randomElement(TaskCompensation::VALID_STATUSES),
            'payment_date' => $this->faker->optional(0.6)->dateTimeBetween('-3 months', '+1 month'),
            'notes' => $this->faker->optional(0.4)->sentence(),
        ];
    }

    /**
     * State: Hourly compensation
     */
    public function hourly(): static
    {
        return $this->state(fn (array $attributes) => [
            'compensation_type' => TaskCompensation::TYPE_HOURLY,
            'rate' => $this->faker->randomFloat(2, 50, 200),
            'quantity' => $this->faker->randomFloat(2, 1, 40), // hours
        ]);
    }

    /**
     * State: Fixed compensation
     */
    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'compensation_type' => TaskCompensation::TYPE_FIXED,
            'quantity' => 1,
            'rate' => $this->faker->randomFloat(2, 1000, 10000),
        ]);
    }

    /**
     * State: Paid compensation
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskCompensation::STATUS_PAID,
            'payment_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }
}