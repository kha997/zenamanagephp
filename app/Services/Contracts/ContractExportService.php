<?php declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use App\Models\ContractPayment;
use App\Services\Reports\ContractsReportsService;
use Carbon\Carbon;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Log;

/**
 * Service for exporting contracts and cost schedules to CSV
 * 
 * Round 47: Cost Overruns Dashboard + Export
 * 
 * Provides CSV export functionality for contracts list and detailed cost schedules.
 */
class ContractExportService
{
    protected ContractsReportsService $contractsReportsService;

    public function __construct(ContractsReportsService $contractsReportsService)
    {
        $this->contractsReportsService = $contractsReportsService;
    }

    /**
     * Export contracts list to CSV
     * 
     * Round 48: Added docblock explaining export behavior
     * 
     * Export includes:
     * - Budget lines / expenses: Excludes cancelled items (status != 'cancelled')
     *   Metrics (budget_total, actual_total, budget_vs_contract_diff, contract_vs_actual_diff)
     *   only count active budget lines and expenses.
     * - Payments: Includes all statuses (including cancelled) for complete payment history.
     *   However, overdue/summary calculations only count non-cancelled payments.
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param array $filters Same filters as contracts list:
     *   - search, status, client_id, project_id, signed_from, signed_to, sort_by, sort_direction
     * @return StreamedResponse CSV file download
     */
    public function exportContractsForTenant(string $tenantId, array $filters = []): StreamedResponse
    {
        $query = Contract::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        // Apply filters (same as ContractsController::index)
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['signed_from'])) {
            $query->where('signed_at', '>=', $filters['signed_from']);
        }
        if (!empty($filters['signed_to'])) {
            $query->where('signed_at', '<=', $filters['signed_to']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $contracts = $query->with(['client', 'project'])->get();

        $filename = 'contracts_export_' . $tenantId . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ];

        return response()->stream(function () use ($contracts, $tenantId) {
            $out = fopen('php://output', 'w');

            // Add BOM for UTF-8 (Excel compatibility)
            fwrite($out, "\xEF\xBB\xBF");

            // CSV headers
            fputcsv($out, [
                'Contract Code',
                'Contract Name',
                'Status',
                'Client Name',
                'Project Name',
                'Total Value',
                'Currency',
                'Signed At',
                'Budget Total',
                'Actual Total',
                'Budget vs Contract Diff',
                'Contract vs Actual Diff',
                'Payments Scheduled Total',
                'Payments Paid Total',
                'Overdue Payments Count',
                'Overdue Payments Total',
            ]);

            foreach ($contracts as $contract) {
                // Get cost summary
                $summary = $this->contractsReportsService->getContractCostSummary($tenantId, $contract);

                // Calculate overdue payments: use centralized scope
                $overduePayments = $contract->payments()
                    ->overdue()
                    ->get();

                $overdueCount = $overduePayments->count();
                $overdueTotal = (float) $overduePayments->sum('amount') ?? 0.0;

                fputcsv($out, [
                    $contract->code,
                    $contract->name,
                    $contract->status,
                    $contract->client?->name ?? '',
                    $contract->project?->name ?? '',
                    $summary['contract_value'] ?? '',
                    $contract->currency,
                    $contract->signed_at?->format('Y-m-d') ?? '',
                    $summary['budget_total'],
                    $summary['actual_total'],
                    $summary['budget_vs_contract_diff'] ?? '',
                    $summary['contract_vs_actual_diff'] ?? '',
                    $summary['payments_scheduled_total'],
                    $summary['payments_paid_total'],
                    $overdueCount,
                    $overdueTotal,
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Export contract cost schedule to CSV
     * 
     * Round 48: Added docblock explaining export behavior
     * 
     * Export includes:
     * - Budget lines: Excludes cancelled (status != 'cancelled')
     * - Expenses: Excludes cancelled (status != 'cancelled')
     * - Payments: Includes all statuses (including cancelled) to show complete payment history.
     *   The status field is included in the CSV so users can see which payments were cancelled.
     * 
     * This ensures:
     * - Budget/expense metrics reflect only active items
     * - Payment history is complete and auditable (cancelled payments are part of the record)
     * 
     * @param string $tenantId Tenant ID (for validation)
     * @param Contract $contract The contract
     * @return StreamedResponse CSV file download
     * @throws \InvalidArgumentException If contract does not belong to tenant
     */
    public function exportContractCostSchedule(string $tenantId, Contract $contract): StreamedResponse
    {
        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            throw new \InvalidArgumentException('Contract does not belong to tenant');
        }

        // Get budget lines (active, not cancelled, not deleted)
        $budgetLines = $contract->budgetLines()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Get payments (not deleted)
        $payments = $contract->payments()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->get();

        // Get expenses (active, not cancelled, not deleted)
        $expenses = $contract->expenses()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->orderBy('sort_order')
            ->orderBy('incurred_at')
            ->orderBy('name')
            ->get();

        $filename = 'contract_cost_schedule_' . $contract->code . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ];

        return response()->stream(function () use ($budgetLines, $payments, $expenses) {
            $out = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            // CSV headers
            fputcsv($out, [
                'Type',
                'Code',
                'Name',
                'Category',
                'Vendor Name',
                'Status',
                'Due/Incurred Date',
                'Quantity',
                'Unit Price/Unit Cost',
                'Amount',
                'Currency',
                'Notes',
            ]);

            // Write budget lines
            foreach ($budgetLines as $line) {
                fputcsv($out, [
                    'budget_line',
                    $line->code ?? '',
                    $line->name,
                    $line->category ?? '',
                    '', // No vendor for budget lines
                    $line->status,
                    '', // No date for budget lines
                    $line->quantity ?? '',
                    $line->unit_price ?? '',
                    $line->total_amount ?? '',
                    $line->currency ?? '',
                    $line->notes ?? '',
                ]);
            }

            // Write payments
            foreach ($payments as $payment) {
                fputcsv($out, [
                    'payment',
                    $payment->code ?? '',
                    $payment->name,
                    $payment->type ?? '',
                    '', // No vendor for payments
                    $payment->status,
                    $payment->due_date?->format('Y-m-d') ?? '',
                    '', // No quantity for payments
                    '', // No unit price for payments
                    $payment->amount,
                    $payment->currency,
                    $payment->notes ?? '',
                ]);
            }

            // Write expenses
            foreach ($expenses as $expense) {
                fputcsv($out, [
                    'expense',
                    $expense->code ?? '',
                    $expense->name,
                    $expense->category ?? '',
                    $expense->vendor_name ?? '',
                    $expense->status,
                    $expense->incurred_at?->format('Y-m-d') ?? '',
                    $expense->quantity ?? '',
                    $expense->unit_cost ?? '',
                    $expense->amount,
                    $expense->currency,
                    $expense->notes ?? '',
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }
}

