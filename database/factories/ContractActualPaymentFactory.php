<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ContractActualPaymentFactory
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContractActualPayment>
 */
class ContractActualPaymentFactory extends Factory
{
    protected $model = ContractActualPayment::class;

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
            'certificate_id' => $this->faker->optional()->randomElement([
                null,
                function (array $attributes) {
                    return ContractPaymentCertificate::factory()->create([
                        'tenant_id' => $attributes['tenant_id'],
                        'project_id' => $attributes['project_id'],
                        'contract_id' => $attributes['contract_id'],
                    ])->id;
                },
            ]),
            'paid_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'amount_paid' => $this->faker->randomFloat(2, 100000, 5000000),
            'currency' => $this->faker->randomElement(['VND', 'USD', 'EUR']), // Provide default since Round 36 table requires it
            'payment_method' => $this->faker->optional()->randomElement(['bank_transfer', 'cash', 'offset', 'check']),
            'reference_no' => $this->faker->optional()->bothify('REF-####-????'),
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
