<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'company' => $this->faker->company(),
            'lifecycle_stage' => $this->faker->randomElement(['lead', 'prospect', 'customer', 'inactive']),
            'notes' => $this->faker->optional()->paragraph(),
            'address' => [
                'street' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => $this->faker->state(),
                'zip' => $this->faker->postcode(),
                'country' => $this->faker->country(),
            ],
            'custom_fields' => [
                'industry' => $this->faker->optional()->randomElement(['Construction', 'Architecture', 'Engineering', 'Real Estate']),
                'source' => $this->faker->optional()->randomElement(['Website', 'Referral', 'Cold Call', 'Trade Show']),
            ],
        ];
    }

    /**
     * Indicate that the client is a lead.
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_stage' => 'lead',
        ]);
    }

    /**
     * Indicate that the client is a prospect.
     */
    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_stage' => 'prospect',
        ]);
    }

    /**
     * Indicate that the client is a customer.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_stage' => 'customer',
        ]);
    }

    /**
     * Indicate that the client is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_stage' => 'inactive',
        ]);
    }
}
