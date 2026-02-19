<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dashboard;
use App\Models\Tenant;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Widget>
 */
class WidgetFactory extends Factory
{
    protected $model = Widget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'dashboard_id' => Dashboard::factory(),
            'user_id' => null,
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(['chart', 'table', 'metric', 'alert', 'text']),
            'description' => $this->faker->sentence(),
            'config' => [
                'title' => $this->faker->sentence(3),
                'refresh_interval' => $this->faker->numberBetween(30, 300)
            ],
            'position' => [
                'x' => $this->faker->numberBetween(0, 5),
                'y' => $this->faker->numberBetween(0, 5),
                'w' => $this->faker->numberBetween(1, 6),
                'h' => $this->faker->numberBetween(1, 4),
            ],
            'is_active' => true,
            'metadata' => []
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Widget $widget) {
            if ($widget->dashboard_id) {
                $dashboard = Dashboard::find($widget->dashboard_id);
                if ($dashboard) {
                    $widget->tenant_id = $dashboard->tenant_id;
                    $widget->user_id = $dashboard->user_id;
                }
            }

            if (!$widget->tenant_id) {
                $widget->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }
}
