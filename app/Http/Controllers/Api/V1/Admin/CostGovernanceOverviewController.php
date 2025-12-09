<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\AuditLog;
use App\Models\ChangeOrder;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Services\CostApprovalPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cost Governance Overview Controller
 * 
 * Round 243: Admin Cost Governance Dashboard / Overview
 * 
 * Provides aggregated overview of cost governance status:
 * - Summary of Change Orders, Certificates, Payments (pending, dual approval, blocked)
 * - Top projects by risk
 * - Recent policy events
 */
class CostGovernanceOverviewController extends BaseApiV1Controller
{
    private CostApprovalPolicyService $costApprovalPolicyService;

    public function __construct(CostApprovalPolicyService $costApprovalPolicyService)
    {
        $this->costApprovalPolicyService = $costApprovalPolicyService;
        
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401, null, 'UNAUTHENTICATED');
            }
            
            // Check if user has system.cost_governance.view permission
            if (!$this->hasPermission($user, 'system.cost_governance.view')) {
                Log::warning('User attempted to access cost governance overview without permission', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'url' => $request->url(),
                ]);
                
                return $this->errorResponse(
                    'Insufficient permissions',
                    403,
                    ['details' => 'system.cost_governance.view permission required'],
                    'PERMISSION_DENIED'
                );
            }
            
            return $next($request);
        });
    }
    
    /**
     * Check if user has specific permission
     */
    private function hasPermission($user, string $permission): bool
    {
        // Super admin has all permissions
        if ($user->role === 'super_admin') {
            return true;
        }
        
        $role = $user->role ?? 'member';
        $permissions = config('permissions.roles.' . $role, []);
        
        if (in_array('*', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }

    /**
     * Get cost governance overview
     * 
     * GET /api/v1/admin/cost-governance-overview
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Build summary
            $summary = $this->buildSummary($tenantId);
            
            // Build top projects by risk
            $topProjectsByRisk = $this->buildTopProjectsByRisk($tenantId);
            
            // Build recent policy events
            $recentPolicyEvents = $this->buildRecentPolicyEvents($tenantId);
            
            $data = [
                'summary' => $summary,
                'top_projects_by_risk' => $topProjectsByRisk,
                'recent_policy_events' => $recentPolicyEvents,
            ];

            return $this->successResponse($data, 'Cost governance overview retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'index']);
            return $this->errorResponse('Failed to retrieve cost governance overview', 500, null, 'OVERVIEW_FETCH_ERROR');
        }
    }

    /**
     * Build summary counts for CO, Certificates, Payments
     */
    private function buildSummary(string $tenantId): array
    {
        // Change Orders summary
        $coQuery = ChangeOrder::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');
        
        $coTotal = (clone $coQuery)->count();
        $coPendingApproval = (clone $coQuery)->where('status', 'proposed')->count();
        $coAwaitingDualApproval = (clone $coQuery)
            ->where('requires_dual_approval', true)
            ->whereNotNull('first_approved_by')
            ->whereNull('second_approved_by')
            ->count();
        
        // CO blocked by policy: check audit logs for policy-related blocks in last 30 days
        $coBlockedByPolicy = $this->countBlockedByPolicy($tenantId, 'ChangeOrder', 30);
        
        // Certificates summary
        $certQuery = ContractPaymentCertificate::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');
        
        $certTotal = (clone $certQuery)->count();
        $certPendingApproval = (clone $certQuery)->where('status', 'submitted')->count();
        $certAwaitingDualApproval = (clone $certQuery)
            ->where('requires_dual_approval', true)
            ->whereNotNull('first_approved_by')
            ->whereNull('second_approved_by')
            ->count();
        
        $certBlockedByPolicy = $this->countBlockedByPolicy($tenantId, 'ContractPaymentCertificate', 30);
        
        // Payments summary
        // Note: ContractActualPayment uses contract_payments table, filter by paid_date/amount_paid
        $paymentQuery = ContractActualPayment::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('paid_date')
            ->whereNotNull('amount_paid');
        
        $paymentTotal = (clone $paymentQuery)->count();
        // Payments don't have a "submitted" status, so pending approval = those without paid_date or with status pending
        // For now, we'll count payments that might need approval (those with status field if exists)
        $paymentPendingApproval = (clone $paymentQuery)
            ->where(function ($q) {
                $q->whereNull('paid_date')
                  ->orWhere('status', 'pending')
                  ->orWhere('status', 'draft');
            })
            ->count();
        $paymentAwaitingDualApproval = (clone $paymentQuery)
            ->where('requires_dual_approval', true)
            ->whereNotNull('first_approved_by')
            ->whereNull('second_approved_by')
            ->count();
        
        $paymentBlockedByPolicy = $this->countBlockedByPolicy($tenantId, 'ContractActualPayment', 30);
        
        return [
            'change_orders' => [
                'total' => $coTotal,
                'pending_approval' => $coPendingApproval,
                'awaiting_dual_approval' => $coAwaitingDualApproval,
                'blocked_by_policy' => $coBlockedByPolicy,
            ],
            'certificates' => [
                'total' => $certTotal,
                'pending_approval' => $certPendingApproval,
                'awaiting_dual_approval' => $certAwaitingDualApproval,
                'blocked_by_policy' => $certBlockedByPolicy,
            ],
            'payments' => [
                'total' => $paymentTotal,
                'pending_approval' => $paymentPendingApproval,
                'awaiting_dual_approval' => $paymentAwaitingDualApproval,
                'blocked_by_policy' => $paymentBlockedByPolicy,
            ],
        ];
    }

    /**
     * Count items blocked by policy in last N days
     */
    private function countBlockedByPolicy(string $tenantId, string $entityType, int $days = 30): int
    {
        $since = now()->subDays($days);
        
        // Look for audit logs with policy-related actions
        // Policy blocks might be logged as: co.approval_blocked, certificate.approval_blocked, payment.approval_blocked
        // Or in payload_after with policy codes
        $blockedActions = [
            'co.approval_blocked',
            'certificate.approval_blocked',
            'payment.approval_blocked',
            'co.policy_blocked',
            'certificate.policy_blocked',
            'payment.policy_blocked',
        ];
        
        $count = AuditLog::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->whereIn('action', $blockedActions)
            ->where('created_at', '>=', $since)
            ->distinct('entity_id')
            ->count('entity_id');
        
        // Also check payload_after for policy codes
        $additionalCount = AuditLog::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('created_at', '>=', $since)
            ->where(function ($q) {
                $q->whereJsonContains('payload_after->code', 'policy.threshold_exceeded')
                  ->orWhereJsonContains('payload_after->code', 'policy.over_budget');
            })
            ->distinct('entity_id')
            ->count('entity_id');
        
        return max($count, $additionalCount);
    }

    /**
     * Build top projects by risk
     */
    private function buildTopProjectsByRisk(string $tenantId): array
    {
        // Get all projects for tenant
        $projects = Project::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get(['id', 'name']);
        
        $projectRisks = [];
        
        foreach ($projects as $project) {
            // Count pending COs
            $pendingCo = ChangeOrder::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('status', 'proposed')
                ->whereNull('deleted_at')
                ->count();
            
            // Count pending certificates
            $pendingCertificates = ContractPaymentCertificate::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('status', 'submitted')
                ->whereNull('deleted_at')
                ->count();
            
            // Count pending payments
            $pendingPayments = ContractActualPayment::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->whereNotNull('paid_date')
                ->whereNotNull('amount_paid')
                ->where(function ($q) {
                    $q->whereNull('paid_date')
                      ->orWhere('status', 'pending')
                      ->orWhere('status', 'draft');
                })
                ->count();
            
            // Count awaiting dual approval (any type)
            $awaitingDualApproval = 0;
            $awaitingDualApproval += ChangeOrder::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('requires_dual_approval', true)
                ->whereNotNull('first_approved_by')
                ->whereNull('second_approved_by')
                ->whereNull('deleted_at')
                ->count();
            $awaitingDualApproval += ContractPaymentCertificate::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('requires_dual_approval', true)
                ->whereNotNull('first_approved_by')
                ->whereNull('second_approved_by')
                ->whereNull('deleted_at')
                ->count();
            $awaitingDualApproval += ContractActualPayment::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('requires_dual_approval', true)
                ->whereNotNull('first_approved_by')
                ->whereNull('second_approved_by')
                ->whereNull('deleted_at')
                ->count();
            
            // Count policy blocked items
            $policyBlockedItems = 0;
            $policyBlockedItems += $this->countBlockedByPolicyForProject($tenantId, $project->id, 'ChangeOrder', 30);
            $policyBlockedItems += $this->countBlockedByPolicyForProject($tenantId, $project->id, 'ContractPaymentCertificate', 30);
            $policyBlockedItems += $this->countBlockedByPolicyForProject($tenantId, $project->id, 'ContractActualPayment', 30);
            
            // Calculate over budget percent (reuse logic from CostApprovalPolicyService)
            $overBudgetPercent = $this->calculateProjectOverBudgetPercent($project->id, $tenantId);
            
            // Only include projects with some risk indicators
            if ($pendingCo > 0 || $pendingCertificates > 0 || $pendingPayments > 0 || 
                $awaitingDualApproval > 0 || $policyBlockedItems > 0 || ($overBudgetPercent !== null && $overBudgetPercent > 0)) {
                $projectRisks[] = [
                    'project_id' => $project->id,
                    'project_name' => $project->name ?? 'Unnamed Project',
                    'pending_co' => $pendingCo,
                    'pending_certificates' => $pendingCertificates,
                    'pending_payments' => $pendingPayments,
                    'awaiting_dual_approval' => $awaitingDualApproval,
                    'policy_blocked_items' => $policyBlockedItems,
                    'over_budget_percent' => $overBudgetPercent,
                ];
            }
        }
        
        // Sort by risk: policy_blocked_items desc, then awaiting_dual_approval desc, then pending_approval desc
        usort($projectRisks, function ($a, $b) {
            if ($a['policy_blocked_items'] !== $b['policy_blocked_items']) {
                return $b['policy_blocked_items'] <=> $a['policy_blocked_items'];
            }
            if ($a['awaiting_dual_approval'] !== $b['awaiting_dual_approval']) {
                return $b['awaiting_dual_approval'] <=> $a['awaiting_dual_approval'];
            }
            $aPending = $a['pending_co'] + $a['pending_certificates'] + $a['pending_payments'];
            $bPending = $b['pending_co'] + $b['pending_certificates'] + $b['pending_payments'];
            return $bPending <=> $aPending;
        });
        
        // Return top 10
        return array_slice($projectRisks, 0, 10);
    }

    /**
     * Count blocked items by policy for a specific project
     */
    private function countBlockedByPolicyForProject(string $tenantId, string $projectId, string $entityType, int $days = 30): int
    {
        $since = now()->subDays($days);
        
        $blockedActions = [
            'co.approval_blocked',
            'certificate.approval_blocked',
            'payment.approval_blocked',
            'co.policy_blocked',
            'certificate.policy_blocked',
            'payment.policy_blocked',
        ];
        
        return AuditLog::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->where('entity_type', $entityType)
            ->whereIn('action', $blockedActions)
            ->where('created_at', '>=', $since)
            ->distinct('entity_id')
            ->count('entity_id');
    }

    /**
     * Calculate project over budget percent (reuse logic from CostApprovalPolicyService)
     */
    private function calculateProjectOverBudgetPercent(string $projectId, string $tenantId): ?float
    {
        try {
            // Get project budget total
            $budgetTotal = (float) DB::table('project_budget_lines')
                ->where('tenant_id', $tenantId)
                ->where('project_id', $projectId)
                ->whereNull('deleted_at')
                ->sum('amount_budget') ?? 0.0;

            if ($budgetTotal <= 0) {
                return null;
            }

            // Get all contracts for this project
            $contracts = DB::table('contracts')
                ->where('tenant_id', $tenantId)
                ->where('project_id', $projectId)
                ->whereNull('deleted_at')
                ->get();

            // Calculate current contract total (base + approved COs)
            $contractCurrentTotal = 0.0;
            foreach ($contracts as $contract) {
                // Get current_amount (base_amount + sum of approved COs)
                $baseAmount = (float) ($contract->base_amount ?? 0.0);
                $coTotal = (float) DB::table('change_orders')
                    ->where('tenant_id', $tenantId)
                    ->where('contract_id', $contract->id)
                    ->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->sum('amount_delta') ?? 0.0;
                $contractCurrentTotal += $baseAmount + $coTotal;
            }

            // Calculate over-budget percentage
            if ($contractCurrentTotal <= $budgetTotal) {
                return 0.0;
            }

            $overBudgetAmount = $contractCurrentTotal - $budgetTotal;
            $overBudgetPercent = ($overBudgetAmount / $budgetTotal) * 100;

            return round($overBudgetPercent, 2);
        } catch (\Exception $e) {
            Log::warning('Failed to calculate over budget percent', [
                'project_id' => $projectId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Build recent policy events
     */
    private function buildRecentPolicyEvents(string $tenantId): array
    {
        $since = now()->subDays(30);
        
        // Get audit logs with policy-related actions
        $policyActions = [
            'co.approval_blocked',
            'certificate.approval_blocked',
            'payment.approval_blocked',
            'co.policy_blocked',
            'certificate.policy_blocked',
            'payment.policy_blocked',
        ];
        
        $logs = AuditLog::where('tenant_id', $tenantId)
            ->whereIn('action', $policyActions)
            ->where('created_at', '>=', $since)
            ->orWhere(function ($q) use ($tenantId, $since) {
                $q->where('tenant_id', $tenantId)
                  ->where('created_at', '>=', $since)
                  ->where(function ($subQ) {
                      $subQ->whereJsonContains('payload_after->code', 'policy.threshold_exceeded')
                           ->orWhereJsonContains('payload_after->code', 'policy.over_budget');
                  });
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        $events = [];
        
        foreach ($logs as $log) {
            $type = $this->determineEntityType($log->entity_type);
            if (!$type) {
                continue;
            }
            
            $payloadAfter = $log->payload_after ?? [];
            $code = $payloadAfter['code'] ?? 'policy.blocked';
            $amount = $payloadAfter['amount'] ?? null;
            $threshold = $payloadAfter['threshold'] ?? $payloadAfter['threshold_percent'] ?? null;
            
            // Get project name
            $projectName = null;
            if ($log->project_id) {
                $project = Project::find($log->project_id);
                $projectName = $project?->name;
            }
            
            $events[] = [
                'type' => $type,
                'entity_id' => $log->entity_id ?? '',
                'project_id' => $log->project_id ?? '',
                'project_name' => $projectName,
                'code' => $code,
                'amount' => $amount,
                'threshold' => $threshold,
                'created_at' => $log->created_at->toISOString(),
            ];
        }
        
        return $events;
    }

    /**
     * Determine entity type for response
     */
    private function determineEntityType(?string $entityType): ?string
    {
        return match ($entityType) {
            'ChangeOrder' => 'co',
            'ContractPaymentCertificate' => 'certificate',
            'ContractActualPayment' => 'payment',
            default => null,
        };
    }
}
