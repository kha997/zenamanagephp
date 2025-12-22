<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChangeOrder;
use App\Models\ChangeOrderLine;
use App\Models\Contract;
use App\Models\ContractLine;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ChangeOrderLineFactory
 * 
 * Round 220: Change Orders for Contracts
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChangeOrderLine>
 */
class ChangeOrderLineFactory extends Factory
{
    protected $model = ChangeOrderLine::class;

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
            'change_order_id' => ChangeOrder::factory(),
            'contract_line_id' => null,
            'budget_line_id' => null,
            'item_code' => $this->faker->optional()->regexify('[A-Z0-9]{6}'),
            'description' => $this->faker->sentence(4),
            'unit' => $this->faker->optional()->randomElement(['m2', 'm3', 'kg', 'unit', 'hour']),
            'quantity_delta' => $this->faker->optional()->randomFloat(2, -100, 100),
            'unit_price_delta' => $this->faker->optional()->randomFloat(2, -10000, 10000),
            'amount_delta' => $this->faker->randomFloat(2, -500000, 500000),
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
