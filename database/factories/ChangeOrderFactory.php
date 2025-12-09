<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ChangeOrderFactory
 * 
 * Round 220: Change Orders for Contracts
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChangeOrder>
 */
class ChangeOrderFactory extends Factory
{
    protected $model = ChangeOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'contract_id' => Contract::factory(),
            'code' => 'CO-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{6}')),
            'title' => $this->faker->sentence(3),
            'reason' => $this->faker->optional()->randomElement(['design_change', 'site_condition', 'client_request']),
            'status' => $this->faker->randomElement(['draft', 'proposed', 'approved', 'rejected', 'cancelled']),
            'amount_delta' => $this->faker->randomFloat(2, -1000000, 1000000),
            'effective_date' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'metadata' => null,
            'created_by' => function (array $attributes) {
                return User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id;
            },
            'updated_by' => function (array $attributes) {
                return $attributes['created_by'];
            },
        ];
    }
}
