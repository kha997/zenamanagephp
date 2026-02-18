<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
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
        $uniqueSuffix = Str::lower((string) Str::ulid());

        return [
            'name' => $this->faker->randomElement([
                'Project Manager',
                'Team Lead',
                'Developer',
                'QA Engineer',
                'Client',
                'Stakeholder'
            ]) . '-' . $uniqueSuffix,
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
            ]) . '-' . Str::lower((string) Str::ulid())
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
            ]) . '-' . Str::lower((string) Str::ulid())
        ]);
    }

    /**
     * Custom scope role state
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'custom',
            'name' => 'Custom ' . $this->faker->jobTitle . '-' . Str::lower((string) Str::ulid())
        ]);
    }
}
