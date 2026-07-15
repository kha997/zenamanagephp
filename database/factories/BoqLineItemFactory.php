<?php

namespace Database\Factories;

use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\BoqLineItem> */
class BoqLineItemFactory extends Factory
{
    protected $model = BoqLineItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'boq_id' => Boq::factory(),
            'code' => 'A.' . str_pad((string) $this->faker->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->randomFloat(2, 1, 1000),
            'unit' => $this->faker->randomElement(['m3', 'kg', 'm2', 'm', 'cái', 'bộ']),
            'unit_price' => $this->faker->randomFloat(0, 10000, 500000),
        ];
    }
}
