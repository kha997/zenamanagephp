<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Requests\Contracts\StoreContractExpenseRequest;
use App\Http\Requests\Contracts\UpdateContractExpenseRequest;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Services\Contracts\ContractExpenseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ContractExpenses API Controller
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * Handles CRUD operations for contract expenses with tenant isolation and RBAC.
 * Nested under /contracts/{contract}/expenses endpoints.
 */
class ContractExpensesController extends Controller
{
    use ResolvesTenantContext;

    protected ContractExpenseService $contractExpenseService;

    public function __construct(ContractExpenseService $contractExpenseService)
    {
        $this->contractExpenseService = $contractExpenseService;
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
     * Display a listing of contract expenses for a contract
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

        // Get expenses for this contract, ordered by sort_order, incurred_at, name
        $query = $contract->expenses()->ordered();

        // Apply filters if needed
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->integer('per_page', 50);
        $expenses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $expenses->items(),
            'meta' => [
                'total' => $expenses->total(),
                'per_page' => $expenses->perPage(),
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage()
            ]
        ]);
    }

    /**
     * Store a newly created contract expense
     */
    public function store(StoreContractExpenseRequest $request, Contract $contract): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Check authorization via policy
        $this->authorize('create', [ContractExpense::class, $contract]);

        $validated = $request->validated();

        // Use service to create expense
        $expense = $this->contractExpenseService->createExpenseForContract(
            $tenantId,
            $contract,
            $validated,
            $user
        );

        return response()->json([
            'success' => true,
            'data' => $expense,
            'message' => 'Contract expense created successfully'
        ], 201);
    }

    /**
     * Update the specified contract expense
     */
    public function update(UpdateContractExpenseRequest $request, Contract $contract, ContractExpense $expense): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Ensure expense belongs to contract and tenant
        if ((string) $expense->contract_id !== (string) $contract->id) {
            abort(404, 'Expense not found for this contract');
        }

        if ((string) $expense->tenant_id !== (string) $tenantId) {
            abort(404, 'Expense not found');
        }

        // Check authorization via policy
        $this->authorize('update', $expense);

        $validated = $request->validated();

        // Use service to update expense
        $expense = $this->contractExpenseService->updateExpenseForContract(
            $tenantId,
            $contract,
            $expense,
            $validated,
            $user
        );

        return response()->json([
            'success' => true,
            'data' => $expense,
            'message' => 'Contract expense updated successfully'
        ]);
    }

    /**
     * Remove the specified contract expense
     */
    public function destroy(Request $request, Contract $contract, ContractExpense $expense): Response
    {
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Ensure expense belongs to contract and tenant
        if ((string) $expense->contract_id !== (string) $contract->id) {
            abort(404, 'Expense not found for this contract');
        }

        if ((string) $expense->tenant_id !== (string) $tenantId) {
            abort(404, 'Expense not found');
        }

        // Check authorization via policy
        $this->authorize('delete', $expense);

        $expense->delete();

        Log::info('Contract expense deleted via API', [
            'expense_id' => $expense->id,
            'contract_id' => $contract->id,
            'tenant_id' => $expense->tenant_id,
            'deleted_by' => auth()->id()
        ]);

        return response()->noContent();
    }
}

