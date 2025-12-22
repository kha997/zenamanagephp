<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\ContractStoreRequest;
use App\Http\Requests\ContractUpdateRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ContractManagementService;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ContractController
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * Handles contracts CRUD operations for projects
 */
class ContractController extends BaseApiV1Controller
{
    public function __construct(
        private ContractManagementService $contractService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * List contracts for a project
     * 
     * GET /api/v1/app/projects/{proj}/contracts
     */
    public function index(Request $request, string $proj): JsonResponse
    {
        try {
            $this->authorize('viewAny', Contract::class);
            
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $contracts = $this->contractService->listContractsForProject($tenantId, $project);

            return $this->successResponse(
                ContractResource::collection($contracts),
                'Contracts retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index', 'project_id' => $proj]);
            return $this->errorResponse('Failed to retrieve contracts', 500);
        }
    }

    /**
     * Get contract by ID
     * 
     * GET /api/v1/app/projects/{proj}/contracts/{contract}
     */
    public function show(Request $request, string $proj, string $contract): JsonResponse
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

            $this->authorize('view', $contractModel);

            return $this->successResponse(
                new ContractResource($contractModel),
                'Contract retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to retrieve contract', 500);
        }
    }

    /**
     * Create contract for a project
     * 
     * POST /api/v1/app/projects/{proj}/contracts
     */
    public function store(ContractStoreRequest $request, string $proj): JsonResponse
    {
        try {
            $this->authorize('create', Contract::class);
            
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $data = $request->validated();
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $contract = $this->contractService->createContractForProject(
                $tenantId,
                $project,
                $data,
                $lines
            );

            return $this->successResponse(
                new ContractResource($contract),
                'Contract created successfully',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store', 'project_id' => $proj]);
            return $this->errorResponse('Failed to create contract', 500);
        }
    }

    /**
     * Update contract for a project
     * 
     * PATCH /api/v1/app/projects/{proj}/contracts/{contract}
     */
    public function update(ContractUpdateRequest $request, string $proj, string $contract): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $data = $request->validated();
            $lines = isset($data['lines']) ? $data['lines'] : null;
            unset($data['lines']);

            // Get contract to authorize
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);
            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }
            
            $this->authorize('update', $contractModel);

            $contractModel = $this->contractService->updateContractForProject(
                $tenantId,
                $project,
                $contract,
                $data,
                $lines
            );

            return $this->successResponse(
                new ContractResource($contractModel),
                'Contract updated successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to update contract', 500);
        }
    }

    /**
     * Delete contract for a project
     * 
     * DELETE /api/v1/app/projects/{proj}/contracts/{contract}
     */
    public function destroy(Request $request, string $proj, string $contract): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            // Get contract to authorize
            $contracts = $this->contractService->listContractsForProject($tenantId, $project);
            $contractModel = $contracts->firstWhere('id', $contract);
            if (!$contractModel) {
                return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
            }
            
            $this->authorize('delete', $contractModel);

            $this->contractService->deleteContractForProject($tenantId, $project, $contract);

            return $this->successResponse(null, 'Contract deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Contract not found', 404, null, 'CONTRACT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'project_id' => $proj, 'contract_id' => $contract]);
            return $this->errorResponse('Failed to delete contract', 500);
        }
    }
}
