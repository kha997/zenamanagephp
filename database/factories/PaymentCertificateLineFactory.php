<?php

namespace Database\Factories;

use App\Models\BoqLineItem;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PaymentCertificateLine> */
class PaymentCertificateLineFactory extends Factory
{
    protected $model = PaymentCertificateLine::class;

    public function definition(): array
    {
        $qty = $this->faker->randomFloat(2, 1, 500);
        $unitPrice = $this->faker->randomFloat(0, 10000, 500000);

        return [
            'tenant_id' => Tenant::factory(),
            'payment_certificate_id' => PaymentCertificate::factory(),
            'boq_line_item_id' => BoqLineItem::factory(),
            'qty_this_period' => $qty,
            'unit_price_snapshot' => $unitPrice,
            'amount_this_period' => $qty * $unitPrice,
        ];
    }
}
