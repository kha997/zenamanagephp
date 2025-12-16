<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\CostApprovalPolicy;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\User;
use App\ValueObjects\CostApprovalDecision;
use Illuminate\Support\Facades\DB;

/**
 * Cost Approval Policy Service
 * 
 * Round 239: Cost Approval Policies (Phase 1 - Thresholds & Blocking)
 * Round 241: Cost Dual-Approval Workflow (Phase 2)
 * 
 * Manages cost approval policies and evaluates approval requests against thresholds.
 */
class CostApprovalPolicyService
{
    private TenancyService $tenancyService;

    public function __construct(TenancyService $tenancyService)
    {
        $this->tenancyService = $tenancyService;
    }

    /**
     * Get current policy for tenant
     * 
     * @param string|null $tenantId Tenant ID (if null, resolves from current context)
     * @return CostApprovalPolicy|null
     */
    public function getCurrentPolicyForTenant(?string $tenantId = null): ?CostApprovalPolicy
    {
        if ($tenantId === null) {
            $user = auth()->user();
            if (!$user) {
                return null;
            }
            $tenantId = $this->tenancyService->resolveActiveTenantId($user, request());
        }

        if (!$tenantId) {
            return null;
        }

        return CostApprovalPolicy::where('tenant_id', $tenantId)->first();
    }

