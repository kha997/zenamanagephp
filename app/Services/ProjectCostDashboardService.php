<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Traits\ServiceBaseTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ProjectCostDashboardService
 * 
 * Round 223: Project Cost Dashboard API (Variance + Timeline + Forecast)
 * 
 * Aggregates project-level cost dashboard data including:
 * - Overall cost summary (reusing Round 222)
 * - Variance and forecast (pending change orders, forecast final cost, variance vs budget and current contract)
 * - Contract-level breakdown of base amount and change orders
 * - Time-series aggregates for approved certificates and actual payments over the last 12 months
 */
class ProjectCostDashboardService
{
    use ServiceBaseTrait;

    public function __construct(
        private ProjectCostSummaryService $costSummaryService
    ) {}

    /**
     * Get project cost dashboard
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Cost dashboard data
     */
    public function getProjectCostDashboard(string $tenantId, Project $project): array
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Reuse Round 222 summary totals
        $summary = $this->costSummaryService->getProjectCostSummary($tenantId, $project);
        $summaryTotals = $summary['totals'];

        // Compute variance and forecast
        $variance = $this->computeVarianceAndForecast($tenantId, $project, $summaryTotals);

        // Compute contract breakdown
        $contracts = $this->computeContractBreakdown($tenantId, $project);

        // Compute time-series (last 12 months)
        $timeSeries = $this->computeTimeSeries($tenantId, $project);

