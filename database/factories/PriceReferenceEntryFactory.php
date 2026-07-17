<?php

namespace Database\Factories;

use App\Models\PriceReferenceEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PriceReferenceEntry> */
class PriceReferenceEntryFactory extends Factory
{
    protected $model = PriceReferenceEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_item_code' => 'CODE-' . $this->faker->unique()->numerify('###'),
            'work_item_name' => $this->faker->words(3, true),
            'unit' => $this->faker->randomElement(['m2', 'm3', 'kg', 'cai']),
            'unit_price' => $this->faker->randomFloat(2, 10000, 2000000),
            'benchmark_type' => $this->faker->randomElement(PriceReferenceEntry::VALID_BENCHMARK_TYPES),
            'evidence_note' => $this->faker->sentence(),
            'evidenced_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