    /**
     * Create or update policy for tenant
     * 
     * @param array $data Policy data
     * @param string|null $tenantId Tenant ID (if null, resolves from current context)
     * @return CostApprovalPolicy
     */
    public function upsertPolicyForTenant(array $data, ?string $tenantId = null): CostApprovalPolicy
    {
        if ($tenantId === null) {
            $user = auth()->user();
            if (!$user) {
                throw new \RuntimeException('User not authenticated');
            }
            $tenantId = $this->tenancyService->resolveActiveTenantId($user, request());
        }

        if (!$tenantId) {
            throw new \RuntimeException('Tenant ID not found');
        }

        return CostApprovalPolicy::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'co_dual_threshold_amount' => $data['co_dual_threshold_amount'] ?? null,
                'certificate_dual_threshold_amount' => $data['certificate_dual_threshold_amount'] ?? null,
                'payment_dual_threshold_amount' => $data['payment_dual_threshold_amount'] ?? null,
                'over_budget_threshold_percent' => $data['over_budget_threshold_percent'] ?? null,
            ]
        );
    }

    /**
     * Evaluate Change Order approval
     * 
     * @param ChangeOrder $co Change Order to evaluate
     * @param User $user User attempting approval
     * @return CostApprovalDecision
     */
    public function evaluateChangeOrderApproval(ChangeOrder $co, User $user): CostApprovalDecision
    {
        // If user has unlimited approval permission, always allow
        if ($user->hasPermission('projects.cost.approve_unlimited')) {
            return CostApprovalDecision::allowed();
        }

        $policy = $this->getCurrentPolicyForTenant($co->tenant_id);
        
        // If no policy exists, allow (backward compatible)
        if (!$policy) {
            return CostApprovalDecision::allowed();
        }

        // Check CO threshold
        if ($policy->co_dual_threshold_amount !== null) {
            $amount = abs((float) $co->amount_delta);
            if ($amount >= (float) $policy->co_dual_threshold_amount) {
                return CostApprovalDecision::denied(
                    'Change Order approval requires higher-level approval due to threshold policy.',
                    'policy.threshold_exceeded',
                    [
                        'threshold' => (float) $policy->co_dual_threshold_amount,
                        'amount' => $amount,
                        'type' => 'co',
                    ]
                );
            }
        }

        // Check over-budget threshold
        if ($policy->over_budget_threshold_percent !== null) {
            $overBudgetPercent = $this->calculateProjectOverBudgetPercent($co->project_id, $co->tenant_id, $co->amount_delta);
            if ($overBudgetPercent !== null && $overBudgetPercent > (float) $policy->over_budget_threshold_percent) {
                return CostApprovalDecision::denied(
                    'Change Order approval requires higher-level approval due to over-budget policy.',
                    'policy.over_budget',
                    [
                        'threshold_percent' => (float) $policy->over_budget_threshold_percent,
                        'over_budget_percent' => $overBudgetPercent,
                        'type' => 'co',
                    ]
                );
            }
        }

        return CostApprovalDecision::allowed();
    }

    /**
     * Evaluate Certificate approval
     * 
     * @param ContractPaymentCertificate $certificate Certificate to evaluate
     * @param User $user User attempting approval
     * @return CostApprovalDecision
     */
    public function evaluateCertificateApproval(ContractPaymentCertificate $certificate, User $user): CostApprovalDecision
    {
        // If user has unlimited approval permission, always allow
        if ($user->hasPermission('projects.cost.approve_unlimited')) {
            return CostApprovalDecision::allowed();
        }

        $policy = $this->getCurrentPolicyForTenant($certificate->tenant_id);
        
        // If no policy exists, allow (backward compatible)
        if (!$policy) {
            return CostApprovalDecision::allowed();
        }

        // Check certificate threshold
        if ($policy->certificate_dual_threshold_amount !== null) {
            $amount = abs((float) $certificate->amount_payable);
            if ($amount >= (float) $policy->certificate_dual_threshold_amount) {
                return CostApprovalDecision::denied(
                    'Payment certificate approval requires higher-level approval due to threshold policy.',
                    'policy.threshold_exceeded',
                    [
                        'threshold' => (float) $policy->certificate_dual_threshold_amount,
                        'amount' => $amount,
                        'type' => 'certificate',
                    ]
                );
            }
        }

        // Check over-budget threshold
        if ($policy->over_budget_threshold_percent !== null) {
            // For certificates, we check the impact on project budget
            $overBudgetPercent = $this->calculateProjectOverBudgetPercent($certificate->project_id, $certificate->tenant_id);
            if ($overBudgetPercent !== null && $overBudgetPercent > (float) $policy->over_budget_threshold_percent) {
                return CostApprovalDecision::denied(
                    'Payment certificate approval requires higher-level approval due to over-budget policy.',
                    'policy.over_budget',
                    [
                        'threshold_percent' => (float) $policy->over_budget_threshold_percent,
                        'over_budget_percent' => $overBudgetPercent,
                        'type' => 'certificate',
                    ]
                );
            }
        }

        return CostApprovalDecision::allowed();
    }

    /**
     * Evaluate Payment approval
     * 
     * @param ContractActualPayment $payment Payment to evaluate
     * @param User $user User attempting approval
     * @return CostApprovalDecision
     */
    public function evaluatePaymentApproval(ContractActualPayment $payment, User $user): CostApprovalDecision
    {
        // If user has unlimited approval permission, always allow
        if ($user->hasPermission('projects.cost.approve_unlimited')) {
            return CostApprovalDecision::allowed();
        }

        $policy = $this->getCurrentPolicyForTenant($payment->tenant_id);
        
        // If no policy exists, allow (backward compatible)
        if (!$policy) {
            return CostApprovalDecision::allowed();
        }

        // Check payment threshold
        if ($policy->payment_dual_threshold_amount !== null) {
            $amount = abs((float) ($payment->amount_paid ?? $payment->amount ?? 0));
            if ($amount >= (float) $policy->payment_dual_threshold_amount) {
                return CostApprovalDecision::denied(
                    'Payment approval requires higher-level approval due to threshold policy.',
                    'policy.threshold_exceeded',
                    [
                        'threshold' => (float) $policy->payment_dual_threshold_amount,
                        'amount' => $amount,
                        'type' => 'payment',
                    ]
                );
            }
        }

        // Check over-budget threshold
        if ($policy->over_budget_threshold_percent !== null) {
            $overBudgetPercent = $this->calculateProjectOverBudgetPercent($payment->project_id, $payment->tenant_id);
            if ($overBudgetPercent !== null && $overBudgetPercent > (float) $policy->over_budget_threshold_percent) {
                return CostApprovalDecision::denied(
                    'Payment approval requires higher-level approval due to over-budget policy.',
                    'policy.over_budget',
                    [
                        'threshold_percent' => (float) $policy->over_budget_threshold_percent,
                        'over_budget_percent' => $overBudgetPercent,
                        'type' => 'payment',
                    ]
                );
            }
        }

        return CostApprovalDecision::allowed();
    }

    /**
     * Calculate project over-budget percentage
     * 
     * @param string $projectId Project ID
     * @param string $tenantId Tenant ID
     * @param float|null $additionalAmount Additional amount to consider (e.g., pending CO)
     * @return float|null Over-budget percentage, or null if cannot calculate
     */
    private function calculateProjectOverBudgetPercent(string $projectId, string $tenantId, ?float $additionalAmount = null): ?float
    {
        // Get project budget total
        $budgetTotal = (float) ProjectBudgetLine::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->whereNull('deleted_at')
            ->sum('amount_budget') ?? 0.0;

        if ($budgetTotal <= 0) {
            // Cannot calculate if no budget
            return null;
        }

        // Get all contracts for this project
        $contracts = Contract::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->whereNull('deleted_at')
            ->get();

        // Calculate current contract total (base + approved COs)
        $contractCurrentTotal = 0.0;
        foreach ($contracts as $contract) {
            $contractCurrentTotal += $contract->current_amount ?? 0.0;
        }

        // Add additional amount if provided (e.g., pending CO being approved)
        if ($additionalAmount !== null) {
            $contractCurrentTotal += $additionalAmount;
        }

        // Calculate over-budget percentage
        if ($contractCurrentTotal <= $budgetTotal) {
            return 0.0;
        }

        $overBudgetAmount = $contractCurrentTotal - $budgetTotal;
        $overBudgetPercent = ($overBudgetAmount / $budgetTotal) * 100;

        return round($overBudgetPercent, 2);
    }

    /**
     * Check if user has high privilege (unlimited approval permission)
     * 
     * Round 241: Dual Approval Workflow
     * 
     * @param User $user User to check
     * @return bool
     */
    public function isHighPrivilege(User $user): bool
    {
        return $user->hasPermission('projects.cost.approve_unlimited');
    }

    /**
     * Check if Change Order requires dual approval
     * 
     * Round 241: Dual Approval Workflow
     * 
     * @param ChangeOrder $co Change Order to check
     * @return bool True if dual approval is required
     */
    public function requiresDualApprovalForChangeOrder(ChangeOrder $co): bool
    {
        $policy = $this->getCurrentPolicyForTenant($co->tenant_id);
        
        // If no policy exists, no dual approval required
        if (!$policy) {
            return false;
        }

        // Check CO threshold
        if ($policy->co_dual_threshold_amount !== null) {
            $amount = abs((float) $co->amount_delta);
            if ($amount >= (float) $policy->co_dual_threshold_amount) {
                return true;
            }
        }

        // Check over-budget threshold
        if ($policy->over_budget_threshold_percent !== null) {
            $overBudgetPercent = $this->calculateProjectOverBudgetPercent($co->project_id, $co->tenant_id, $co->amount_delta);
            if ($overBudgetPercent !== null && $overBudgetPercent > (float) $policy->over_budget_threshold_percent) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Payment Certificate requires dual approval
     * 
     * Round 241: Dual Approval Workflow
     * 
     * @param ContractPaymentCertificate $cert Certificate to check
     * @return bool True if dual approval is required
     */
    public function requiresDualApprovalForCertificate(ContractPaymentCertificate $cert): bool
    {
        $policy = $this->getCurrentPolicyForTenant($cert->tenant_id);
        
        // If no policy exists, no dual approval required
        if (!$policy) {
            return false;
        }

        // Check certificate threshold
        if ($policy->certificate_dual_threshold_amount !== null) {
            $amount = abs((float) $cert->amount_payable);
            if ($amount >= (float) $policy->certificate_dual_threshold_amount) {
                return true;
            }
        }

        // Check over-budget threshold
        if ($policy->over_budget_threshold_percent !== null) {
            $overBudgetPercent = $this->calculateProjectOverBudgetPercent($cert->project_id, $cert->tenant_id);
            if ($overBudgetPercent !== null && $overBudgetPercent > (float) $policy->over_budget_threshold_percent) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Payment requires dual approval
     * 
     * Round 241: Dual Approval Workflow
     * 
     * @param ContractActualPayment $payment Payment to check
     * @return bool True if dual approval is required
     */
    public function requiresDualApprovalForPayment(ContractActualPayment $payment): bool
    {
        $policy = $this->getCurrentPolicyForTenant($payment->tenant_id);
        
        // If no policy exists, no dual approval required
        if (!$policy) {
            return false;
        }

        // Check payment threshold
        if ($policy->payment_dual_threshold_amount !== null) {
            $amount = abs((float) ($payment->amount_paid ?? $payment->amount ?? 0));
            if ($amount >= (float) $policy->payment_dual_threshold_amount) {
                return true;
            }
        }

        // Check over-budget threshold
        if ($policy->over_budget_threshold_percent !== null) {
            $overBudgetPercent = $this->calculateProjectOverBudgetPercent($payment->project_id, $payment->tenant_id);
            if ($overBudgetPercent !== null && $overBudgetPercent > (float) $policy->over_budget_threshold_percent) {
                return true;
            }
        }

        return false;
    }
}
