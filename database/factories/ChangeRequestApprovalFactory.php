<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChangeRequestApproval;
use App\Models\ZenaChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChangeRequestApproval>
 */
class ChangeRequestApprovalFactory extends Factory
{
    protected $model = ChangeRequestApproval::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'change_request_id' => ZenaChangeRequest::factory(),
            'user_id' => User::factory(),
            'level' => $this->faker->randomElement(['level_1', 'level_2', 'level_3', 'final']),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'comments' => null,
            'approved_at' => null,
        ];
    }

    /**
     * Indicate that the approval is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'comments' => null,
            'approved_at' => null,
        ]);
    }

    /**
     * Indicate that the approval is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'comments' => $this->faker->paragraph(),
            'approved_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the approval is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'comments' => $this->faker->paragraph(),
            'approved_at' => null,
        ]);
    }

    /**
     * Indicate that the approval is level 1.
     */
    public function level1(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'level_1',
        ]);
    }

    /**
     * Indicate that the approval is level 2.
     */
    public function level2(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'level_2',
        ]);
    }

    /**
     * Indicate that the approval is level 3.
     */
    public function level3(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'level_3',
        ]);
    }

    /**
     * Indicate that the approval is final.
     */
    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'final',
        ]);
    }
}
