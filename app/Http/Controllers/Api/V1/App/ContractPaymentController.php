<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\ContractPaymentStoreRequest;
use App\Http\Requests\ContractPaymentUpdateRequest;
use App\Http\Resources\ContractPaymentResource;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Services\AuditLogService;
use App\Services\ContractManagementService;
use App\Services\ContractPaymentService;
use App\Services\CostApprovalPolicyService;
use App\Services\NotificationService;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ContractPaymentController
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * Handles actual payments CRUD operations for contracts
 */
class ContractPaymentController extends BaseApiV1Controller
{
    public function __construct(
        private ContractPaymentService $paymentService,
        private ContractManagementService $contractService,
        private ProjectManagementService $projectService,
        private AuditLogService $auditLogService,
        private CostApprovalPolicyService $costApprovalPolicyService,
        private NotificationService $notificationService
    ) {}

    /**
     * List payments for a contract
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/payments
     */
    public function index(Request $request, string $proj, string $contract): JsonResponse
    {
        try {
            $this->authorize('viewAny', ContractActualPayment::class);
            
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

            $payments = $this->paymentService->listPaymentsForContract($tenantId, $project, $contractModel);

            return $this->successResponse(
                ContractPaymentResource::collection($payments),
                'Payments retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project or contract not found', 404, null, 'NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to retrieve payments', 500);
        }
    }

    /**
     * Get payment by ID
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/payments/{payment}
     */
    public function show(Request $request, string $proj, string $contract, string $payment): JsonResponse
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

