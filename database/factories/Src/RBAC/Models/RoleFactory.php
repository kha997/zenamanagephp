<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\RBAC\Models\Role;

/**
 * Factory cho Role model
 * 
 * Tạo test data cho RBAC roles với các scopes khác nhau
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Project Manager',
                'Team Lead',
                'Developer',
                'QA Engineer',
                'Client',
                'Stakeholder'
            ]),
            'scope' => $this->faker->randomElement(['system', 'custom', 'project']),
            'description' => $this->faker->sentence(10),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * System scope role state
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'system',
            'name' => $this->faker->randomElement([
                'Super Admin',
                'System Administrator',
                'Global Manager'
            ])
        ]);
    }

    /**
     * Project scope role state
     */
    public function project(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'project',
            'name' => $this->faker->randomElement([
                'Project Owner',
                'Project Manager',
                'Team Member',
                'Observer'
            ])
        ]);
    }

    /**
     * Custom scope role state
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'custom',
            'name' => 'Custom ' . $this->faker->jobTitle
        ]);
    }
}