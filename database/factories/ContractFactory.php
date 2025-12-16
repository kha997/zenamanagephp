<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<App\Models\Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'CT-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{8}')),
            'name' => $this->faker->sentence(3),
            'status' => $this->faker->randomElement(['draft', 'active', 'completed', 'terminated']),
            'client_id' => null,
            'project_id' => null,
            'signed_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'effective_from' => $this->faker->optional()->dateTimeBetween('-1 year', '+1 year'),
            'effective_to' => $this->faker->optional()->dateTimeBetween('+1 year', '+3 years'),
            'currency' => $this->faker->randomElement(['USD', 'VND', 'EUR', 'GBP']),
            'total_value' => $this->faker->randomFloat(2, 1000, 1000000),
            'notes' => $this->faker->optional()->paragraph(),
            'created_by_id' => function (array $attributes) {
                return User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id;
            },
            'updated_by_id' => function (array $attributes) {
                return $attributes['created_by_id'];
            },
        ];
    }
}
