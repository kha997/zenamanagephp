<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\RBAC\Models\UserRoleCustom;
use Src\RBAC\Models\Role;
use App\Models\User;

/**
 * Factory cho UserRoleCustom model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\RBAC\Models\UserRoleCustom>
 */
class UserRoleCustomFactory extends Factory
{
    protected $model = UserRoleCustom::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory()->custom(),
            'assigned_by' => User::factory(),
            'assigned_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'expires_at' => $this->faker->optional(0.4)->dateTimeBetween('now', '+1 year'),
            'is_active' => $this->faker->boolean(85),
        ];
    }

    /**
     * State: Active assignment
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}