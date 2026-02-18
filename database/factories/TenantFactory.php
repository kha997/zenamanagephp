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
        $companyName = $this->faker->unique()->company();

        return [
            'id' => (string) Str::ulid(),
            'name' => $companyName,
            'domain' => $this->faker->domainName(),
            'slug' => Str::slug($companyName . '-' . Str::ulid()),
            'settings' => json_encode([
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND'
            ]),
            'is_active' => true,
        ];
    }
}
