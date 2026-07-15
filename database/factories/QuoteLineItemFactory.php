<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\QuoteLineItem> */
class QuoteLineItemFactory extends Factory
{
    protected $model = QuoteLineItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'quote_id' => Quote::factory(),
            'sort_order' => 1,
            'name' => $this->faker->words(3, true),
            'unit' => $this->faker->randomElement(['m', 'm2', 'pcs', 'kg']),
            'quantity' => $this->faker->randomFloat(3, 1, 100),
            'unit_price' => $this->faker->randomFloat(2, 10000, 1000000),
            'amount' => $this->faker->randomFloat(2, 10000, 10000000),
        ];
    }
}
