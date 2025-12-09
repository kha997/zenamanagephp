<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractLine;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContractLine>
 */
class ContractLineFactory extends Factory
{
    protected $model = ContractLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 1000);
        $unitPrice = $this->faker->randomFloat(2, 1000, 100000);
        $amount = $quantity * $unitPrice;

        return [
            'tenant_id' => Tenant::factory(),
            'contract_id' => Contract::factory(),
            'project_id' => Project::factory(),
            'budget_line_id' => null,
            'item_code' => $this->faker->optional()->regexify('[A-Z]{3}-[0-9]{3}'),
            'description' => $this->faker->sentence(4),
            'unit' => $this->faker->optional()->randomElement(['m2', 'm3', 'kg', 'unit', 'lot']),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
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
