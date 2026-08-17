<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryFundChain;
use App\Models\Treasury\TreasuryFundChainMember;
use App\Models\Treasury\TreasuryPaymentRoute;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryFundChainMembersSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_fund_chain_members'));
        $this->assertTrue(Schema::hasColumns('treasury_fund_chain_members', [
            'id', 'tenant_id', 'fund_chain_id', 'member_financial_document_id',
            'member_payment_route_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_exactly_one_member_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $fundChain = TreasuryFundChain::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'chain_reference' => 'FC-1',
        ]);
        $document = TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'document_type' => 'funding',
            'status' => 'draft',
            'amount' => 100,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
        $route = TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'total_allocated_amount' => 100,
            'status' => 'planned',
            'linked_financial_document_id' => $document->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exactly one of/');

        TreasuryFundChainMember::create([
            'tenant_id' => $tenant->id,
            'fund_chain_id' => $fundChain->id,
            'member_financial_document_id' => $document->id,
            'member_payment_route_id' => $route->id,
        ]);
    }
}
