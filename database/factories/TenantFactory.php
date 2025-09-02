<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Factory cho Tenant model
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->randomNumber(4),
            'domain' => $this->faker->unique()->domainName(),
            'database_name' => null,
            'settings' => null,
            'status' => 'active',
            'is_active' => true,
        ];
    }
    
    /**
     * Tạo tenant với trạng thái trial
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
        ]);
    }
    
    /**
     * Tạo tenant không hoạt động
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'is_active' => false,
        ]);
    }
}