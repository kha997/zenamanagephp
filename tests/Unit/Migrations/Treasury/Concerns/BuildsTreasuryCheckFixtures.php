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
 *
 * Every helper accepts an optional `$conn` connection name (default null =
 * the test's default connection). This repository's default PHPUnit
 * connection is always sqlite (phpunit.xml's unforced <env> wins over any
 * real DB_CONNECTION=mysql environment variable a CI job sets) -- the only
 * way to genuinely exercise the real MySQL service in CI is to explicitly
 * target the named "mysql" connection, exactly as this repo's own
 * DocumentWorkflowConcurrencyTest/RfiEscalationConcurrencyTest already do.
 */
trait BuildsTreasuryCheckFixtures
{
    private function tenant(?string $conn = null): Tenant
    {
        return Tenant::on($conn)->create(Tenant::factory()->raw());
    }

    private function user(Tenant $tenant, ?string $conn = null): User
    {
        return User::on($conn)->create(User::factory()->raw(['tenant_id' => $tenant->id]));
    }

    private function project(Tenant $tenant, ?string $conn = null): Project
    {
        return Project::on($conn)->create(Project::factory()->raw(['tenant_id' => $tenant->id]));
    }

    private function wallet(Tenant $tenant, string $name = 'W', ?string $conn = null): TreasuryWallet
    {
        return TreasuryWallet::on($conn)->create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => $name,
        ]);
    }

    private function party(Tenant $tenant, string $name = 'P', ?string $conn = null): TreasuryFinancialParty
    {
        return TreasuryFinancialParty::on($conn)->create([
            'tenant_id' => $tenant->id, 'party_type' => 'vendor', 'name' => $name,
        ]);
    }

    private function financialDocument(
        Tenant $tenant,
        Project $project,
        User $user,
        TreasuryWallet $destinationWallet,
        ?string $conn = null
    ): TreasuryFinancialDocument {
        return TreasuryFinancialDocument::on($conn)->create([
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
        TreasuryFinancialParty $party,
        ?string $conn = null
    ): TreasuryFinancialDocument {
        return TreasuryFinancialDocument::on($conn)->create([
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
        TreasuryFinancialDocument $doc,
        ?string $conn = null
    ): TreasuryPaymentRoute {
        return TreasuryPaymentRoute::on($conn)->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
        ]);
    }

    private function paymentRouteLeg(
        Tenant $tenant,
        TreasuryPaymentRoute $route,
        TreasuryWallet $toWallet,
        ?string $conn = null
    ): TreasuryPaymentRouteLeg {
        return TreasuryPaymentRouteLeg::on($conn)->create([
            'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 1, 'to_wallet_id' => $toWallet->id,
            'amount' => 100, 'status' => 'in_transit',
        ]);
    }

    private function advance(
        Tenant $tenant,
        Project $project,
        TreasuryFinancialParty $party,
        TreasuryFinancialDocument $originatingDoc,
        ?string $conn = null
    ): TreasuryAdvance {
        return TreasuryAdvance::on($conn)->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id,
            'originating_financial_document_id' => $originatingDoc->id, 'amount' => 100,
        ]);
    }

    private function advanceSettlement(Tenant $tenant, TreasuryAdvance $advance, ?string $conn = null): TreasuryAdvanceSettlement
    {
        return TreasuryAdvanceSettlement::on($conn)->create([
            'tenant_id' => $tenant->id, 'advance_id' => $advance->id,
            'settlement_type' => TreasuryAdvanceSettlement::SETTLEMENT_TYPE_APPROVED_EXPENSE,
            'direction' => TreasuryAdvanceSettlement::DIRECTION_APPLY,
            'amount' => 100,
        ]);
    }

    private function contractExpense(Tenant $tenant, Project $project, User $user, ?string $conn = null): ContractExpense
    {
        $contract = Contract::on($conn)->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-CHK-'.uniqid(),
            'title' => 'HĐ test check',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $user->id,
        ]);

        return ContractExpense::on($conn)->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'expense_date' => '2026-08-18',
            'amount' => 5000,
            'category' => 'labor',
            'description' => 'Test expense',
            'recorded_by' => (string) $user->id,
        ]);
    }

    private function materialReceiptLine(Tenant $tenant, Project $project, ?string $conn = null): MaterialReceiptLine
    {
        $receipt = MaterialReceipt::on($conn)->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => $project->id,
            'receipt_number' => 'MR-CHK-'.uniqid(),
            'receipt_date' => '2026-08-18',
        ]);

        $material = Material::on($conn)->create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'MAT-CHK-'.uniqid(),
            'name' => 'Test Material',
            'category' => 'concrete',
            'unit' => 'm3',
            'description' => 'Test material description',
            'is_active' => true,
        ]);

        return MaterialReceiptLine::on($conn)->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => $project->id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 10,
            'unit_cost' => 25,
        ]);
    }

    private function fundChain(Tenant $tenant, Project $project, ?string $conn = null): TreasuryFundChain
    {
        return TreasuryFundChain::on($conn)->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'chain_reference' => 'FC-'.uniqid(),
        ]);
    }
}
