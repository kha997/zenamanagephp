<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Tenant;

/**
 * Factory cho User model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();
        [$local, $domain] = explode('@', $email);

        return [
            'id' => (string) Str::ulid(),
            'name' => $this->faker->name(),
            'email' => sprintf('%s+%s@%s', $local, Str::ulid(), $domain),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => \Illuminate\Support\Str::random(10),
            'tenant_id' => Tenant::factory(), // Laravel sẽ tự động tạo ULID
            'is_active' => true,
            'profile_data' => '{}',
        ];
    }

    /**
     * Tạo user với email đã xác thực
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Tạo user chưa xác thực email
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Tạo user không hoạt động
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Tạo user với tenant cụ thể (nhận ULID string)
     */
    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }
}
