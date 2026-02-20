<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('CTR-####??')),
            'title' => $this->faker->sentence(4),
            'status' => $this->faker->randomElement(Contract::VALID_STATUSES),
            'currency' => 'USD',
            'total_value' => $this->faker->randomFloat(2, 1000, 200000),
            'signed_at' => $this->faker->optional()->date(),
            'start_date' => $this->faker->optional()->date(),
            'end_date' => $this->faker->optional()->date(),
            'created_by' => User::factory(),
        ];
    }
}

