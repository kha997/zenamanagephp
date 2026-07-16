<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\PaymentCertificate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PaymentCertificate> */
class PaymentCertificateFactory extends Factory
{
    protected $model = PaymentCertificate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contract_id' => Contract::factory(),
            'period_no' => $this->faker->numberBetween(1, 12),
            'period_from' => $this->faker->dateTimeBetween('-3 months', '-1 month'),
            'period_to' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'status' => PaymentCertificate::STATUS_DRAFT,
            'total_this_period' => $this->faker->randomFloat(2, 1000000, 10000000000),
            'retention_amount' => $this->faker->randomFloat(2, 0, 100000000),
            'advance_deduction' => $this->faker->randomFloat(2, 0, 50000000),
            'net_payable' => $this->faker->randomFloat(2, 500000, 5000000000),
        ];
    }
}
