<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ContractPaymentCertificateFactory
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContractPaymentCertificate>
 */
class ContractPaymentCertificateFactory extends Factory
{
    protected $model = ContractPaymentCertificate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountBeforeRetention = $this->faker->randomFloat(2, 1000000, 10000000);
        $retentionPercent = $this->faker->randomFloat(2, 0, 10);
        $retentionAmount = $amountBeforeRetention * ($retentionPercent / 100);
        $amountPayable = $amountBeforeRetention - $retentionAmount;

        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'contract_id' => Contract::factory(),
            'code' => 'IPC-' . str_pad((string) $this->faker->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
            'title' => $this->faker->optional()->sentence(3),
            'period_start' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'period_end' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'status' => $this->faker->randomElement(['draft', 'submitted', 'approved', 'rejected', 'cancelled']),
            'amount_before_retention' => $amountBeforeRetention,
            'retention_percent_override' => $this->faker->optional()->randomFloat(2, 0, 10),
            'retention_amount' => $retentionAmount,
            'amount_payable' => $amountPayable,
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
