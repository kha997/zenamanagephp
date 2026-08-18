<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury\Concerns;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryAdvance;
use App\Models\Treasury\TreasuryAdvanceSettlement;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryFundChain;
use App\Models\Treasury\TreasuryPaymentRoute;
use App\Models\Treasury\TreasuryPaymentRouteLeg;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;

/**
 * Shared fixture builders for GAP-038 native-CHECK-constraint conformance
 * tests. Every helper here uses Eloquent (so EnforcesRowInvariants gladly
 * validates the *setup* rows) -- only the row actually under test in each
 * test method bypasses Eloquent via a raw DB::table()->insert(), which is
 * the entire point: proving the database itself, not the trait, rejects an
 * invalid row.
 */
trait BuildsTreasuryCheckFixtures
{
    private function tenant(): Tenant
    {
        return Tenant::factory()->create();
    }

    private function user(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function project(Tenant $tenant): Project
    {
        return Project::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function wallet(Tenant $tenant, string $name = 'W'): TreasuryWallet
    {
        return TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => $name,
        ]);
    }

    private function party(Tenant $tenant, string $name = 'P'): TreasuryFinancialParty
    {
        return TreasuryFinancialParty::create([
            'tenant_id' => $tenant->id, 'party_type' => 'vendor', 'name' => $name,
        ]);
    }

    private function financialDocument(
        Tenant $tenant,
        Project $project,
        User $user,
        TreasuryWallet $destinationWallet
    ): TreasuryFinancialDocument {
        return TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft',
            'amount' => 100, 'destination_wallet_id' => $destinationWallet->id,
            'created_by' => $user->id,
        ]);
    }

    private function advanceOriginatingDocument(
        Tenant $tenant,
        Project $project,
        User $user,
        TreasuryWallet $sourceWallet,
        TreasuryFinancialParty $party
    ): TreasuryFinancialDocument {
        return TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'advance', 'status' => 'posted_unreconciled',
            'amount' => 100, 'source_wallet_id' => $sourceWallet->id,
            'destination_party_id' => $party->id,
            'created_by' => $user->id,
        ]);
    }

    private function paymentRoute(
        Tenant $tenant,
        Project $project,
        TreasuryFinancialDocument $doc
    ): TreasuryPaymentRoute {
        return TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
        ]);
    }

    private function paymentRouteLeg(
        Tenant $tenant,
        TreasuryPaymentRoute $route,
        TreasuryWallet $toWallet
    ): TreasuryPaymentRouteLeg {
        return TreasuryPaymentRouteLeg::create([
            'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 1, 'to_wallet_id' => $toWallet->id,
            'amount' => 100, 'status' => 'in_transit',
        ]);
    }

    private function advance(
        Tenant $tenant,
        Project $project,
        TreasuryFinancialParty $party,
        TreasuryFinancialDocument $originatingDoc
    ): TreasuryAdvance {
        return TreasuryAdvance::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id,
            'originating_financial_document_id' => $originatingDoc->id, 'amount' => 100,
        ]);
    }

    private function advanceSettlement(Tenant $tenant, TreasuryAdvance $advance): TreasuryAdvanceSettlement
    {
        return TreasuryAdvanceSettlement::create([
            'tenant_id' => $tenant->id, 'advance_id' => $advance->id,
            'settlement_type' => TreasuryAdvanceSettlement::SETTLEMENT_TYPE_APPROVED_EXPENSE,
            'direction' => TreasuryAdvanceSettlement::DIRECTION_APPLY,
            'amount' => 100,
        ]);
    }

    private function contractExpense(Tenant $tenant, Project $project, User $user): ContractExpense
    {
        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-CHK-'.uniqid(),
            'title' => 'HĐ test check',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $user->id,
        ]);

        return ContractExpense::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'expense_date' => '2026-08-18',
            'amount' => 5000,
            'category' => 'labor',
            'description' => 'Test expense',
            'recorded_by' => (string) $user->id,
        ]);
    }

    private function materialReceiptLine(Tenant $tenant, Project $project): MaterialReceiptLine
    {
        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => $project->id,
            'receipt_number' => 'MR-CHK-'.uniqid(),
            'receipt_date' => '2026-08-18',
        ]);

        $material = Material::query()->create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'MAT-CHK-'.uniqid(),
            'name' => 'Test Material',
            'category' => 'concrete',
            'unit' => 'm3',
            'description' => 'Test material description',
            'is_active' => true,
        ]);

        return MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => $project->id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 10,
            'unit_cost' => 25,
        ]);
    }

    private function fundChain(Tenant $tenant, Project $project): TreasuryFundChain
    {
        return TreasuryFundChain::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'chain_reference' => 'FC-'.uniqid(),
        ]);
    }
}
