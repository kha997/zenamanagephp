<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Component;
use App\Models\Project;

/**
 * Factory cho Component model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Component>
 */
class ComponentFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = Component::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'tenant_id' => \App\Models\Tenant::factory(),
            'project_id' => Project::factory(),
            'parent_component_id' => null,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(2),
            'type' => $this->faker->randomElement(['general', 'design', 'development', 'testing', 'deployment']),
            'status' => $this->faker->randomElement(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'progress_percent' => $this->faker->randomFloat(2, 0, 100),
            'planned_cost' => $this->faker->randomFloat(2, 10000, 500000),
            'actual_cost' => $this->faker->randomFloat(2, 0, 400000),
            'budget' => $this->faker->randomFloat(2, 10000, 500000),
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => $this->faker->dateTimeBetween('+1 month', '+6 months'),
            'dependencies' => json_encode([]),
            'metadata' => json_encode(['created_by_factory' => true]),
            'created_by' => null,
        ];
    }

    /**
     * Tạo component con với parent cụ thể
     */
    public function withParent(Component $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $parent->project_id,
            'parent_component_id' => $parent->id,
            'planned_cost' => $this->faker->randomFloat(2, 1000, 50000),
            'actual_cost' => $this->faker->randomFloat(2, 0, 40000),
        ]);
    }

    /**
     * Tạo component cho project cụ thể
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Tạo component với tiến độ hoàn thành
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percent' => 100.0,
        ]);
    }

    /**
     * Tạo component chưa bắt đầu
     */
    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percent' => 0.0,
            'actual_cost' => 0.0,
        ]);
    }

    /**
     * Tạo component đang thực hiện
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percent' => $this->faker->randomFloat(2, 10, 90),
        ]);
    }
}