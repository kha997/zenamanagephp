<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Requests\Contracts\StoreContractRequest;
use App\Http\Requests\Contracts\UpdateContractRequest;
use App\Models\Contract;
use App\Services\Reports\ContractsReportsService;
use App\Services\Contracts\ContractExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Contracts API Controller
 * 
 * Round 33: MVP Contract Backend
 * 
 * Handles CRUD operations for contracts with tenant isolation and RBAC.
 */
class ContractsController extends Controller
{
    use ResolvesTenantContext;

    /**
     * Get tenant ID from request context (throws if not found)
     */
    protected function getTenantId(Request $request): string
    {
        $tenantId = $this->resolveActiveTenantIdFromRequest($request);
        if (!$tenantId) {
            throw new \RuntimeException('Tenant ID not found for user');
        }
        return $tenantId;
    }

    /**
     * Display a listing of contracts
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $query = Contract::where('tenant_id', $tenantId);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->search($search);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $contracts = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $contracts->items(),
            'meta' => [
                'total' => $contracts->total(),
                'per_page' => $contracts->perPage(),
                'current_page' => $contracts->currentPage(),
                'last_page' => $contracts->lastPage()
            ]
        ]);
    }

    /**
     * Display the specified contract
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $contract = Contract::where('tenant_id', $tenantId)->findOrFail($id);
        
        // Check authorization via policy
        $this->authorize('view', $contract);
        
        // Load relationships if they exist (optional, won't fail if null)
        // Only load if foreign keys are not null
        if ($contract->client_id) {
            $contract->load('client');
        }
        if ($contract->project_id) {
            $contract->load('project');
        }
        if ($contract->created_by_id) {
            $contract->load('createdBy');
        }
        if ($contract->updated_by_id) {
            $contract->load('updatedBy');
        }

        return response()->json([
            'success' => true,
            'data' => $contract
        ]);
    }

    /**
     * Store a newly created contract
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId($request);

        // Check authorization via policy
        $this->authorize('create', Contract::class);

        $contract = Contract::create([
            'tenant_id' => $tenantId,
            'code' => $request->validated()['code'],
            'name' => $request->validated()['name'],
            'status' => $request->validated()['status'] ?? Contract::STATUS_DRAFT,
            'client_id' => $request->validated()['client_id'] ?? null,
            'project_id' => $request->validated()['project_id'] ?? null,
            'signed_at' => $request->validated()['signed_at'] ?? null,
            'effective_from' => $request->validated()['effective_from'] ?? null,
            'effective_to' => $request->validated()['effective_to'] ?? null,
            'currency' => $request->validated()['currency'] ?? 'USD',
            'total_value' => $request->validated()['total_value'],
            'notes' => $request->validated()['notes'] ?? null,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        Log::info('Contract created via API', [
            'contract_id' => $contract->id,
            'code' => $contract->code,
            'tenant_id' => $contract->tenant_id,
            'created_by' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'data' => $contract,
            'message' => 'Contract created successfully'
        ], 201);
    }

    /**
     * Update the specified contract
     */
    public function update(UpdateContractRequest $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId($request);
        $contract = Contract::where('tenant_id', $tenantId)->findOrFail($id);

        // Check authorization via policy
        $this->authorize('update', $contract);

        // Update contract (tenant_id cannot be changed)
        $validated = $request->validated();
        $updateData = [];
        
        if (isset($validated['code'])) {
            $updateData['code'] = $validated['code'];
        }
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }
        if (array_key_exists('client_id', $validated)) {
            $updateData['client_id'] = $validated['client_id'];
        }
        if (array_key_exists('project_id', $validated)) {
            $updateData['project_id'] = $validated['project_id'];
        }
        if (array_key_exists('signed_at', $validated)) {
            $updateData['signed_at'] = $validated['signed_at'];
        }
        if (array_key_exists('effective_from', $validated)) {
            $updateData['effective_from'] = $validated['effective_from'];
        }
        if (array_key_exists('effective_to', $validated)) {
            $updateData['effective_to'] = $validated['effective_to'];
        }
        if (isset($validated['currency'])) {
            $updateData['currency'] = $validated['currency'];
        }
        if (isset($validated['total_value'])) {
            $updateData['total_value'] = $validated['total_value'];
        }
        if (array_key_exists('notes', $validated)) {
            $updateData['notes'] = $validated['notes'];
        }
        $updateData['updated_by_id'] = $user->id;
        
        $contract->update($updateData);
        
        // Refresh to get latest data
        $contract->refresh();

        Log::info('Contract updated via API', [
            'contract_id' => $contract->id,
            'code' => $contract->code,
            'tenant_id' => $contract->tenant_id,
            'updated_by' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'data' => $contract,
            'message' => 'Contract updated successfully'
        ]);
    }

    /**
     * Remove the specified contract
     */
    public function destroy(Request $request, string $id): Response
    {
        $tenantId = $this->getTenantId($request);
        $contract = Contract::where('tenant_id', $tenantId)->findOrFail($id);

        // Check authorization via policy
        $this->authorize('delete', $contract);

        $contract->delete();

        Log::info('Contract deleted via API', [
            'contract_id' => $contract->id,
            'code' => $contract->code,
            'tenant_id' => $contract->tenant_id,
            'deleted_by' => auth()->id()
        ]);

        return response()->noContent();
    }

    /**
     * Get cost summary for a contract
     * 
     * Round 45: Contract Cost Control - Cost Summary API
     * 
     * Returns budget, actual, and payments summary for a single contract.
     */
    public function getCostSummary(Request $request, Contract $contract): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        
        // Check tenant match
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }
        
        // Check authorization via policy
        $this->authorize('view', $contract);
        
        // Get cost summary via service
        $service = app(ContractsReportsService::class);
        $summary = $service->getContractCostSummary($tenantId, $contract);
        
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary
            ]
        ]);
    }

    /**
     * Export contracts list to CSV
     * 
     * Round 47: Cost Overruns Dashboard + Export
     * 
     * Exports contracts with budget, actual, and payment summaries.
     */
    public function exportContracts(Request $request): Response
    {
        $tenantId = $this->getTenantId($request);

        // Get filters from query string (same as index method)
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'client_id' => $request->query('client_id'),
            'project_id' => $request->query('project_id'),
            'signed_from' => $request->query('signed_from'),
            'signed_to' => $request->query('signed_to'),
            'sort_by' => $request->query('sort_by'),
            'sort_direction' => $request->query('sort_direction'),
        ];

        // Remove null values
        $filters = array_filter($filters, fn($value) => $value !== null);

        $exportService = app(ContractExportService::class);
        return $exportService->exportContractsForTenant($tenantId, $filters);
    }

    /**
     * Export contract cost schedule to CSV
     * 
     * Round 47: Cost Overruns Dashboard + Export
     * 
     * Exports detailed cost schedule (budget lines, payments, expenses) for a single contract.
     */
    public function exportContractCostSchedule(Request $request, Contract $contract): Response
    {
        $tenantId = $this->getTenantId($request);

        // Check tenant match
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Check authorization via policy
        $this->authorize('view', $contract);

        $exportService = app(ContractExportService::class);
        return $exportService->exportContractCostSchedule($tenantId, $contract);
    }
}
