<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Requests\Contracts\StoreContractBudgetLineRequest;
use App\Http\Requests\Contracts\UpdateContractBudgetLineRequest;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Services\Contracts\ContractBudgetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ContractBudgetLines API Controller
 * 
 * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
 * 
 * Handles CRUD operations for contract budget lines with tenant isolation and RBAC.
 * Nested under /contracts/{contract}/budget-lines endpoints.
 */
class ContractBudgetLinesController extends Controller
{
    use ResolvesTenantContext;

    protected ContractBudgetService $contractBudgetService;

    public function __construct(ContractBudgetService $contractBudgetService)
    {
        $this->contractBudgetService = $contractBudgetService;
    }

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
     * Display a listing of contract budget lines for a contract
     */
    public function index(Request $request, Contract $contract): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        
        // Ensure contract belongs to tenant (route binding should handle this, but double-check)
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Check authorization via policy
        $this->authorize('view', $contract);

        // Get budget lines for this contract, ordered by sort_order and name
        $query = $contract->budgetLines()->ordered();

        // Apply filters if needed
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $budgetLines = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $budgetLines->items(),
            'meta' => [
                'total' => $budgetLines->total(),
                'per_page' => $budgetLines->perPage(),
                'current_page' => $budgetLines->currentPage(),
                'last_page' => $budgetLines->lastPage()
            ]
        ]);
    }

    /**
     * Store a newly created contract budget line
     */
    public function store(StoreContractBudgetLineRequest $request, Contract $contract): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Check authorization via policy
        $this->authorize('create', [ContractBudgetLine::class, $contract]);

        $validated = $request->validated();

        // Use service to create budget line
        $line = $this->contractBudgetService->createBudgetLineForContract(
            $contract,
            $validated,
            $user
        );

        return response()->json([
            'success' => true,
            'data' => $line,
            'message' => 'Contract budget line created successfully'
        ], 201);
    }

    /**
     * Update the specified contract budget line
     */
    public function update(UpdateContractBudgetLineRequest $request, Contract $contract, ContractBudgetLine $line): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Ensure line belongs to contract and tenant
        if ((string) $line->contract_id !== (string) $contract->id) {
            abort(404, 'Budget line not found for this contract');
        }

        if ((string) $line->tenant_id !== (string) $tenantId) {
            abort(404, 'Budget line not found');
        }

        // Check authorization via policy
        $this->authorize('update', $line);

        $validated = $request->validated();

        // Use service to update budget line
        $line = $this->contractBudgetService->updateBudgetLineForContract(
            $contract,
            $line,
            $validated,
            $user
        );

        return response()->json([
            'success' => true,
            'data' => $line,
            'message' => 'Contract budget line updated successfully'
        ]);
    }

    /**
     * Remove the specified contract budget line
     */
    public function destroy(Request $request, Contract $contract, ContractBudgetLine $line): Response
    {
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Ensure line belongs to contract and tenant
        if ((string) $line->contract_id !== (string) $contract->id) {
            abort(404, 'Budget line not found for this contract');
        }

        if ((string) $line->tenant_id !== (string) $tenantId) {
            abort(404, 'Budget line not found');
        }

        // Check authorization via policy
        $this->authorize('delete', $line);

        $line->delete();

        Log::info('Contract budget line deleted via API', [
            'budget_line_id' => $line->id,
            'contract_id' => $contract->id,
            'tenant_id' => $line->tenant_id,
            'deleted_by' => auth()->id()
        ]);

        return response()->noContent();
    }
}

