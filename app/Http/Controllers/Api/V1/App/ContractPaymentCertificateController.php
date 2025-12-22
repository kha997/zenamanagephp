<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\ContractPaymentCertificateStoreRequest;
use App\Http\Requests\ContractPaymentCertificateUpdateRequest;
use App\Http\Resources\ContractPaymentCertificateResource;
use App\Models\Contract;
use App\Models\ContractPaymentCertificate;
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
 * ContractPaymentCertificateController
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * 
 * Handles payment certificates CRUD operations for contracts
 */
class ContractPaymentCertificateController extends BaseApiV1Controller
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
     * List payment certificates for a contract
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/payment-certificates
     */
    public function index(Request $request, string $proj, string $contract): JsonResponse
    {
        try {
            $this->authorize('viewAny', ContractPaymentCertificate::class);
            
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

            $certificates = $this->paymentService->listPaymentCertificatesForContract($tenantId, $project, $contractModel);

            return $this->successResponse(
                ContractPaymentCertificateResource::collection($certificates),
                'Payment certificates retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project or contract not found', 404, null, 'NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to retrieve payment certificates', 500);
        }
    }

    /**
     * Get payment certificate by ID
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}/payment-certificates/{certificate}
     */
    public function show(Request $request, string $proj, string $contract, string $certificate): JsonResponse
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

            $certificateModel = $this->paymentService->findPaymentCertificateForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $certificate
            );

            $this->authorize('view', $certificateModel);

            return $this->successResponse(
                new ContractPaymentCertificateResource($certificateModel),
                'Payment certificate retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment certificate not found', 404, null, 'CERTIFICATE_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $proj, 'contract_id' => $contract, 'certificate_id' => $certificate]);
            return $this->errorResponse('Failed to retrieve payment certificate', 500);
        }
    }

    /**
     * Create payment certificate for a contract
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/payment-certificates
     */
    public function store(ContractPaymentCertificateStoreRequest $request, string $proj, string $contract): JsonResponse
    {
        try {
            $this->authorize('create', ContractPaymentCertificate::class);
            
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

            $certificate = $this->paymentService->createPaymentCertificateForContract(
                $tenantId,
                $project,
                $contractModel,
                $data
            );

            // Round 235: Audit log
            $this->auditLogService->record(
                tenantId: $tenantId,
                userId: auth()->id(),
                action: 'certificate.created',
                entityType: 'ContractPaymentCertificate',
                entityId: $certificate->id,
                projectId: $project->id,
                before: null,
                after: [
                    'code' => $certificate->code,
                    'status' => $certificate->status,
                    'amount' => $certificate->amount ?? null,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->successResponse(
                new ContractPaymentCertificateResource($certificate),
                'Payment certificate created successfully',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project or contract not found', 404, null, 'NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to create payment certificate', 500);
        }
    }

    /**
     * Update payment certificate for a contract
     * 
     * PATCH /api/v1/app/projects/{proj}/contracts/{contract}/payment-certificates/{certificate}
     */
    public function update(ContractPaymentCertificateUpdateRequest $request, string $proj, string $contract, string $certificate): JsonResponse
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

            // Get certificate to authorize
            $certificateModel = $this->paymentService->findPaymentCertificateForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $certificate
            );
            
            $this->authorize('update', $certificateModel);

            $certificateModel = $this->paymentService->updatePaymentCertificateForContract(
                $tenantId,
                $project,
                $contractModel,
                $certificate,
                $data
            );

            return $this->successResponse(
                new ContractPaymentCertificateResource($certificateModel),
                'Payment certificate updated successfully'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('You do not have permission to perform this action', 403, null, 'FORBIDDEN');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment certificate not found', 404, null, 'CERTIFICATE_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'project_id' => $proj, 'contract_id' => $contract, 'certificate_id' => $certificate]);
            return $this->errorResponse('Failed to update payment certificate', 500);
        }
    }

    /**
     * Delete payment certificate for a contract
     * 
     * DELETE /api/v1/app/projects/{proj}/contracts/{contract}/payment-certificates/{certificate}
     */
    public function destroy(Request $request, string $proj, string $contract, string $certificate): JsonResponse
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

            // Get certificate to authorize
            $certificateModel = $this->paymentService->findPaymentCertificateForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $certificate
            );
            
            $this->authorize('delete', $certificateModel);

            $this->paymentService->deletePaymentCertificateForContract($tenantId, $project, $contractModel, $certificate);

            return $this->successResponse(null, 'Payment certificate deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment certificate not found', 404, null, 'CERTIFICATE_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'project_id' => $proj, 'contract_id' => $contract, 'certificate_id' => $certificate]);
            return $this->errorResponse('Failed to delete payment certificate', 500);
        }
    }

    /**
     * Submit payment certificate (draft → submitted)
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/payment-certificates/{certificate}/submit
     * 
     * Round 230: Workflow/Approval for Payment Certificates
     */
    public function submit(Request $request, string $proj, string $contract, string $certificate): JsonResponse
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

            $certificateModel = $this->paymentService->findPaymentCertificateForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $certificate
            );

            try {
                $this->authorize('approve', $certificateModel);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return $this->errorResponse('You do not have permission to perform this action', 403, null, 'FORBIDDEN');
            }

            // Validate transition: only from draft → submitted
            if ($certificateModel->status !== 'draft') {
                return $this->errorResponse(
                    'Payment certificate can only be submitted from draft status',
                    422,
                    null,
                    'INVALID_STATUS_TRANSITION'
                );
            }

            $oldStatus = $certificateModel->status;
            
            $certificateModel = DB::transaction(function () use ($certificateModel, $project, $tenantId, $oldStatus, $request) {
                $certificateModel->status = 'submitted';
                $certificateModel->updated_by = auth()->id();
                $certificateModel->save();

                // Log activity
                ProjectActivity::create([
                    'project_id' => $project->id,
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'action' => 'certificate_submitted',
                    'entity_type' => 'ContractPaymentCertificate',
                    'entity_id' => $certificateModel->id,
                    'description' => "Payment certificate '{$certificateModel->code}' was submitted",
                    'metadata' => [
                        'certificate_id' => $certificateModel->id,
                        'certificate_code' => $certificateModel->code,
                        'old_status' => $oldStatus,
                        'new_status' => 'submitted',
                        'user_id' => auth()->id(),
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Round 235: Audit log
                $this->auditLogService->record(
                    tenantId: $tenantId,
                    userId: auth()->id(),
                    action: 'certificate.submitted',
                    entityType: 'ContractPaymentCertificate',
                    entityId: $certificateModel->id,
                    projectId: $project->id,
                    before: ['status' => $oldStatus],
                    after: ['status' => 'submitted'],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $certificateModel->fresh();
            });

            return $this->successResponse(
                new ContractPaymentCertificateResource($certificateModel),
                'Payment certificate submitted successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment certificate not found', 404, null, 'CERTIFICATE_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'submit', 'project_id' => $proj, 'contract_id' => $contract, 'certificate_id' => $certificate]);
            return $this->errorResponse('Failed to submit payment certificate', 500);
        }
    }

    /**
     * Approve payment certificate (submitted → approved)
     * 
     * POST /api/v1/app/projects/{proj}/contracts/{contract}/payment-certificates/{certificate}/approve
     * 
     * Round 230: Workflow/Approval for Payment Certificates
     */
    public function approve(Request $request, string $proj, string $contract, string $certificate): JsonResponse
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

            $certificateModel = $this->paymentService->findPaymentCertificateForContractOrFail(
                $tenantId,
                $project,
                $contractModel,
                $certificate
            );

            try {
                $this->authorize('approve', $certificateModel);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return $this->errorResponse('You do not have permission to perform this action', 403, null, 'FORBIDDEN');
            }

            // Validate transition: only from submitted → approved
            if ($certificateModel->status !== 'submitted') {
                return $this->errorResponse(
                    'Payment certificate can only be approved from submitted status',
                    422,
                    null,
                    'INVALID_STATUS_TRANSITION'
                );
            }

            // Round 241: Dual Approval Workflow
            $user = auth()->user();
            $requiresDualApproval = $this->costApprovalPolicyService->requiresDualApprovalForCertificate($certificateModel);
            $isHighPrivilege = $this->costApprovalPolicyService->isHighPrivilege($user);

            // If dual approval is required and user is not high privilege, implement dual approval flow
            if ($requiresDualApproval && !$isHighPrivilege) {
                // Check if this is first approval
                if ($certificateModel->first_approved_by === null) {
                    // Record first approval
                    $certificateModel = DB::transaction(function () use ($certificateModel, $project, $tenantId, $request) {
                        $certificateModel->first_approved_by = auth()->id();
                        $certificateModel->first_approved_at = now();
                        $certificateModel->requires_dual_approval = true;
                        $certificateModel->status = 'approved'; // Status still changes to approved
                        $certificateModel->updated_by = auth()->id();
                        $certificateModel->save();

                        // Round 241: Audit log for first approval
                        $this->auditLogService->record(
                            tenantId: $tenantId,
                            userId: auth()->id(),
                            action: 'certificate.first_approved',
                            entityType: 'ContractPaymentCertificate',
                            entityId: $certificateModel->id,
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

                        return $certificateModel->fresh();
                    });

                    return $this->successResponse(
                        new ContractPaymentCertificateResource($certificateModel),
                        'First-level approval recorded. Waiting for second approver.',
                        [
                            'dual_approval_stage' => 'first',
                        ]
                    );
                }

                // Check if this is second approval
                if ($certificateModel->second_approved_by === null) {
                    // Must be a different user than first approver
                    if ($certificateModel->first_approved_by === auth()->id()) {
                        return $this->errorResponse(
                            'Second approval must be performed by a different approver',
                            403,
                            null,
                            'DUAL_APPROVAL_SAME_USER'
                        );
                    }

                    // Record second approval and finalize
                    $oldStatus = $certificateModel->status;
                    $certificateModel = DB::transaction(function () use ($certificateModel, $project, $tenantId, $oldStatus, $request) {
                        $certificateModel->second_approved_by = auth()->id();
                        $certificateModel->second_approved_at = now();
                        $certificateModel->status = 'approved';
                        $certificateModel->updated_by = auth()->id();
                        $certificateModel->save();

                        // Round 241: Audit log for second approval
                        $this->auditLogService->record(
                            tenantId: $tenantId,
                            userId: auth()->id(),
                            action: 'certificate.second_approved',
                            entityType: 'ContractPaymentCertificate',
                            entityId: $certificateModel->id,
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
                            'action' => 'certificate_approved',
                            'entity_type' => 'ContractPaymentCertificate',
                            'entity_id' => $certificateModel->id,
                            'description' => "Payment certificate '{$certificateModel->code}' was approved (dual approval completed)",
                            'metadata' => [
                                'certificate_id' => $certificateModel->id,
                                'certificate_code' => $certificateModel->code,
                                'old_status' => $oldStatus,
                                'new_status' => 'approved',
                                'first_approved_by' => $certificateModel->first_approved_by,
                                'second_approved_by' => auth()->id(),
                                'approved_at' => now()->toISOString(),
                            ],
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                        ]);

                        // Round 252: Notification for certificate approval (notify creator)
                        if ($certificateModel->created_by && $certificateModel->created_by !== auth()->id()) {
                            try {
                                $this->notificationService->notifyUser(
                                    userId: (string) $certificateModel->created_by,
                                    module: 'cost',
                                    type: 'certificate.approved',
                                    title: 'Chứng chỉ thanh toán đã được phê duyệt',
                                    message: sprintf("Chứng chỉ thanh toán \"%s\" trong dự án \"%s\" đã được phê duyệt.", $certificateModel->code ?? $certificateModel->id, $project->name),
                                    entityType: 'payment_certificate',
                                    entityId: $certificateModel->id,
                                    metadata: [
                                        'project_id' => $project->id,
                                        'project_name' => $project->name,
                                        'status' => $certificateModel->status,
                                    ],
                                    tenantId: (string) $tenantId
                                );
                            } catch (\Exception $e) {
                                \Log::warning('Failed to create notification for certificate approval', [
                                    'error' => $e->getMessage(),
                                    'certificate_id' => $certificateModel->id,
                                    'created_by' => $certificateModel->created_by,
                                ]);
                            }
                        }

                        return $certificateModel->fresh();
                    });

                    return $this->successResponse(
                        new ContractPaymentCertificateResource($certificateModel),
                        'Dual approval completed.',
                        [
                            'dual_approval_stage' => 'second',
                        ]
                    );
                }

                // Already dual-approved, proceed with normal approval
            }

            // Normal approval flow (no dual approval required OR high privilege user OR already dual-approved)
            $oldStatus = $certificateModel->status;
            
            $certificateModel = DB::transaction(function () use ($certificateModel, $project, $tenantId, $oldStatus, $request) {
                $certificateModel->status = 'approved';
                $certificateModel->updated_by = auth()->id();
                $certificateModel->save();

                // Note: Approved certificates are already accounted for in total_certified_amount
                // and time-series metrics by existing logic (R221, R223)

                // Log activity
                ProjectActivity::create([
                    'project_id' => $project->id,
                    'tenant_id' => $tenantId,
                    'user_id' => auth()->id(),
                    'action' => 'certificate_approved',
                    'entity_type' => 'ContractPaymentCertificate',
                    'entity_id' => $certificateModel->id,
                    'description' => "Payment certificate '{$certificateModel->code}' was approved",
                    'metadata' => [
                        'certificate_id' => $certificateModel->id,
                        'certificate_code' => $certificateModel->code,
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
                    action: 'certificate.approved',
                    entityType: 'ContractPaymentCertificate',
                    entityId: $certificateModel->id,
                    projectId: $project->id,
                    before: ['status' => $oldStatus],
                    after: ['status' => 'approved'],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                // Round 252: Notification for certificate approval (notify creator)
                if ($certificateModel->created_by && $certificateModel->created_by !== auth()->id()) {
                    try {
                        $this->notificationService->notifyUser(
                            userId: (string) $certificateModel->created_by,
                            module: 'cost',
                            type: 'certificate.approved',
                            title: 'Chứng chỉ thanh toán đã được phê duyệt',
                            message: sprintf("Chứng chỉ thanh toán \"%s\" trong dự án \"%s\" đã được phê duyệt.", $certificateModel->code ?? $certificateModel->id, $project->name),
                            entityType: 'payment_certificate',
                            entityId: $certificateModel->id,
                            metadata: [
                                'project_id' => $project->id,
                                'project_name' => $project->name,
                                'status' => $certificateModel->status,
                            ],
                            tenantId: (string) $tenantId
                        );
                    } catch (\Exception $e) {
                        \Log::warning('Failed to create notification for certificate approval', [
                            'error' => $e->getMessage(),
                            'certificate_id' => $certificateModel->id,
                            'created_by' => $certificateModel->created_by,
                        ]);
                    }
                }

                return $certificateModel->fresh();
            });

            return $this->successResponse(
                new ContractPaymentCertificateResource($certificateModel),
                'Payment certificate approved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Payment certificate not found', 404, null, 'CERTIFICATE_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'approve', 'project_id' => $proj, 'contract_id' => $contract, 'certificate_id' => $certificate]);
            return $this->errorResponse('Failed to approve payment certificate', 500);
        }
    }
}
