<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryPaymentRoutesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_payment_routes'));
        $this->assertTrue(Schema::hasColumns('treasury_payment_routes', [
            'id', 'tenant_id', 'project_id', 'total_allocated_amount', 'status',
            'linked_financial_document_id', 'linked_contract_payment_id',
            'expected_destination_wallet_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_linked_financial_document_id_is_unique(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);

        \App\Models\Treasury\TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 50, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
        ]);
    }

    public function test_exactly_one_of_linked_document_or_contract_payment_is_enforced(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
        $contract = \App\Models\Contract::factory()->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'created_by' => $user->id,
        ]);
        $contractPayment = \App\Models\ContractPayment::factory()->create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exactly one of/');

        \App\Models\Treasury\TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
            'linked_contract_payment_id' => $contractPayment->id,
        ]);
    }

    public function test_co_nullable_contract_payment_and_expected_wallet_is_enforced(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $contract = \App\Models\Contract::factory()->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'created_by' => $user->id,
        ]);
        $contractPayment = \App\Models\ContractPayment::factory()->create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be both null or both set together/');

        \App\Models\Treasury\TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_contract_payment_id' => $contractPayment->id,
        ]);
    }
}
