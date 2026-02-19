<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $uniqueSuffix = Str::lower((string) Str::ulid());

        return [
            'name' => $this->faker->jobTitle() . '-' . $uniqueSuffix,
            'description' => $this->faker->sentence(),
            'scope' => $this->faker->randomElement(['system', 'tenant', 'project']),
            'is_active' => true,
        ];
    }
}
