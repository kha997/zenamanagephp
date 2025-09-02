<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\RBAC\Models\Role;

/**
 * Factory cho Role model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\RBAC\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->jobTitle(),
            'scope' => $this->faker->randomElement(Role::VALID_SCOPES),
            'allow_override' => $this->faker->boolean(30), // 30% chance true
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Tạo system role
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => Role::SCOPE_SYSTEM,
        ]);
    }

    /**
     * Tạo project role
     */
    public function project(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => Role::SCOPE_PROJECT,
        ]);
    }

    /**
     * Tạo custom role
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => Role::SCOPE_CUSTOM,
        ]);
    }

    /**
     * Tạo role với override permission
     */
    public function withOverride(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_override' => true,
        ]);
    }

    /**
     * Tạo role không có override permission
     */
    public function withoutOverride(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_override' => false,
        ]);
    }
}