        return [
            'project_id' => $project->id,
            'currency' => $summary['currency'] ?? 'VND',
            'summary' => [
                'budget_total' => $summaryTotals['budget_total'],
                'contract_base_total' => $summaryTotals['contract_base_total'],
                'contract_current_total' => $summaryTotals['contract_current_total'],
                'total_certified_amount' => $summaryTotals['total_certified_amount'],
                'total_paid_amount' => $summaryTotals['total_paid_amount'],
                'outstanding_amount' => $summaryTotals['outstanding_amount'],
            ],
            'variance' => $variance,
            'contracts' => $contracts,
            'time_series' => $timeSeries,
        ];
    }

    /**
     * Compute variance and forecast metrics
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param array $summaryTotals Summary totals from Round 222
     * @return array Variance and forecast data
     */
    private function computeVarianceAndForecast(string $tenantId, Project $project, array $summaryTotals): array
    {
        // Get all change orders for this project (not soft-deleted)
        $changeOrders = ChangeOrder::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->get();

        // Pending change orders: status in ['draft', 'proposed']
        $pendingChangeOrdersTotal = (float) $changeOrders
            ->whereIn('status', ['draft', 'proposed'])
            ->sum('amount_delta') ?? 0.0;

        // Rejected change orders
        $rejectedChangeOrdersTotal = (float) $changeOrders
            ->where('status', 'rejected')
            ->sum('amount_delta') ?? 0.0;

        // Forecast final cost = contract_current_total + pending_change_orders_total
        $forecastFinalCost = $summaryTotals['contract_current_total'] + $pendingChangeOrdersTotal;

        // Variance vs budget = forecast_final_cost - budget_total
        $varianceVsBudget = $forecastFinalCost - $summaryTotals['budget_total'];

        // Variance vs contract current = forecast_final_cost - contract_current_total
        $varianceVsContractCurrent = $forecastFinalCost - $summaryTotals['contract_current_total'];

        return [
            'pending_change_orders_total' => $pendingChangeOrdersTotal,
            'rejected_change_orders_total' => $rejectedChangeOrdersTotal,
            'forecast_final_cost' => $forecastFinalCost,
            'variance_vs_budget' => $varianceVsBudget,
            'variance_vs_contract_current' => $varianceVsContractCurrent,
        ];
    }

    /**
     * Compute contract breakdown
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Contract breakdown data
     */
    private function computeContractBreakdown(string $tenantId, Project $project): array
    {
        // Get all contracts for this project (not soft-deleted)
        $contracts = Contract::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->get();

        // Contract base total: sum of base_amount
        $contractBaseTotal = (float) $contracts->sum('base_amount') ?? 0.0;

        // Get all change orders for this project (not soft-deleted)
        $changeOrders = ChangeOrder::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->get();

        // Approved change orders total
        $changeOrdersApprovedTotal = (float) $changeOrders
            ->where('status', 'approved')
            ->sum('amount_delta') ?? 0.0;

        // Pending change orders total
        $changeOrdersPendingTotal = (float) $changeOrders
            ->whereIn('status', ['draft', 'proposed'])
            ->sum('amount_delta') ?? 0.0;

        // Rejected change orders total
        $changeOrdersRejectedTotal = (float) $changeOrders
            ->where('status', 'rejected')
            ->sum('amount_delta') ?? 0.0;

        // Contract current total: sum of current_amount (using accessor)
        $contractCurrentTotal = 0.0;
        foreach ($contracts as $contract) {
            $contractCurrentTotal += $contract->current_amount ?? 0.0;
        }

        return [
            'contract_base_total' => $contractBaseTotal,
            'change_orders_approved_total' => $changeOrdersApprovedTotal,
            'change_orders_pending_total' => $changeOrdersPendingTotal,
            'change_orders_rejected_total' => $changeOrdersRejectedTotal,
            'contract_current_total' => $contractCurrentTotal,
        ];
    }

    /**
     * Compute time-series data for last 12 months
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Time-series data
     */
    private function computeTimeSeries(string $tenantId, Project $project): array
    {
        // Certificates per month
        $certificatesPerMonth = $this->computeCertificatesPerMonth($tenantId, $project);

        // Payments per month
        $paymentsPerMonth = $this->computePaymentsPerMonth($tenantId, $project);

        return [
            'certificates_per_month' => $certificatesPerMonth,
            'payments_per_month' => $paymentsPerMonth,
        ];
    }

    /**
     * Compute certificates per month for last 12 months
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Certificates per month data
     */
    private function computeCertificatesPerMonth(string $tenantId, Project $project): array
    {
        // Get last 12 months window (including current month)
        $now = Carbon::now();
        $startDate = $now->copy()->subMonths(11)->startOfMonth();

        // Get all approved certificates for this project (not soft-deleted)
        $certificates = ContractPaymentCertificate::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->get();

        // Group by effective month (period_end if not null, otherwise created_at month)
        $grouped = [];
        foreach ($certificates as $certificate) {
            // Determine effective month
            $effectiveDate = $certificate->period_end ?? $certificate->created_at;
            if (!$effectiveDate) {
                continue;
            }

            $carbonDate = Carbon::parse($effectiveDate);
            
            // Only include if within last 12 months
            if ($carbonDate->lt($startDate) || $carbonDate->gt($now)) {
                continue;
            }

            $year = (int) $carbonDate->format('Y');
            $month = (int) $carbonDate->format('n');
            $key = sprintf('%04d-%02d', $year, $month);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'year' => $year,
                    'month' => $month,
                    'amount_payable_approved' => 0.0,
                ];
            }

            $grouped[$key]['amount_payable_approved'] += (float) ($certificate->amount_payable ?? 0.0);
        }

        // Convert to array and sort by year, month
        $result = array_values($grouped);
        usort($result, function ($a, $b) {
            if ($a['year'] !== $b['year']) {
                return $a['year'] <=> $b['year'];
            }
            return $a['month'] <=> $b['month'];
        });

        return $result;
    }

    /**
     * Compute payments per month for last 12 months
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Payments per month data
     */
    private function computePaymentsPerMonth(string $tenantId, Project $project): array
    {
        // Get last 12 months window (including current month)
        $now = Carbon::now();
        $startDate = $now->copy()->subMonths(11)->startOfMonth();

        // Get all actual payments for this project (not soft-deleted)
        // Use scopeActualPayments to filter only Round 221 actual payments
        $payments = ContractActualPayment::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->actualPayments()
            ->whereNull('deleted_at')
            ->get();

        // Group by paid_date month
        $grouped = [];
        foreach ($payments as $payment) {
            if (!$payment->paid_date) {
                continue;
            }

            $carbonDate = Carbon::parse($payment->paid_date);
            
            // Only include if within last 12 months
            if ($carbonDate->lt($startDate) || $carbonDate->gt($now)) {
                continue;
            }

            $year = (int) $carbonDate->format('Y');
            $month = (int) $carbonDate->format('n');
            $key = sprintf('%04d-%02d', $year, $month);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'year' => $year,
                    'month' => $month,
                    'amount_paid' => 0.0,
                ];
            }

            $grouped[$key]['amount_paid'] += (float) ($payment->amount_paid ?? 0.0);
        }

        // Convert to array and sort by year, month
        $result = array_values($grouped);
        usort($result, function ($a, $b) {
            if ($a['year'] !== $b['year']) {
                return $a['year'] <=> $b['year'];
            }
            return $a['month'] <=> $b['month'];
        });

        return $result;
    }
}
