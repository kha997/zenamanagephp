<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\ChangeOrder;
use App\Models\ContractPaymentCertificate;
use App\Traits\ServiceBaseTrait;
use Carbon\Carbon;

/**
 * ProjectCostFlowStatusService
 * 
 * Round 232: Project Cost Flow Status
 * 
 * Computes unified approval workflow status for project cost entities:
 * - Change Orders (CO)
 * - Payment Certificates
 * - Payments (future extension)
 * 
 * Status hierarchy (in priority order):
 * 1. BLOCKED (red): Any rejected CO or Certificate
 * 2. DELAYED (orange): Any pending approval > threshold days
 * 3. PENDING_APPROVAL (blue): Any pending approval within threshold
 * 4. OK (green): All approvals resolved
 */
class ProjectCostFlowStatusService
{
    use ServiceBaseTrait;

    /**
     * Default delay thresholds (in days)
     */
    private const DEFAULT_CO_DELAY_THRESHOLD = 14;
    private const DEFAULT_CERTIFICATE_DELAY_THRESHOLD = 14;

    /**
     * Get project cost flow status
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Flow status data
     */
    public function getFlowStatus(string $tenantId, Project $project): array
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Get project timezone if available (from settings or default)
        $timezone = $project->settings['timezone'] ?? config('app.timezone', 'UTC');
        $now = Carbon::now($timezone);

        // Query Change Orders for this project
        $changeOrders = ChangeOrder::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->get();

        // Query Payment Certificates for this project
        $certificates = ContractPaymentCertificate::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->get();

        // Analyze Change Orders
        $coMetrics = $this->analyzeChangeOrders($changeOrders, $now, $timezone);
        
        // Analyze Certificates
        $certMetrics = $this->analyzeCertificates($certificates, $now, $timezone);

        // Compute overall status (priority: BLOCKED > DELAYED > PENDING_APPROVAL > OK)
        $status = $this->computeOverallStatus($coMetrics, $certMetrics);

        return [
            'project_id' => $project->id,
            'status' => $status,
            'metrics' => [
                'pending_change_orders' => $coMetrics['pending'],
                'delayed_change_orders' => $coMetrics['delayed'],
                'rejected_change_orders' => $coMetrics['rejected'],
                'pending_certificates' => $certMetrics['pending'],
                'delayed_certificates' => $certMetrics['delayed'],
                'rejected_certificates' => $certMetrics['rejected'],
            ],
        ];
    }

    /**
     * Analyze Change Orders and compute metrics
     * 
     * @param \Illuminate\Database\Eloquent\Collection $changeOrders
     * @param Carbon $now Current time
     * @param string $timezone Project timezone
     * @return array Metrics
     */
    private function analyzeChangeOrders($changeOrders, Carbon $now, string $timezone): array
    {
        $pending = 0;
        $delayed = 0;
        $rejected = 0;

        foreach ($changeOrders as $co) {
            if ($co->status === 'rejected') {
                $rejected++;
            } elseif ($co->status === 'proposed') {
                $pending++;
                
                // Check if delayed (updated_at > threshold days ago)
                $updatedAt = Carbon::parse($co->updated_at, $timezone);
                $daysSinceUpdate = $now->diffInDays($updatedAt);
                
                if ($daysSinceUpdate > self::DEFAULT_CO_DELAY_THRESHOLD) {
                    $delayed++;
                }
            }
        }

        return [
            'pending' => $pending,
            'delayed' => $delayed,
            'rejected' => $rejected,
        ];
    }

    /**
     * Analyze Payment Certificates and compute metrics
     * 
     * @param \Illuminate\Database\Eloquent\Collection $certificates
     * @param Carbon $now Current time
     * @param string $timezone Project timezone
     * @return array Metrics
     */
    private function analyzeCertificates($certificates, Carbon $now, string $timezone): array
    {
        $pending = 0;
        $delayed = 0;
        $rejected = 0;

        foreach ($certificates as $cert) {
            if ($cert->status === 'rejected') {
                $rejected++;
            } elseif ($cert->status === 'submitted') {
                $pending++;
                
                // Check if delayed (updated_at > threshold days ago)
                $updatedAt = Carbon::parse($cert->updated_at, $timezone);
                $daysSinceUpdate = $now->diffInDays($updatedAt);
                
                if ($daysSinceUpdate > self::DEFAULT_CERTIFICATE_DELAY_THRESHOLD) {
                    $delayed++;
                }
            }
        }

        return [
            'pending' => $pending,
            'delayed' => $delayed,
            'rejected' => $rejected,
        ];
    }

    /**
     * Compute overall status from metrics
     * 
     * Priority: BLOCKED > DELAYED > PENDING_APPROVAL > OK
     * 
     * @param array $coMetrics Change Order metrics
     * @param array $certMetrics Certificate metrics
     * @return string Status
     */
    private function computeOverallStatus(array $coMetrics, array $certMetrics): string
    {
        // 1. BLOCKED: Any rejected items
        if ($coMetrics['rejected'] > 0 || $certMetrics['rejected'] > 0) {
            return 'BLOCKED';
        }

        // 2. DELAYED: Any delayed items (pending > threshold days)
        if ($coMetrics['delayed'] > 0 || $certMetrics['delayed'] > 0) {
            return 'DELAYED';
        }

        // 3. PENDING_APPROVAL: Any pending items (within threshold)
        if ($coMetrics['pending'] > 0 || $certMetrics['pending'] > 0) {
            return 'PENDING_APPROVAL';
        }

        // 4. OK: All clear
        return 'OK';
    }
}
