<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoleProfile>
 */
class RoleProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->jobTitle() . ' Profile',
            'description' => $this->faker->sentence(),
            'roles' => [], // Will be set in state or after creation
            'is_active' => true,
            'tenant_id' => \App\Models\Tenant::factory(),
        ];
    }
}
