<?php

namespace Database\Factories;

use App\Models\Dashboard;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dashboard>
 */
class DashboardFactory extends Factory
{
    protected $model = Dashboard::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'widget_config' => [
                'kpi_widget' => true,
                'recent_activities' => true,
                'project_status' => true
            ],
            'layout' => [
                'columns' => 3,
                'rows' => 2
            ],
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the dashboard is the default one.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}