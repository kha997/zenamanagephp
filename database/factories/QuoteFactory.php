<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Quote> */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'opportunity_id' => null, // must be set manually — no OpportunityFactory
            'quote_number' => 'BG-' . date('Y') . '-0001',
            'revision_no' => 1,
            'status' => Quote::STATUS_DRAFT,
            'subtotal' => 0,
            'created_by' => User::factory(),
        ];
    }
}
