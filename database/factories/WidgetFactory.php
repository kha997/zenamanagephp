<?php

namespace Database\Factories;

use App\Models\Widget;
use App\Models\Dashboard;
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
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(['kpi', 'chart', 'table', 'text']),
            'settings' => [
                'title' => $this->faker->sentence(3),
                'data_source' => 'api',
                'refresh_interval' => 300
            ],
            'dashboard_id' => Dashboard::factory(),
            'user_id' => null, // Will be set by test
            'tenant_id' => null, // Will be set by test
        ];
    }
}