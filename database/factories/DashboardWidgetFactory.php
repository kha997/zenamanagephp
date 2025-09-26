<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\DashboardWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DashboardWidget>
 */
class DashboardWidgetFactory extends Factory
{
    protected $model = DashboardWidget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['chart', 'table', 'card', 'metric', 'alert']),
            'category' => $this->faker->randomElement(['overview', 'progress', 'analytics', 'alerts', 'quality', 'budget', 'safety']),
            'config' => [
                'title' => $this->faker->sentence(3),
                'refresh_interval' => $this->faker->numberBetween(30, 300),
                'chart_type' => $this->faker->randomElement(['line', 'bar', 'pie', 'doughnut']),
            ],
            'data_source' => [
                'model' => $this->faker->randomElement(['Project', 'Task', 'User', 'Component']),
                'method' => $this->faker->randomElement(['count', 'sum', 'avg', 'groupBy']),
                'filters' => [],
            ],
            'permissions' => ['view_dashboard'],
            'is_active' => true,
            'description' => $this->faker->paragraph(2),
        ];
    }

    /**
     * Widget type: Chart
     */
    public function chart(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'chart',
            'config' => array_merge($attributes['config'] ?? [], [
                'chart_type' => $this->faker->randomElement(['line', 'bar', 'pie', 'doughnut']),
                'x_axis' => 'date',
                'y_axis' => 'count',
            ]),
        ]);
    }

    /**
     * Widget type: Metric
     */
    public function metric(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'metric',
            'config' => array_merge($attributes['config'] ?? [], [
                'format' => 'number',
                'prefix' => '',
                'suffix' => '',
            ]),
        ]);
    }

    /**
     * Widget type: Table
     */
    public function table(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'table',
            'config' => array_merge($attributes['config'] ?? [], [
                'columns' => ['name', 'status', 'created_at'],
                'sortable' => true,
                'paginated' => true,
            ]),
        ]);
    }

    /**
     * Widget category: Overview
     */
    public function overview(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'overview',
        ]);
    }

    /**
     * Widget category: Analytics
     */
    public function analytics(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'analytics',
        ]);
    }

    /**
     * Widget category: Progress
     */
    public function progress(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'progress',
        ]);
    }

    /**
     * Inactive widget
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
