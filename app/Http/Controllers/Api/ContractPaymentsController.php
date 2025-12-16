<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Requests\Contracts\StoreContractPaymentRequest;
use App\Http\Requests\Contracts\UpdateContractPaymentRequest;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Services\Contracts\ContractPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ContractPayments API Controller
 * 
 * Round 36: Contract Payment Schedule Backend
 * Round 37: Payment Hardening - Uses ContractPaymentService for business invariants
 * 
 * Handles CRUD operations for contract payments with tenant isolation and RBAC.
 * Nested under /contracts/{contract}/payments endpoints.
 */
class ContractPaymentsController extends Controller
{
    use ResolvesTenantContext;

    protected ContractPaymentService $contractPaymentService;

    public function __construct(ContractPaymentService $contractPaymentService)
    {
        $this->contractPaymentService = $contractPaymentService;
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
     * Display a listing of contract payments for a contract
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

        // Get payments for this contract, ordered by sort_order and due_date
        $query = $contract->payments()->ordered();

        // Apply filters if needed
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'meta' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage()
            ]
        ]);
    }

    /**
     * Store a newly created contract payment
     * 
     * Round 37: Uses ContractPaymentService to enforce business invariants
     */
    public function store(StoreContractPaymentRequest $request, Contract $contract): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Check authorization via policy
        $this->authorize('create', [ContractPayment::class, $contract]);

        $validated = $request->validated();
        
        // If currency is not provided, inherit from contract
        if (!isset($validated['currency']) || empty($validated['currency'])) {
            $validated['currency'] = $contract->currency ?? 'USD';
        }

        // Use service to create payment (enforces business invariants)
        $payment = $this->contractPaymentService->createPaymentForContract(
            $contract,
            $validated,
            (string) $user->id
        );

        return response()->json([
            'success' => true,
            'data' => $payment,
            'message' => 'Contract payment created successfully'
        ], 201);
    }

    /**
     * Update the specified contract payment
     * 
     * Round 37: Uses ContractPaymentService to enforce business invariants
     */
    public function update(UpdateContractPaymentRequest $request, Contract $contract, ContractPayment $payment): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Ensure payment belongs to contract and tenant
        if ((string) $payment->contract_id !== (string) $contract->id) {
            abort(404, 'Payment not found for this contract');
        }

        if ((string) $payment->tenant_id !== (string) $tenantId) {
            abort(404, 'Payment not found');
        }

        // Check authorization via policy
        $this->authorize('update', $payment);

        $validated = $request->validated();

        // Use service to update payment (enforces business invariants)
        $payment = $this->contractPaymentService->updatePaymentForContract(
            $contract,
            $payment,
            $validated,
            (string) $user->id
        );

        return response()->json([
            'success' => true,
            'data' => $payment,
            'message' => 'Contract payment updated successfully'
        ]);
    }

    /**
     * Remove the specified contract payment
     */
    public function destroy(Request $request, Contract $contract, ContractPayment $payment): Response
    {
        $tenantId = $this->getTenantId($request);

        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            abort(404, 'Contract not found');
        }

        // Ensure payment belongs to contract and tenant
        if ((string) $payment->contract_id !== (string) $contract->id) {
            abort(404, 'Payment not found for this contract');
        }

        if ((string) $payment->tenant_id !== (string) $tenantId) {
            abort(404, 'Payment not found');
        }

        // Check authorization via policy
        $this->authorize('delete', $payment);

        $payment->delete();

        Log::info('Contract payment deleted via API', [
            'payment_id' => $payment->id,
            'contract_id' => $contract->id,
            'tenant_id' => $payment->tenant_id,
            'deleted_by' => auth()->id()
        ]);

        return response()->noContent();
    }
}
