<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dashboard;
use App\Models\Tenant;
use App\Models\User;
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
            'tenant_id' => null,
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(6),
            'layout' => [],
            'preferences' => [],
            'is_public' => false,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Dashboard $dashboard) {
            if (!$dashboard->tenant_id && $dashboard->user_id) {
                $user = $dashboard->user_id instanceof User
                    ? $dashboard->user_id
                    : User::find($dashboard->user_id);

                $dashboard->tenant_id = $user?->tenant_id;
            }

            if (!$dashboard->tenant_id) {
                $dashboard->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }
}
