<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Contract;
use App\Models\ContractPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContractPaymentController extends BaseApiController
{
    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? data_get(Auth::user(), 'tenant_id');

        return $tenantId ? (string) $tenantId : '';
    }

    private function findContract(string $tenantId, string $contractId): ?Contract
    {
        return Contract::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($contractId)
            ->first();
    }

    public function index(Request $request, string $contract): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (!$this->findContract($tenantId, $contract)) {
            return $this->notFound('Contract not found');
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        $payments = ContractPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', $contract)
            ->orderBy('due_date')
            ->paginate($perPage);

        return $this->successResponse([
            'items' => $payments->items(),
            'pagination' => [
                'page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ], 'Contract payments retrieved successfully');
    }

    public function store(Request $request, string $contract): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (!$this->findContract($tenantId, $contract)) {
            return $this->notFound('Contract not found');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(ContractPayment::VALID_STATUSES)],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $payment = ContractPayment::query()->create([
            'tenant_id' => $tenantId,
            'contract_id' => $contract,
            'name' => $request->string('name')->value(),
            'amount' => (float) $request->input('amount'),
            'due_date' => $request->input('due_date'),
            'status' => (string) $request->input('status', ContractPayment::STATUS_PLANNED),
            'paid_at' => $request->input('paid_at'),
            'note' => $request->input('note'),
        ]);

        return $this->successResponse($payment, 'Contract payment created successfully', 201);
    }

    public function update(Request $request, string $contract, string $payment): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (!$this->findContract($tenantId, $contract)) {
            return $this->notFound('Contract not found');
        }

        $paymentModel = ContractPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', $contract)
            ->whereKey($payment)
            ->first();

        if (!$paymentModel) {
            return $this->notFound('Contract payment not found');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(ContractPayment::VALID_STATUSES)],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $paymentModel->update($request->only([
            'name',
            'amount',
            'due_date',
            'status',
            'paid_at',
            'note',
        ]));

        return $this->successResponse($paymentModel->fresh(), 'Contract payment updated successfully');
    }

    public function destroy(Request $request, string $contract, string $payment): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (!$this->findContract($tenantId, $contract)) {
            return $this->notFound('Contract not found');
        }

        $paymentModel = ContractPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', $contract)
            ->whereKey($payment)
            ->first();

        if (!$paymentModel) {
            return $this->notFound('Contract payment not found');
        }

        $paymentModel->delete();

        return $this->successResponse(null, 'Contract payment deleted successfully');
    }
}
