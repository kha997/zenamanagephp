<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectBudgetLine>
 */
class ProjectBudgetLineFactory extends Factory
{
    protected $model = ProjectBudgetLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'cost_category' => $this->faker->randomElement(['structure', 'mep', 'interior', 'doors', 'ffe']),
            'cost_code' => $this->faker->optional()->regexify('[A-Z]{3}-[0-9]{3}'),
            'description' => $this->faker->sentence(4),
            'unit' => $this->faker->optional()->randomElement(['m2', 'm3', 'kg', 'unit', 'lot']),
            'quantity' => $this->faker->optional()->randomFloat(2, 1, 1000),
            'unit_price_budget' => $this->faker->optional()->randomFloat(2, 1000, 100000),
            'amount_budget' => $this->faker->randomFloat(2, 10000, 10000000),
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
