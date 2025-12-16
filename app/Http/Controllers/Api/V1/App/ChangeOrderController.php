<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\ChangeOrderStoreRequest;
use App\Http\Requests\ChangeOrderUpdateRequest;
use App\Http\Resources\ChangeOrderResource;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Services\AuditLogService;
use App\Services\ChangeOrderService;
use App\Services\ContractManagementService;
use App\Services\CostApprovalPolicyService;
use App\Services\NotificationService;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ChangeOrderController
 * 
 * Round 220: Change Orders for Contracts
 * 
 * Handles change orders CRUD operations for contracts
 */
class ChangeOrderController extends BaseApiV1Controller
{
    public function __construct(
        private ChangeOrderService $changeOrderService,
        private ContractManagementService $contractService,
        private ProjectManagementService $projectService,
        private AuditLogService $auditLogService,
        private CostApprovalPolicyService $costApprovalPolicyService,
        private NotificationService $notificationService
    ) {}

    /**
     * List change orders for a contract
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/change-orders
     */
    public function index(Request $request, string $proj, string $contract): JsonResponse
    {
        try {
            $this->authorize('viewAny', ChangeOrder::class);
            
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Get contract to verify it belongs to project and tenant
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }

            $changeOrders = $this->changeOrderService->listChangeOrdersForContract($tenantId, $project, $contractModel);

            return $this->successResponse(
                ChangeOrderResource::collection($changeOrders),
                'Change orders retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project or contract not found', 404, null, 'NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to retrieve change orders', 500);
        }
    }

    /**
     * Get change order by ID
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/change-orders/{change_order}
     */
    public function show(Request $request, string $proj, string $contract, string $changeOrder): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Get contract to verify it belongs to project and tenant
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }

            $changeOrderModel = $this->changeOrderService->findChangeOrderForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $changeOrder
            );

            $this->authorize('view', $changeOrderModel);

            return $this->successResponse(
                new ChangeOrderResource($changeOrderModel),
                'Change order retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Change order not found', 404, null, 'CHANGE_ORDER_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $proj, 'contract_id' => $contract, 'change_order_id' => $changeOrder]);
            return $this->errorResponse('Failed to retrieve change order', 500);
        }
    }

    /**
     * Create change order for a contract
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/change-orders
     */
    public function store(ChangeOrderStoreRequest $request, string $proj, string $contract): JsonResponse
    {
        try {
            $this->authorize('create', ChangeOrder::class);
            
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Get contract to verify it belongs to project and tenant
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }

            $data = $request->validated();
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $changeOrder = $this->changeOrderService->createChangeOrderForContract(
                $tenantId,
                $project,
                $contractModel,
                $data,
                $lines
            );

            return $this->successResponse(
                new ChangeOrderResource($changeOrder),
                'Change order created successfully',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project or contract not found', 404, null, 'NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to create change order', 500);
        }
    }

    /**
     * Update change order for a contract
     * 
     * PATCH /api/v1/app/projects/{proj}/contracts/{contract}/change-orders/{change_order}
     */
    public function update(ChangeOrderUpdateRequest $request, string $proj, string $contract, string $changeOrder): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Get contract to verify it belongs to project and tenant
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }

            $data = $request->validated();
            $lines = isset($data['lines']) ? $data['lines'] : null;
            unset($data['lines']);

            // Get change order to authorize
            $changeOrderModel = $this->changeOrderService->findChangeOrderForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $changeOrder
            );
            
            $this->authorize('update', $changeOrderModel);

            $changeOrderModel = $this->changeOrderService->updateChangeOrderForContract(
                $tenantId,
                $project,
                $contractModel,
                $changeOrder,
                $data,
                $lines
            );

            return $this->successResponse(
                new ChangeOrderResource($changeOrderModel),
                'Change order updated successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Change order not found', 404, null, 'CHANGE_ORDER_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'project_id' => $proj, 'contract_id' => $contract, 'change_order_id' => $changeOrder]);
            return $this->errorResponse('Failed to update change order', 500);
        }
    }

    /**
     * Delete change order for a contract
     * 
     * DELETE /api/v1/app/projects/{proj}/contracts/{contract}/change-orders/{change_order}
     */
    public function destroy(Request $request, string $proj, string $contract, string $changeOrder): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Get contract to verify it belongs to project and tenant
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }

            // Get change order to authorize
            $changeOrderModel = $this->changeOrderService->findChangeOrderForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $changeOrder
            );
            
            $this->authorize('delete', $changeOrderModel);

            $this->changeOrderService->deleteChangeOrderForContract($tenantId, $project, $contractModel, $changeOrder);

            return $this->successResponse(null, 'Change order deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Change order not found', 404, null, 'CHANGE_ORDER_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'project_id' => $proj, 'contract_id' => $contract, 'change_order_id' => $changeOrder]);
            return $this->errorResponse('Failed to delete change order', 500);
        }
    }

    /**
     * Propose change order (draft → proposed)
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/change-orders/{co}/propose
     * 
     * Round 230: Workflow/Approval for Change Orders
     */
    public function propose(Request $request, string $proj, string $contract, string $co): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }

            $changeOrder = $this->changeOrderService->findChangeOrderForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $co
            );

            try {
                $this->authorize('approve', $changeOrder);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return $this->errorResponse('You do not have permission to perform this action', 403, null, 'FORBIDDEN');
            }

            // Validate transition: only from draft → proposed
            if ($changeOrder->status !== 'draft') {
                return $this->errorResponse(
                    'Change order can only be proposed from draft status',
                    422,
                    null,
                    'INVALID_STATUS_TRANSITION'
                );
            }

            $oldStatus = $changeOrder->status;
            
            $changeOrder = DB::transaction(function () use ($changeOrder, $project, $oldStatus, $tenantId, $request) {
                $changeOrder->status = 'proposed';
                $changeOrder->updated_by = auth()->id();
                $changeOrder->save();

                // Log activity
                ProjectActivity::create([
                    'project_id' => $project->id,
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'action' => 'change_order_proposed',
                    'entity_type' => 'ChangeOrder',
                    'entity_id' => $changeOrder->id,
                    'description' => "Change order '{$changeOrder->code}' was proposed",
                    'metadata' => [
                        'change_order_id' => $changeOrder->id,
                        'change_order_code' => $changeOrder->code,
                        'old_status' => $oldStatus,
                        'new_status' => 'proposed',
                        'user_id' => auth()->id(),
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Round 235: Audit log
                $this->auditLogService->record(
                    tenantId: $tenantId,
                    userId: auth()->id(),
                    action: 'co.submitted',
                    entityType: 'ChangeOrder',
                    entityId: $changeOrder->id,
                    projectId: $project->id,
                    before: ['status' => $oldStatus],
                    after: ['status' => 'proposed'],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $changeOrder->fresh();
            });

            return $this->successResponse(
                new ChangeOrderResource($changeOrder),
                'Change order proposed successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Change order not found', 404, null, 'CHANGE_ORDER_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'propose', 'project_id' => $proj, 'contract_id' => $contract, 'change_order_id' => $co]);
            return $this->errorResponse('Failed to propose change order', 500);
        }
    }

    /**
     * Approve change order (proposed → approved)
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/change-orders/{co}/approve
     * 
     * Round 230: Workflow/Approval for Change Orders
     */
    public function approve(Request $request, string $proj, string $contract, string $co): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }

            $changeOrder = $this->changeOrderService->findChangeOrderForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $co
            );

            try {
                $this->authorize('approve', $changeOrder);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return $this->errorResponse('You do not have permission to perform this action', 403, null, 'FORBIDDEN');
            }

            // Validate transition: only from proposed → approved
            if ($changeOrder->status !== 'proposed') {
                return $this->errorResponse(
                    'Change order can only be approved from proposed status',
                    422,
                    null,
                    'INVALID_STATUS_TRANSITION'
                );
            }

            // Round 241: Dual Approval Workflow
            $user = auth()->user();
            $requiresDualApproval = $this->costApprovalPolicyService->requiresDualApprovalForChangeOrder($changeOrder);
            $isHighPrivilege = $this->costApprovalPolicyService->isHighPrivilege($user);

            // If dual approval is required and user is not high privilege, implement dual approval flow
            if ($requiresDualApproval && !$isHighPrivilege) {
                // Check if this is first approval
                if ($changeOrder->first_approved_by === null) {
                    // Record first approval
                    $changeOrder = DB::transaction(function () use ($changeOrder, $project, $tenantId, $request) {
                        $changeOrder->first_approved_by = auth()->id();
                        $changeOrder->first_approved_at = now();
                        $changeOrder->requires_dual_approval = true;
                        $changeOrder->status = 'approved'; // Status still changes to approved
                        $changeOrder->updated_by = auth()->id();
                        $changeOrder->save();

                        // Round 241: Audit log for first approval
                        $this->auditLogService->record(
                            tenantId: $tenantId,
                            userId: auth()->id(),
                            action: 'co.first_approved',
                            entityType: 'ChangeOrder',
                            entityId: $changeOrder->id,
                            projectId: $project->id,
                            before: ['first_approved_by' => null],
                            after: [
                                'first_approved_by' => auth()->id(),
                                'first_approved_at' => now()->toISOString(),
                                'requires_dual_approval' => true,
                            ],
                            ipAddress: $request->ip(),
                            userAgent: $request->userAgent()
                        );

                        return $changeOrder->fresh();
                    });

                    return $this->successResponse(
                        new ChangeOrderResource($changeOrder),
                        'First-level approval recorded. Waiting for second approver.',
                        [
                            'dual_approval_stage' => 'first',
                        ]
                    );
                }

                // Check if this is second approval
                if ($changeOrder->second_approved_by === null) {
                    // Must be a different user than first approver
                    if ($changeOrder->first_approved_by === auth()->id()) {
                        return $this->errorResponse(
                            'Second approval must be performed by a different approver',
                            403,
                            null,
                            'DUAL_APPROVAL_SAME_USER'
                        );
                    }

                    // Record second approval and finalize
                    $oldStatus = $changeOrder->status;
                    $changeOrder = DB::transaction(function () use ($changeOrder, $project, $contractModel, $tenantId, $oldStatus, $request) {
                        $changeOrder->second_approved_by = auth()->id();
                        $changeOrder->second_approved_at = now();
                        $changeOrder->status = 'approved';
                        $changeOrder->updated_by = auth()->id();
                        $changeOrder->save();

                        // Round 241: Audit log for second approval
                        $this->auditLogService->record(
                            tenantId: $tenantId,
                            userId: auth()->id(),
                            action: 'co.second_approved',
                            entityType: 'ChangeOrder',
                            entityId: $changeOrder->id,
                            projectId: $project->id,
                            before: [
                                'second_approved_by' => null,
                                'status' => $oldStatus,
                            ],
                            after: [
                                'second_approved_by' => auth()->id(),
                                'second_approved_at' => now()->toISOString(),
                                'status' => 'approved',
                            ],
                            ipAddress: $request->ip(),
                            userAgent: $request->userAgent()
                        );

                        // Also log the final approval activity
                        ProjectActivity::create([
                            'project_id' => $project->id,
                            'tenant_id' => $tenantId,
                            'user_id' => auth()->id(),
                            'action' => 'change_order_approved',
                            'entity_type' => 'ChangeOrder',
                            'entity_id' => $changeOrder->id,
                            'description' => "Change order '{$changeOrder->code}' was approved (dual approval completed)",
                            'metadata' => [
                                'change_order_id' => $changeOrder->id,
                                'change_order_code' => $changeOrder->code,
                                'old_status' => $oldStatus,
                                'new_status' => 'approved',
                                'first_approved_by' => $changeOrder->first_approved_by,
                                'second_approved_by' => auth()->id(),
                                'approved_at' => now()->toISOString(),
                            ],
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                        ]);

                        // Round 252: Notification for CO final approval (notify creator)
                        if ($changeOrder->created_by && $changeOrder->created_by !== auth()->id()) {
                            try {
                                $this->notificationService->notifyUser(
                                    userId: (string) $changeOrder->created_by,
                                    module: 'cost',
                                    type: 'co.approved',
                                    title: 'Change order đã được phê duyệt',
                                    message: sprintf("Change order \"%s\" trong dự án \"%s\" đã được phê duyệt.", $changeOrder->code ?? $changeOrder->id, $project->name),
                                    entityType: 'change_order',
                                    entityId: $changeOrder->id,
                                    metadata: [
                                        'project_id' => $project->id,
                                        'project_name' => $project->name,
                                        'status' => $changeOrder->status,
                                    ],
                                    tenantId: (string) $tenantId
                                );
                            } catch (\Exception $e) {
                                \Log::warning('Failed to create notification for CO approval', [
                                    'error' => $e->getMessage(),
                                    'change_order_id' => $changeOrder->id,
                                    'created_by' => $changeOrder->created_by,
                                ]);
                            }
                        }

                        return $changeOrder->fresh();
                    });

                    return $this->successResponse(
                        new ChangeOrderResource($changeOrder),
                        'Dual approval completed.',
                        [
                            'dual_approval_stage' => 'second',
                        ]
                    );
                }

                // Already dual-approved, proceed with normal approval
            }

            // Normal approval flow (no dual approval required OR high privilege user OR already dual-approved)
            $oldStatus = $changeOrder->status;
            
            $changeOrder = DB::transaction(function () use ($changeOrder, $project, $contractModel, $tenantId, $oldStatus, $request) {
                $changeOrder->status = 'approved';
                $changeOrder->updated_by = auth()->id();
                $changeOrder->save();

                // Note: Contract current amount update is handled by existing ChangeOrderService logic
                // when CO is approved, it should already be accounted for in contract calculations

                // Log activity
                ProjectActivity::create([
                    'project_id' => $project->id,
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'action' => 'change_order_approved',
                    'entity_type' => 'ChangeOrder',
                    'entity_id' => $changeOrder->id,
                    'description' => "Change order '{$changeOrder->code}' was approved",
                    'metadata' => [
                        'change_order_id' => $changeOrder->id,
                        'change_order_code' => $changeOrder->code,
                        'old_status' => $oldStatus,
                        'new_status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now()->toISOString(),
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Round 235: Audit log
                $this->auditLogService->record(
                    tenantId: $tenantId,
                    userId: auth()->id(),
                    action: 'co.approved',
                    entityType: 'ChangeOrder',
                    entityId: $changeOrder->id,
                    projectId: $project->id,
                    before: ['status' => $oldStatus],
                    after: ['status' => 'approved'],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                // Round 252: Notification for CO final approval (notify creator)
                if ($changeOrder->created_by && $changeOrder->created_by !== auth()->id()) {
                    try {
                        $this->notificationService->notifyUser(
                            userId: (string) $changeOrder->created_by,
                            module: 'cost',
                            type: 'co.approved',
                            title: 'Change order đã được phê duyệt',
                            message: sprintf("Change order \"%s\" trong dự án \"%s\" đã được phê duyệt.", $changeOrder->code ?? $changeOrder->id, $project->name),
                            entityType: 'change_order',
                            entityId: $changeOrder->id,
                            metadata: [
                                'project_id' => $project->id,
                                'project_name' => $project->name,
                                'status' => $changeOrder->status,
                            ],
                            tenantId: (string) $tenantId
                        );
                    } catch (\Exception $e) {
                        \Log::warning('Failed to create notification for CO approval', [
                            'error' => $e->getMessage(),
                            'change_order_id' => $changeOrder->id,
                            'created_by' => $changeOrder->created_by,
                        ]);
                    }
                }

                return $changeOrder->fresh();
            });

            return $this->successResponse(
                new ChangeOrderResource($changeOrder),
                'Change order approved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Change order not found', 404, null, 'CHANGE_ORDER_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'approve', 'project_id' => $proj, 'contract_id' => $contract, 'change_order_id' => $co]);
            return $this->errorResponse('Failed to approve change order', 500);
        }
    }

    /**
     * Reject change order (proposed → rejected)
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/change-orders/{co}/reject
     * 
     * Round 230: Workflow/Approval for Change Orders
     */
    public function reject(Request $request, string $proj, string $contract, string $co): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);

            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }

            $changeOrder = $this->changeOrderService->findChangeOrderForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $co
            );

            try {
                $this->authorize('approve', $changeOrder);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return $this->errorResponse('You do not have permission to perform this action', 403, null, 'FORBIDDEN');
            }

            // Validate transition: only from proposed → rejected
            if ($changeOrder->status !== 'proposed') {
                return $this->errorResponse(
                    'Change order can only be rejected from proposed status',
                    422,
                    null,
                    'INVALID_STATUS_TRANSITION'
                );
            }

            $oldStatus = $changeOrder->status;
            
            $changeOrder = DB::transaction(function () use ($changeOrder, $project, $tenantId, $oldStatus, $request) {
                $changeOrder->status = 'rejected';
                $changeOrder->updated_by = auth()->id();
                $changeOrder->save();

                // Log activity
                ProjectActivity::create([
                    'project_id' => $project->id,
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'action' => 'change_order_rejected',
                    'entity_type' => 'ChangeOrder',
                    'entity_id' => $changeOrder->id,
                    'description' => "Change order '{$changeOrder->code}' was rejected",
                    'metadata' => [
                        'change_order_id' => $changeOrder->id,
                        'change_order_code' => $changeOrder->code,
                        'old_status' => $oldStatus,
                        'new_status' => 'rejected',
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now()->toISOString(),
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Round 235: Audit log
                $this->auditLogService->record(
                    tenantId: $tenantId,
                    userId: auth()->id(),
                    action: 'co.rejected',
                    entityType: 'ChangeOrder',
                    entityId: $changeOrder->id,
                    projectId: $project->id,
                    before: ['status' => $oldStatus],
                    after: ['status' => 'rejected'],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $changeOrder->fresh();
            });

            return $this->successResponse(
                new ChangeOrderResource($changeOrder),
                'Change order rejected successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Change order not found', 404, null, 'CHANGE_ORDER_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'reject', 'project_id' => $proj, 'contract_id' => $contract, 'change_order_id' => $co]);
            return $this->errorResponse('Failed to reject change order', 500);
        }
    }
}
