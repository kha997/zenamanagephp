<?php declare(strict_types=1);

namespace Database\Factories\Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\TaskAssignment;
use Src\CoreProject\Models\Task;
use App\Models\User;

/**
 * Factory cho TaskAssignment model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\CoreProject\Models\TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
{
    protected $model = TaskAssignment::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'split_percentage' => $this->faker->randomFloat(2, 10, 100),
            'assigned_by' => User::factory(),
            'assigned_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'role_in_task' => $this->faker->randomElement(['lead', 'contributor', 'reviewer']),
        ];
    }

    /**
     * State: Full assignment (100%)
     */
    public function fullAssignment(): static
    {
        return $this->state(fn (array $attributes) => [
            'split_percentage' => 100.00,
            'role_in_task' => 'lead',
        ]);
    }

    /**
     * State: Lead role
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_in_task' => 'lead',
            'split_percentage' => $this->faker->randomFloat(2, 50, 100),
        ]);
    }
}