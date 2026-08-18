<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryLedgerEntriesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_ledger_entries'));
        $this->assertTrue(Schema::hasColumns('treasury_ledger_entries', [
            'id', 'tenant_id', 'source_financial_document_id', 'source_payment_route_leg_id',
            'wallet_id', 'direction', 'amount', 'entry_type', 'posted_at',
            'reversal_of_entry_id', 'original_posting_key', 'created_at',
        ]));
        $this->assertFalse(
            Schema::hasColumn('treasury_ledger_entries', 'updated_at'),
            'treasury_ledger_entries is append-only per design-doc Sec 2.1a — it must not have an updated_at column'
        );
    }

    public function test_original_posting_key_is_unique(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'posted_unreconciled',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);

        \App\Models\Treasury\TreasuryLedgerEntry::create([
            'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "doc:{$doc->id}:credit",
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryLedgerEntry::create([
            'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "doc:{$doc->id}:credit",
        ]);
    }

    public function test_positive_amount_is_enforced(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'posted_unreconciled',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be > 0/');

        \App\Models\Treasury\TreasuryLedgerEntry::create([
            'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 0,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "doc:{$doc->id}:zero",
        ]);
    }

    public function test_exactly_one_source_is_enforced(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'posted_unreconciled',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
        $route = \App\Models\Treasury\TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
        ]);
        $leg = \App\Models\Treasury\TreasuryPaymentRouteLeg::create([
            'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 1, 'to_wallet_id' => $wallet->id,
            'amount' => 100, 'status' => 'in_transit',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exactly one of/');

        \App\Models\Treasury\TreasuryLedgerEntry::create([
            'tenant_id' => $tenant->id,
            'source_financial_document_id' => $doc->id,
            'source_payment_route_leg_id' => $leg->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "doc:{$doc->id}:both",
        ]);
    }

    public function test_allowed_direction_is_enforced(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'posted_unreconciled',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be one of/');

        \App\Models\Treasury\TreasuryLedgerEntry::create([
            'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'invalid', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "doc:{$doc->id}:baddir",
        ]);
    }
}
