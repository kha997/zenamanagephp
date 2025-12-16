<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\TemplateSet;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<App\Models\TemplateSet>
 */
class TemplateSetFactory extends Factory
{
    protected $model = TemplateSet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'TEMPLATE-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{8}')),
            'name' => $this->faker->words(3, true) . ' Template',
            'description' => $this->faker->paragraph(),
            'version' => $this->faker->randomElement(['1.0', '1.1', '2.0', '2.1']),
            'is_active' => true,
            'is_global' => false,
            'created_by' => function (array $attributes) {
                return User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id;
            },
            'metadata' => null,
        ];
    }
}

