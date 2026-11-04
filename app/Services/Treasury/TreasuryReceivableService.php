<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use Illuminate\Support\Facades\DB;

/**
 * GAP-105 R-1 — investor receivable per contract.
 *
 * The receivable belongs to the contract lifecycle (SSOT OWN-2026-009 §7; GAP-037: Treasury does not duplicate
 * ContractPayment): received = paid ContractPayment instalments. Money that reached a Treasury wallet through a route
 * linked to a contract payment is informational only. Treasury funding is not tied to any contract, so it is reported
 * apart and never subtracted from the receivable. Missing contracts are no_data, never zero (§8).
 */
class TreasuryReceivableService
{
    /**
     * @return array{
     *     availability: string,
     *     contracts: list<array{id: string, code: string, title: string, value: string, paid: string, arrived_in_treasury: string, outstanding: string, overpaid: bool}>,
     *     totals: array{value: string|null, paid: string|null, arrived_in_treasury: string|null, outstanding: string|null},
     *     unlinked_funding: string
     * }
     */
    public function forProject(Project $project): array
    {
        $tenantId = (string) $project->tenant_id;
        $contracts = DB::table('contracts')
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $project->id)
            ->where('status', '!=', Contract::STATUS_CANCELLED)
            ->orderBy('code')
            ->get(['id', 'code', 'title', 'total_value']);
        $contractIds = $contracts->pluck('id')->map(static fn ($id): string => (string) $id)->all();

        $paid = DB::table('contract_payments')
            ->where('tenant_id', $tenantId)
            ->whereIn('contract_id', $contractIds)
            ->where('status', ContractPayment::STATUS_PAID)
            ->groupBy('contract_id')
            ->selectRaw('contract_id, ROUND(COALESCE(SUM(amount), 0) * 100) AS cents')
            ->pluck('cents', 'contract_id');

        $arrived = DB::table('treasury_payment_routes')
            ->join('contract_payments', 'contract_payments.id', '=', 'treasury_payment_routes.linked_contract_payment_id')
            ->where('treasury_payment_routes.tenant_id', $tenantId)
            ->where('treasury_payment_routes.project_id', (string) $project->id)
            ->where('treasury_payment_routes.status', Route::STATUS_COMPLETED)
            ->whereIn('contract_payments.contract_id', $contractIds)
            ->groupBy('contract_payments.contract_id')
            ->selectRaw('contract_payments.contract_id AS contract_id, ROUND(COALESCE(SUM(treasury_payment_routes.total_allocated_amount), 0) * 100) AS cents')
            ->pluck('cents', 'contract_id');

        $rows = [];
        $totals = ['value' => 0, 'paid' => 0, 'arrived' => 0];
        foreach ($contracts as $contract) {
            $id = (string) data_get($contract, 'id');
            $value = TreasuryBalanceService::toCents((string) data_get($contract, 'total_value', '0'));
            $paidCents = (int) round((float) ($paid[$id] ?? 0));
            $arrivedCents = (int) round((float) ($arrived[$id] ?? 0));
            $rows[] = [
                'id' => $id,
                'code' => (string) data_get($contract, 'code'),
                'title' => (string) data_get($contract, 'title'),
                'value' => TreasuryBalanceService::fromCents($value),
                'paid' => TreasuryBalanceService::fromCents($paidCents),
                'arrived_in_treasury' => TreasuryBalanceService::fromCents($arrivedCents),
                'outstanding' => TreasuryBalanceService::fromCents($value - $paidCents),
                'overpaid' => $paidCents > $value,
            ];
            $totals['value'] += $value;
            $totals['paid'] += $paidCents;
            $totals['arrived'] += $arrivedCents;
        }

        $hasContracts = $rows !== [];
        $money = static fn (int $cents): ?string => $hasContracts ? TreasuryBalanceService::fromCents($cents) : null;

        return [
            'availability' => $hasContracts ? 'available' : 'no_data',
            'contracts' => $rows,
            'totals' => [
                'value' => $money($totals['value']),
                'paid' => $money($totals['paid']),
                'arrived_in_treasury' => $money($totals['arrived']),
                'outstanding' => $money($totals['value'] - $totals['paid']),
            ],
            'unlinked_funding' => $this->postedFunding($project),
        ];
    }

    private function postedFunding(Project $project): string
    {
        $cents = Document::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', Document::TYPE_FUNDING)
            ->whereIn('status', [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED])
            ->selectRaw('ROUND(COALESCE(SUM(amount), 0) * 100) AS total_cents')
            ->value('total_cents');

        return TreasuryBalanceService::fromCents((int) round((float) $cents));
    }
}
