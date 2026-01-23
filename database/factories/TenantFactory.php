<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory cho Tenant model
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;
    
    /**
     * Define the model's default state
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'name' => $this->faker->company(),
            'slug' => Str::slug(
                $this->faker->unique()->company() . '-' . Str::lower(Str::random(6))
            ),
            'domain' => $this->faker->domainName(),
            'settings' => json_encode([
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND'
            ]),
            'is_active' => true,
        ];
    }
}
