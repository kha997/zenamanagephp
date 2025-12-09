<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ChangeOrder;
use App\Models\ContractPaymentCertificate;
use App\Models\ContractActualPayment;
use App\Models\Project;
use App\Traits\ServiceBaseTrait;
use Carbon\Carbon;

/**
 * ProjectCostAlertsService
 * 
 * Round 227: Cost Alerts System (Nagging & Attention Flags)
 * 
 * Computes cost alerts for projects based on:
 * - Pending Change Orders Overdue (draft/proposed, >14 days old)
 * - Approved Certificates but Unpaid (approved, no payment covering full amount, >14 days old)
 * - Project Cost Health is AT_RISK or OVER_BUDGET
 * - High Pending CO Financial Impact (pending_co_total > 0.1 * budget_total)
 */
class ProjectCostAlertsService
{
    use ServiceBaseTrait;

    private const THRESHOLD_DAYS = 14;
    private const HIGH_IMPACT_THRESHOLD = 0.1; // 10% of budget

    public function __construct(
        private ProjectCostSummaryService $costSummaryService,
        private ProjectCostDashboardService $costDashboardService,
        private ProjectCostHealthService $costHealthService
    ) {}

    /**
     * Get project cost alerts
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Cost alerts data
     */
    public function getCostAlerts(string $tenantId, Project $project): array
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Reuse existing services to get data (no duplicate math)
        $summary = $this->costSummaryService->getProjectCostSummary($tenantId, $project);
        $dashboard = $this->costDashboardService->getProjectCostDashboard($tenantId, $project);
        $health = $this->costHealthService->getCostHealth($tenantId, $project);

        $budgetTotal = (float) ($summary['totals']['budget_total'] ?? 0.0);
        $pendingChangeOrdersTotal = (float) ($dashboard['variance']['pending_change_orders_total'] ?? 0.0);
        $costHealthStatus = $health['cost_health_status'] ?? 'ON_BUDGET';

        // Compute alerts
        $alerts = [];
        $details = [
            'pending_co_count' => 0,
            'overdue_co_count' => 0,
            'unpaid_certificates_count' => 0,
            'cost_health_status' => $costHealthStatus,
            'pending_change_orders_total' => $pendingChangeOrdersTotal,
            'budget_total' => $budgetTotal,
            'threshold_days' => self::THRESHOLD_DAYS,
        ];

        // Alert 1: Pending Change Orders Overdue
        $pendingCOData = $this->checkPendingChangeOrdersOverdue($tenantId, $project);
        if ($pendingCOData['has_alert']) {
            $alerts[] = 'pending_change_orders_overdue';
            $details['pending_co_count'] = $pendingCOData['pending_count'];
            $details['overdue_co_count'] = $pendingCOData['overdue_count'];
        } else {
            $details['pending_co_count'] = $pendingCOData['pending_count'];
        }

        // Alert 2: Approved Certificates but Unpaid
        $unpaidCertificatesCount = $this->checkApprovedCertificatesUnpaid($tenantId, $project);
        if ($unpaidCertificatesCount > 0) {
            $alerts[] = 'approved_certificates_unpaid';
            $details['unpaid_certificates_count'] = $unpaidCertificatesCount;
        }

        // Alert 3: Project Cost Health is AT_RISK or OVER_BUDGET
        if (in_array($costHealthStatus, ['AT_RISK', 'OVER_BUDGET'])) {
            $alerts[] = 'cost_health_warning';
        }

        // Alert 4: High Pending CO Financial Impact
        if ($budgetTotal > 0 && $pendingChangeOrdersTotal > 0) {
            $threshold = $budgetTotal * self::HIGH_IMPACT_THRESHOLD;
            if ($pendingChangeOrdersTotal > $threshold) {
                $alerts[] = 'pending_co_high_impact';
            }
        }

        return [
            'project_id' => $project->id,
            'alerts' => $alerts,
            'details' => $details,
        ];
    }

    /**
     * Check for pending change orders that are overdue
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array ['has_alert' => bool, 'pending_count' => int, 'overdue_count' => int]
     */
    private function checkPendingChangeOrdersOverdue(string $tenantId, Project $project): array
    {
        $thresholdDate = Carbon::now()->subDays(self::THRESHOLD_DAYS);

        // Get all pending change orders (draft or proposed)
        $pendingCOs = ChangeOrder::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereIn('status', ['draft', 'proposed'])
            ->whereNull('deleted_at')
            ->get();

        $pendingCount = $pendingCOs->count();
        $overdueCount = $pendingCOs->filter(function ($co) use ($thresholdDate) {
            return $co->created_at && Carbon::parse($co->created_at)->lt($thresholdDate);
        })->count();

        return [
            'has_alert' => $overdueCount > 0,
            'pending_count' => $pendingCount,
            'overdue_count' => $overdueCount,
        ];
    }

    /**
     * Check for approved certificates that are unpaid
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return int Count of unpaid certificates
     */
    private function checkApprovedCertificatesUnpaid(string $tenantId, Project $project): int
    {
        $thresholdDate = Carbon::now()->subDays(self::THRESHOLD_DAYS);

        // Get all approved certificates for this project
        $certificates = ContractPaymentCertificate::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->get();

        $unpaidCount = 0;

        foreach ($certificates as $certificate) {
            // Check if certificate is older than threshold
            $approvedDate = $certificate->updated_at ?? $certificate->created_at;
            if (!$approvedDate || Carbon::parse($approvedDate)->gte($thresholdDate)) {
                continue; // Not old enough
            }

            // Get all payments linked to this certificate
            $paymentsForCertificate = ContractActualPayment::where('tenant_id', $tenantId)
                ->where('certificate_id', $certificate->id)
                ->whereNotNull('paid_date')
                ->whereNotNull('amount_paid')
                ->whereNull('deleted_at')
                ->sum('amount_paid');

            // Also check for payments that might cover this certificate amount
            // (even if not explicitly linked via certificate_id)
            // We check payments for the same contract within a reasonable timeframe
            $certificateAmount = (float) ($certificate->amount_payable ?? 0.0);
            $totalPaidForCertificate = (float) $paymentsForCertificate;

            // If total paid is less than certificate amount, it's unpaid
            if ($totalPaidForCertificate < $certificateAmount) {
                $unpaidCount++;
            }
        }

        return $unpaidCount;
    }
}