            $paymentModel = $this->paymentService->findPaymentForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $payment
            );

            $this->authorize('view', $paymentModel);

            return $this->successResponse(
                new ContractPaymentResource($paymentModel),
                'Payment retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment not found', 404, null, 'PAYMENT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $proj, 'contract_id' => $contract, 'payment_id' => $payment]);
            return $this->errorResponse('Failed to retrieve payment', 500);
        }
    }

    /**
     * Create payment for a contract
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/payments
     */
    public function store(ContractPaymentStoreRequest $request, string $proj, string $contract): JsonResponse
    {
        try {
            $this->authorize('create', ContractActualPayment::class);
            
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

            $payment = $this->paymentService->createPaymentForContract(
                $tenantId,
                $project,
                $contractModel,
                $data
            );

            return $this->successResponse(
                new ContractPaymentResource($payment),
                'Payment created successfully',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project, contract, or certificate not found', 404, null, 'NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to create payment', 500);
        }
    }

    /**
     * Update payment for a contract
     * 
     * PATCH /api/v1/app/projects/{proj}/contracts/{contract}/payments/{payment}
     */
    public function update(ContractPaymentUpdateRequest $request, string $proj, string $contract, string $payment): JsonResponse
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

            // Get payment to authorize
            $paymentModel = $this->paymentService->findPaymentForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $payment
            );
            
            $this->authorize('update', $paymentModel);

            $paymentModel = $this->paymentService->updatePaymentForContract(
                $tenantId,
                $project,
                $contractModel,
                $payment,
                $data
            );

            return $this->successResponse(
                new ContractPaymentResource($paymentModel),
                'Payment updated successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment or certificate not found', 404, null, 'NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'project_id' => $proj, 'contract_id' => $contract, 'payment_id' => $payment]);
            return $this->errorResponse('Failed to update payment', 500);
        }
    }

    /**
     * Delete payment for a contract
     * 
     * DELETE /api/v1/app/projects/{proj}/contracts/{contract}/payments/{payment}
     */
    public function destroy(Request $request, string $proj, string $contract, string $payment): JsonResponse
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

            // Get payment to authorize
            $paymentModel = $this->paymentService->findPaymentForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $payment
            );
            
            $this->authorize('delete', $paymentModel);

            $this->paymentService->deletePaymentForContract($tenantId, $project, $contractModel, $payment);

            return $this->successResponse(null, 'Payment deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment not found', 404, null, 'PAYMENT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'project_id' => $proj, 'contract_id' => $contract, 'payment_id' => $payment]);
            return $this->errorResponse('Failed to delete payment', 500);
        }
    }

    /**
     * Mark payment as paid (planned → paid)
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/payments/{payment}/mark-paid
     * 
     * Round 230: Workflow/Approval for Payments
     */
    public function markPaid(Request $request, string $proj, string $contract, string $payment): JsonResponse
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

            $paymentModel = $this->paymentService->findPaymentForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $payment
            );

            try {
                $this->authorize('approve', $paymentModel);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return $this->errorResponse('You do not have permission to perform this action', 403, null, 'FORBIDDEN');
            }

            // Check if payment already has status field, otherwise use paid_date as indicator
            // If status exists and is 'paid', reject
            $currentStatus = $paymentModel->getAttribute('status');
            if ($currentStatus === 'paid') {
                return $this->errorResponse(
                    'Payment is already marked as paid',
                    422,
                    null,
                    'PAYMENT_ALREADY_PAID'
                );
            }

            // If status exists, validate transition: only from planned → paid
            // If status doesn't exist, check paid_date instead
            if ($currentStatus !== null && $currentStatus !== 'planned') {
                return $this->errorResponse(
                    'Payment can only be marked as paid from planned status',
                    422,
                    null,
                    'INVALID_STATUS_TRANSITION'
                );
            }

            // If no status field but paid_date exists, reject
            if ($currentStatus === null && $paymentModel->paid_date !== null) {
                return $this->errorResponse(
                    'Payment already has a paid date',
                    422,
                    null,
                    'PAYMENT_ALREADY_PAID'
                );
            }

            // Round 241: Dual Approval Workflow
            $user = auth()->user();
            $requiresDualApproval = $this->costApprovalPolicyService->requiresDualApprovalForPayment($paymentModel);
            $isHighPrivilege = $this->costApprovalPolicyService->isHighPrivilege($user);

            // If dual approval is required and user is not high privilege, implement dual approval flow
            if ($requiresDualApproval && !$isHighPrivilege) {
                // Check if this is first approval
                if ($paymentModel->first_approved_by === null) {
                    // Record first approval
                    $paymentModel = DB::transaction(function () use ($paymentModel, $project, $tenantId, $request) {
                        $paymentModel->first_approved_by = auth()->id();
                        $paymentModel->first_approved_at = now();
                        $paymentModel->requires_dual_approval = true;
                        // Set status to paid if status field exists
                        if ($paymentModel->getAttribute('status') !== null) {
                            $paymentModel->status = 'paid';
                        }
                        // Set paid_date if not already set
                        if ($paymentModel->paid_date === null) {
                            $paymentModel->paid_date = now()->toDateString();
                        }
                        $paymentModel->updated_by = auth()->id();
                        $paymentModel->save();

                        // Round 241: Audit log for first approval
                        $this->auditLogService->record(
                            tenantId: $tenantId,
                            userId: auth()->id(),
                            action: 'payment.first_approved',
                            entityType: 'ContractActualPayment',
                            entityId: $paymentModel->id,
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

                        return $paymentModel->fresh();
                    });

                    return $this->successResponse(
                        new ContractPaymentResource($paymentModel),
                        'First-level approval recorded. Waiting for second approver.',
                        [
                            'dual_approval_stage' => 'first',
                        ]
                    );
                }

                // Check if this is second approval
                if ($paymentModel->second_approved_by === null) {
                    // Must be a different user than first approver
                    if ($paymentModel->first_approved_by === auth()->id()) {
                        return $this->errorResponse(
                            'Second approval must be performed by a different approver',
                            403,
                            null,
                            'DUAL_APPROVAL_SAME_USER'
                        );
                    }

                    // Record second approval and finalize
                    $oldStatus = $paymentModel->getAttribute('status') ?? 'planned';
                    $paymentModel = DB::transaction(function () use ($paymentModel, $project, $tenantId, $oldStatus, $request) {
                        $paymentModel->second_approved_by = auth()->id();
                        $paymentModel->second_approved_at = now();
                        // Set status to paid if status field exists
                        if ($paymentModel->getAttribute('status') !== null) {
                            $paymentModel->status = 'paid';
                        }
                        // Set paid_date if not already set
                        if ($paymentModel->paid_date === null) {
                            $paymentModel->paid_date = now()->toDateString();
                        }
                        $paymentModel->updated_by = auth()->id();
                        $paymentModel->save();

                        // Round 241: Audit log for second approval
                        $this->auditLogService->record(
                            tenantId: $tenantId,
                            userId: auth()->id(),
                            action: 'payment.second_approved',
                            entityType: 'ContractActualPayment',
                            entityId: $paymentModel->id,
                            projectId: $project->id,
                            before: [
                                'second_approved_by' => null,
                                'status' => $oldStatus,
                            ],
                            after: [
                                'second_approved_by' => auth()->id(),
                                'second_approved_at' => now()->toISOString(),
                                'status' => 'paid',
                                'paid_date' => $paymentModel->paid_date?->toISOString(),
                            ],
                            ipAddress: $request->ip(),
                            userAgent: $request->userAgent()
                        );

                        // Also log the final approval activity
                        ProjectActivity::create([
                            'project_id' => $project->id,
                            'tenant_id' => $tenantId,
                            'user_id' => auth()->id(),
                            'action' => 'payment_marked_paid',
                            'entity_type' => 'ContractActualPayment',
                            'entity_id' => $paymentModel->id,
                            'description' => "Payment was marked as paid (dual approval completed)",
                            'metadata' => [
                                'payment_id' => $paymentModel->id,
                                'amount' => $paymentModel->amount_paid ?? $paymentModel->amount ?? 0,
                                'old_status' => $oldStatus,
                                'new_status' => 'paid',
                                'paid_date' => $paymentModel->paid_date?->toISOString(),
                                'first_approved_by' => $paymentModel->first_approved_by,
                                'second_approved_by' => auth()->id(),
                            ],
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                        ]);

                        // Round 252: Notification for payment marked paid (notify creator)
                        if ($paymentModel->created_by && $paymentModel->created_by !== auth()->id()) {
                            try {
                                $this->notificationService->notifyUser(
                                    userId: (string) $paymentModel->created_by,
                                    module: 'cost',
                                    type: 'payment.marked_paid',
                                    title: 'Thanh toán đã được ghi nhận',
                                    message: sprintf("Thanh toán trong dự án \"%s\" đã được ghi nhận là đã thanh toán.", $project->name),
                                    entityType: 'payment',
                                    entityId: $paymentModel->id,
                                    metadata: [
                                        'project_id' => $project->id,
                                        'project_name' => $project->name,
                                        'amount' => $paymentModel->amount_paid ?? $paymentModel->amount ?? 0,
                                        'paid_date' => $paymentModel->paid_date?->toISOString(),
                                    ],
                                    tenantId: (string) $tenantId
                                );
                            } catch (\Exception $e) {
                                \Log::warning('Failed to create notification for payment marked paid', [
                                    'error' => $e->getMessage(),
                                    'payment_id' => $paymentModel->id,
                                    'created_by' => $paymentModel->created_by,
                                ]);
                            }
                        }

                        return $paymentModel->fresh();
                    });

                    return $this->successResponse(
                        new ContractPaymentResource($paymentModel),
                        'Dual approval completed.',
                        [
                            'dual_approval_stage' => 'second',
                        ]
                    );
                }

                // Already dual-approved, proceed with normal approval
            }

            // Normal approval flow (no dual approval required OR high privilege user OR already dual-approved)
            $oldStatus = $paymentModel->getAttribute('status') ?? 'planned';
            
            $paymentModel = DB::transaction(function () use ($paymentModel, $project, $tenantId, $oldStatus, $request) {
                // Set status to paid if status field exists
                if ($paymentModel->getAttribute('status') !== null) {
                    $paymentModel->status = 'paid';
                }
                
                // Set paid_date if not already set
                if ($paymentModel->paid_date === null) {
                    $paymentModel->paid_date = now()->toDateString();
                }
                
                $paymentModel->updated_by = auth()->id();
                $paymentModel->save();

                // Log activity
                ProjectActivity::create([
                    'project_id' => $project->id,
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'action' => 'payment_marked_paid',
                    'entity_type' => 'ContractActualPayment',
                    'entity_id' => $paymentModel->id,
                    'description' => "Payment was marked as paid",
                    'metadata' => [
                        'payment_id' => $paymentModel->id,
                        'amount' => $paymentModel->amount_paid ?? $paymentModel->amount ?? 0,
                        'old_status' => $oldStatus,
                        'new_status' => 'paid',
                        'paid_date' => $paymentModel->paid_date?->toISOString(),
                        'user_id' => auth()->id(),
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Round 235: Audit log
                $this->auditLogService->record(
                    tenantId: $tenantId,
                    userId: auth()->id(),
                    action: 'payment.marked_paid',
                    entityType: 'ContractActualPayment',
                    entityId: $paymentModel->id,
                    projectId: $project->id,
                    before: ['status' => $oldStatus],
                    after: ['status' => 'paid', 'paid_date' => $paymentModel->paid_date?->toISOString()],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                // Round 252: Notification for payment marked paid (notify creator)
                if ($paymentModel->created_by && $paymentModel->created_by !== auth()->id()) {
                    try {
                        $this->notificationService->notifyUser(
                            userId: (string) $paymentModel->created_by,
                            module: 'cost',
                            type: 'payment.marked_paid',
                            title: 'Thanh toán đã được ghi nhận',
                            message: sprintf("Thanh toán trong dự án \"%s\" đã được ghi nhận là đã thanh toán.", $project->name),
                            entityType: 'payment',
                            entityId: $paymentModel->id,
                            metadata: [
                                'project_id' => $project->id,
                                'project_name' => $project->name,
                                'amount' => $paymentModel->amount_paid ?? $paymentModel->amount ?? 0,
                                'paid_date' => $paymentModel->paid_date?->toISOString(),
                            ],
                            tenantId: (string) $tenantId
                        );
                    } catch (\Exception $e) {
                        \Log::warning('Failed to create notification for payment marked paid', [
                            'error' => $e->getMessage(),
                            'payment_id' => $paymentModel->id,
                            'created_by' => $paymentModel->created_by,
                        ]);
                    }
                }

                return $paymentModel->fresh();
            });

            return $this->successResponse(
                new ContractPaymentResource($paymentModel),
                'Payment marked as paid successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment not found', 404, null, 'PAYMENT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'markPaid', 'project_id' => $proj, 'contract_id' => $contract, 'payment_id' => $payment]);
            return $this->errorResponse('Failed to mark payment as paid', 500);
        }
    }
}
