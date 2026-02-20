<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractPayment>
 */
class ContractPaymentFactory extends Factory
{
    protected $model = ContractPayment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contract_id' => Contract::factory(),
            'name' => 'Milestone ' . $this->faker->randomNumber(2),
            'amount' => $this->faker->randomFloat(2, 100, 50000),
            'due_date' => $this->faker->optional()->date(),
            'status' => $this->faker->randomElement(ContractPayment::VALID_STATUSES),
            'paid_at' => null,
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}

