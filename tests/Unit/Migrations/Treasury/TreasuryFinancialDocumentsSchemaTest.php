<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryFinancialDocumentsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_financial_documents'));
        $this->assertTrue(Schema::hasColumns('treasury_financial_documents', [
            'id', 'tenant_id', 'project_id', 'document_type', 'status',
            'posting_path', 'amount', 'source_wallet_id', 'destination_wallet_id',
            'source_party_id', 'destination_party_id', 'description',
            'created_by', 'approved_by', 'posted_at',
            'reversed_document_id', 'replacement_document_id',
            'created_at', 'updated_at',
        ]));
    }

    public function test_reversed_document_id_is_unique(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $original = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'posted_unreconciled',
            'amount' => 100, 'destination_wallet_id' => $wallet->id, 'created_by' => $user->id,
        ]);

        \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'reversal', 'status' => 'draft',
            'amount' => 100, 'source_wallet_id' => $wallet->id,
            'reversed_document_id' => $original->id, 'created_by' => $user->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'reversal', 'status' => 'draft',
            'amount' => 100, 'source_wallet_id' => $wallet->id,
            'reversed_document_id' => $original->id, 'created_by' => $user->id,
        ]);
    }
}
