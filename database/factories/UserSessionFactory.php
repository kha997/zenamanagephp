<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserSession>
 */
class UserSessionFactory extends Factory
{
    protected $model = UserSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => $this->faker->uuid(),
            'device_id' => $this->faker->uuid(),
            'device_name' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'device_type' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'browser_version' => $this->faker->numerify('##.#'),
            'os' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'os_version' => $this->faker->numerify('##.#'),
            'ip_address' => $this->faker->ipv4(),
            'country' => $this->faker->countryCode(),
            'city' => $this->faker->city(),
            'is_current' => false,
            'is_trusted' => false,
            'last_activity_at' => now(),
            'expires_at' => now()->addHours(24),
            'metadata' => [],
        ];
    }
}
