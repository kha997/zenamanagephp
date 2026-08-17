<?php declare(strict_types=1);

namespace Tests\Unit\Models\Treasury;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforcesRowInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_amount_rejects_zero(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('amount must be > 0');

        TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'document_type' => 'funding',
            'status' => 'draft',
            'amount' => 0,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_positive_amount_rejects_negative(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('amount must be > 0');

        TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'document_type' => 'funding',
            'status' => 'draft',
            'amount' => -10,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_mutually_exclusive_source_pair_rejects_both_set(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $party = \App\Models\Treasury\TreasuryFinancialParty::create([
            'tenant_id' => $tenant->id, 'party_type' => 'vendor', 'name' => 'P',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('source_wallet_id and source_party_id are mutually exclusive');

        TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'document_type' => 'expense',
            'status' => 'draft',
            'amount' => 10,
            'source_wallet_id' => $wallet->id,
            'source_party_id' => $party->id,
            'destination_party_id' => $party->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_allowed_values_rejects_unknown_document_type(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("document_type must be one of");

        TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'document_type' => 'not_a_real_type',
            'status' => 'draft',
            'amount' => 10,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_valid_row_passes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $doc = TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'document_type' => 'funding',
            'status' => 'draft',
            'amount' => 100,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($doc->id);
        $this->assertSame('100.00', (string) $doc->amount);
    }
}
