<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\AuditLogService;
use App\Services\CostApprovalPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Cost Approval Policy Controller
 * 
 * Round 239: Cost Approval Policies (Phase 1 - Thresholds & Blocking)
 * 
 * Admin API for managing cost approval policies per tenant.
 */
class CostApprovalPolicyController extends BaseApiV1Controller
{
    private AuditLogService $auditLogService;
    private CostApprovalPolicyService $costApprovalPolicyService;

    /**
     * Constructor - Check permission
     */
    public function __construct(
        AuditLogService $auditLogService,
        CostApprovalPolicyService $costApprovalPolicyService
    ) {
        $this->auditLogService = $auditLogService;
        $this->costApprovalPolicyService = $costApprovalPolicyService;
        
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401, null, 'UNAUTHENTICATED');
            }
            
            // Check if user has system.cost_policies.manage permission
            if (!$this->hasPermission($user, 'system.cost_policies.manage')) {
                Log::warning('User attempted to access cost approval policy management without permission', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'url' => $request->url(),
                ]);
                
                return $this->errorResponse(
                    'Insufficient permissions',
                    403,
                    ['details' => 'system.cost_policies.manage permission required'],
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
     * Get current cost approval policy for tenant
     * 
     * GET /api/v1/admin/cost-approval-policy
     */
    public function index(): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $policy = $this->costApprovalPolicyService->getCurrentPolicyForTenant($tenantId);
            
            // Return default values if no policy exists
            $data = [
                'tenant_id' => $tenantId,
                'co_dual_threshold_amount' => $policy?->co_dual_threshold_amount,
                'certificate_dual_threshold_amount' => $policy?->certificate_dual_threshold_amount,
                'payment_dual_threshold_amount' => $policy?->payment_dual_threshold_amount,
                'over_budget_threshold_percent' => $policy?->over_budget_threshold_percent,
            ];

            return $this->successResponse($data, 'Cost approval policy retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'index']);
            return $this->errorResponse('Failed to retrieve cost approval policy', 500, null, 'POLICY_FETCH_ERROR');
        }
    }

    /**
     * Create or update cost approval policy for tenant
     * 
     * PUT /api/v1/admin/cost-approval-policy
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'co_dual_threshold_amount' => 'nullable|numeric|min:0',
                'certificate_dual_threshold_amount' => 'nullable|numeric|min:0',
                'payment_dual_threshold_amount' => 'nullable|numeric|min:0',
                'over_budget_threshold_percent' => 'nullable|numeric|min:0|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()->toArray(),
                    'VALIDATION_FAILED'
                );
            }

            $tenantId = $this->getTenantId();
            
            // Get existing policy for audit log
            $existingPolicy = $this->costApprovalPolicyService->getCurrentPolicyForTenant($tenantId);
            $before = $existingPolicy ? [
                'co_dual_threshold_amount' => $existingPolicy->co_dual_threshold_amount,
                'certificate_dual_threshold_amount' => $existingPolicy->certificate_dual_threshold_amount,
                'payment_dual_threshold_amount' => $existingPolicy->payment_dual_threshold_amount,
                'over_budget_threshold_percent' => $existingPolicy->over_budget_threshold_percent,
            ] : null;

            $policy = $this->costApprovalPolicyService->upsertPolicyForTenant($request->only([
                'co_dual_threshold_amount',
                'certificate_dual_threshold_amount',
                'payment_dual_threshold_amount',
                'over_budget_threshold_percent',
            ]), $tenantId);

            // Round 235: Audit log
            $this->auditLogService->record(
                tenantId: $tenantId,
                userId: Auth::id(),
                action: 'cost_policy.updated',
                entityType: 'CostApprovalPolicy',
                entityId: (string) $policy->id,
                before: $before,
                after: [
                    'co_dual_threshold_amount' => $policy->co_dual_threshold_amount,
                    'certificate_dual_threshold_amount' => $policy->certificate_dual_threshold_amount,
                    'payment_dual_threshold_amount' => $policy->payment_dual_threshold_amount,
                    'over_budget_threshold_percent' => $policy->over_budget_threshold_percent,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $data = [
                'tenant_id' => $policy->tenant_id,
                'co_dual_threshold_amount' => $policy->co_dual_threshold_amount,
                'certificate_dual_threshold_amount' => $policy->certificate_dual_threshold_amount,
                'payment_dual_threshold_amount' => $policy->payment_dual_threshold_amount,
                'over_budget_threshold_percent' => $policy->over_budget_threshold_percent,
            ];

            return $this->successResponse($data, 'Cost approval policy updated successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'method' => 'update',
                'request_id' => $request->header('X-Request-Id'),
            ]);
            return $this->errorResponse('Failed to update cost approval policy', 500, null, 'POLICY_UPDATE_ERROR');
        }
    }
}
