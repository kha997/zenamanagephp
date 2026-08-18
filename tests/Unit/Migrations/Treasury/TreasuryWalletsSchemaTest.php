<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryWalletsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_wallets'));
        $this->assertTrue(Schema::hasColumns('treasury_wallets', [
            'id', 'tenant_id', 'project_id', 'wallet_type', 'name',
            'custodian_party_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_custodian_party_composite_foreign_key_is_enforced(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $otherTenant = \App\Models\Tenant::factory()->create();

        $foreignParty = \App\Models\Treasury\TreasuryFinancialParty::create([
            'tenant_id' => $otherTenant->id,
            'party_type' => 'vendor',
            'name' => 'Cross-tenant party',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id,
            'wallet_type' => 'bank',
            'name' => 'Should fail',
            'custodian_party_id' => $foreignParty->id,
        ]);
    }
}
