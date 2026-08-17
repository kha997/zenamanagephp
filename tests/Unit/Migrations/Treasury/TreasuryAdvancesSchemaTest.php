<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryAdvancesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_advances'));
        $this->assertTrue(Schema::hasColumns('treasury_advances', [
            'id', 'tenant_id', 'project_id', 'financial_party_id',
            'originating_financial_document_id', 'amount', 'created_at', 'updated_at',
        ]));
    }

    public function test_originating_financial_document_id_is_unique(): void
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

        \App\Models\Treasury\TreasuryAdvance::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id,
            'originating_financial_document_id' => $doc->id, 'amount' => 100,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryAdvance::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id,
            'originating_financial_document_id' => $doc->id, 'amount' => 100,
        ]);
    }

    public function test_positive_amount_is_enforced(): void
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be > 0/');

        \App\Models\Treasury\TreasuryAdvance::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id,
            'originating_financial_document_id' => $doc->id, 'amount' => 0,
        ]);
    }
}
