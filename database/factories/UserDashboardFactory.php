<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserDashboard;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserDashboard>
 */
class UserDashboardFactory extends Factory
{
    protected $model = UserDashboard::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(2, true) . ' Dashboard',
            'layout_config' => [
                'columns' => 3,
                'rows' => 4,
                'widgets' => [
                    [
                        'id' => 'widget-1',
                        'x' => 0,
                        'y' => 0,
                        'width' => 1,
                        'height' => 1,
                    ],
                    [
                        'id' => 'widget-2',
                        'x' => 1,
                        'y' => 0,
                        'width' => 2,
                        'height' => 1,
                    ],
                ],
            ],
            'widgets' => [
                'widget-1' => [
                    'type' => 'metric',
                    'config' => ['title' => 'Total Projects'],
                ],
                'widget-2' => [
                    'type' => 'chart',
                    'config' => ['title' => 'Project Status'],
                ],
            ],
            'preferences' => [
                'theme' => 'light',
                'refresh_interval' => 60,
                'auto_refresh' => true,
            ],
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /**
     * Default dashboard
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'name' => 'Default Dashboard',
        ]);
    }

    /**
     * Inactive dashboard
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Dashboard with specific layout
     */
    public function withLayout(int $columns = 3, int $rows = 4): static
    {
        return $this->state(fn (array $attributes) => [
            'layout_config' => [
                'columns' => $columns,
                'rows' => $rows,
                'widgets' => [],
            ],
        ]);
    }
}
