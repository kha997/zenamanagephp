<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryAdvanceSettlementsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_advance_settlements'));
        $this->assertTrue(Schema::hasColumns('treasury_advance_settlements', [
            'id', 'tenant_id', 'advance_id', 'settlement_type', 'direction', 'amount',
            'financial_document_id', 'reverses_settlement_id', 'created_at',
        ]));
        $this->assertFalse(
            Schema::hasColumn('treasury_advance_settlements', 'updated_at'),
            'treasury_advance_settlements is an event-log table per Sec 2.1a — no updated_at'
        );
    }

    private function makeAdvance(): \App\Models\Treasury\TreasuryAdvance
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $party = \App\Models\Treasury\TreasuryFinancialParty::create([
            'tenant_id' => $tenant->id, 'party_type' => 'vendor', 'name' => 'P',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'advance', 'status' => 'posted_unreconciled',
            'amount' => 100, 'source_wallet_id' => \App\Models\Treasury\TreasuryWallet::create([
                'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
            ])->id,
            'destination_party_id' => $party->id,
            'created_by' => $user->id,
        ]);

        return \App\Models\Treasury\TreasuryAdvance::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id,
            'originating_financial_document_id' => $doc->id, 'amount' => 100,
        ]);
    }

    public function test_positive_amount_is_enforced(): void
    {
        $advance = $this->makeAdvance();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be > 0/');

        \App\Models\Treasury\TreasuryAdvanceSettlement::create([
            'tenant_id' => $advance->tenant_id,
            'advance_id' => $advance->id,
            'settlement_type' => \App\Models\Treasury\TreasuryAdvanceSettlement::SETTLEMENT_TYPE_APPROVED_EXPENSE,
            'direction' => \App\Models\Treasury\TreasuryAdvanceSettlement::DIRECTION_APPLY,
            'amount' => 0,
        ]);
    }

    public function test_allowed_settlement_type_is_enforced(): void
    {
        $advance = $this->makeAdvance();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be one of/');

        \App\Models\Treasury\TreasuryAdvanceSettlement::create([
            'tenant_id' => $advance->tenant_id,
            'advance_id' => $advance->id,
            'settlement_type' => 'not_a_real_type',
            'direction' => \App\Models\Treasury\TreasuryAdvanceSettlement::DIRECTION_APPLY,
            'amount' => 100,
        ]);
    }
}
