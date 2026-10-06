<?php declare(strict_types=1);

namespace Database\Factories\Treasury;

use App\Models\Project;
use App\Models\Treasury\TreasuryWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryWallet>
 */
class TreasuryWalletFactory extends Factory
{
    protected $model = TreasuryWallet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'tenant_id' => fn (array $attributes) => Project::query()->withoutGlobalScopes()->findOrFail($attributes['project_id'])->tenant_id,
            'wallet_type' => 'company_bank',
            'name' => 'Tài khoản ' . $this->faker->word(),
            'custodian_party_id' => null,
        ];
    }
}
