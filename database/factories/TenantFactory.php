<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        $companyName = $this->faker->company();
        $slug = \Illuminate\Support\Str::slug($companyName) . '-' . $this->faker->unique()->randomNumber(4);
        
        return [
            'id' => \Illuminate\Support\Str::ulid(),
            'name' => $companyName,
            'slug' => $slug,
            'domain' => $this->faker->domainName(),
            'settings' => json_encode([
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND'
            ]),
            'is_active' => true,
            'status' => $this->faker->randomElement(['trial', 'active', 'suspended', 'cancelled']),
        ];
    }
}