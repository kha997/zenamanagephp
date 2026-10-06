<?php declare(strict_types=1);

namespace Database\Factories\Treasury;

use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryFinancialParty>
 */
class TreasuryFinancialPartyFactory extends Factory
{
    protected $model = TreasuryFinancialParty::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'party_type' => 'supplier',
            'name' => $this->faker->company(),
            'linked_account_id' => null,
            'linked_user_id' => null,
        ];
    }
}
