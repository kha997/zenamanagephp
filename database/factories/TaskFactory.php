<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;

/**
 * Factory cho Task model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\CoreProject\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = Task::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-30 days', '+30 days');
        $endDate = $this->faker->dateTimeBetween($startDate, '+60 days');
        
        return [
            'project_id' => Project::factory(),
            'component_id' => null,
            'phase_id' => null,
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(2),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $this->faker->randomElement(Task::VALID_STATUSES),
            'priority' => $this->faker->randomElement(Task::VALID_PRIORITIES),
            'dependencies' => null,
            'conditional_tag' => null,
            'is_hidden' => false,
            'estimated_hours' => $this->faker->randomFloat(2, 1, 80),
            'actual_hours' => $this->faker->randomFloat(2, 0, 60),
            'progress_percent' => $this->faker->randomFloat(2, 0, 100),
            'tags' => $this->faker->randomElements(['frontend', 'backend', 'design', 'testing', 'documentation'], $this->faker->numberBetween(0, 3)),
            'visibility' => $this->faker->randomElement(['internal', 'client']),
            'client_approved' => false,
        ];
    }

    /**
     * Tạo task cho project cụ thể
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Tạo task cho component cụ thể
     */
    public function forComponent(Component $component): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $component->project_id,
            'component_id' => $component->id,
        ]);
    }

    /**
     * Tạo task với trạng thái pending
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_PENDING,
            'progress_percent' => 0.0,
            'actual_hours' => 0.0,
        ]);
    }

    /**
     * Tạo task đang thực hiện
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_IN_PROGRESS,
            'progress_percent' => $this->faker->randomFloat(2, 10, 90),
        ]);
    }

    /**
     * Tạo task đã hoàn thành
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Task::STATUS_COMPLETED,
            'progress_percent' => 100.0,
            'client_approved' => $this->faker->boolean(70),
        ]);
    }

    /**
     * Tạo task với conditional tag
     */
    public function withConditionalTag(string $tag, bool $isHidden = false): static
    {
        return $this->state(fn (array $attributes) => [
            'conditional_tag' => $tag,
            'is_hidden' => $isHidden,
        ]);
    }

    /**
     * Tạo task với độ ưu tiên cao
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Task::PRIORITY_HIGH,
        ]);
    }

    /**
     * Tạo task với visibility client
     */
    public function clientVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'client',
            'client_approved' => $this->faker->boolean(80),
        ]);
    }
}