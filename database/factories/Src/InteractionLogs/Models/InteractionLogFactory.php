<?php declare(strict_types=1);

namespace Database\Factories\Src\InteractionLogs\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\InteractionLogs\Models\InteractionLog;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use App\Models\User;

/**
 * Factory cho InteractionLog model
 * 
 * Tạo test data cho interaction logs với các types và visibility levels
 */
class InteractionLogFactory extends Factory
{
    protected $model = InteractionLog::class;

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'linked_task_id' => null,
            'type' => $this->faker->randomElement(['call', 'email', 'meeting', 'note', 'feedback']),
            'description' => $this->faker->paragraph(3),
            'tag_path' => $this->faker->randomElement([
                'Material/Concrete/Foundation',
                'Design/Architecture/Layout',
                'Quality/Testing/Performance',
                'Communication/Client/Feedback',
                'Documentation/Technical/Specification'
            ]),
            'visibility' => $this->faker->randomElement(['internal', 'client']),
            'client_approved' => $this->faker->boolean(70), // 70% chance of approval
            'created_by' => User::factory(),
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'updated_at' => now()
        ];
    }

    /**
     * Client visible interaction log
     */
    public function clientVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'client',
            'client_approved' => true
        ]);
    }

    /**
     * Internal only interaction log
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'internal',
            'client_approved' => false
        ]);
    }

    /**
     * Meeting type interaction log
     */
    public function meeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'meeting',
            'description' => 'Meeting with ' . $this->faker->randomElement([
                'client stakeholders',
                'project team',
                'technical leads',
                'quality assurance team'
            ]) . ' to discuss ' . $this->faker->sentence(5)
        ]);
    }

    /**
     * Task-linked interaction log
     */
    public function withTask(): static
    {
        return $this->state(fn (array $attributes) => [
            'linked_task_id' => Task::factory()
        ]);
    }
}