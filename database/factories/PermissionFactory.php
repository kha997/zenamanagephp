<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->words(2, true),
            'code' => $this->faker->word() . '.' . $this->faker->randomElement(['create', 'read', 'update', 'delete']),
            'description' => $this->faker->sentence(),
            'module' => $this->faker->randomElement(['task', 'project', 'user', 'document']),
            'action' => $this->faker->randomElement(['create', 'read', 'update', 'delete']),
            'is_active' => true,
            'tenant_id' => null,
        ];
    }
}
