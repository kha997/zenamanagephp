<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\RBAC\Models\UserRoleSystem;
use Src\RBAC\Models\Role;
use App\Models\User;

/**
 * Factory cho UserRoleSystem model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\RBAC\Models\UserRoleSystem>
 */
class UserRoleSystemFactory extends Factory
{
    protected $model = UserRoleSystem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory()->system(),
            'assigned_by' => User::factory(),
            'assigned_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'expires_at' => $this->faker->optional(0.3)->dateTimeBetween('now', '+2 years'),
            'is_active' => $this->faker->boolean(90),
        ];
    }

    /**
     * State: Active assignment
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'expires_at' => null,
        ]);
    }

    /**
     * State: Expired assignment
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
            'is_active' => false,
        ]);
    }
}