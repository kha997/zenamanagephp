<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use App\Models\Contract;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\Project;
use App\Services\DeliverablePdfExportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContractController extends BaseApiController
{
    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? data_get(Auth::user(), 'tenant_id');

        return $tenantId ? (string) $tenantId : '';
    }

    private function findProjectOrFail(string $tenantId, string $projectId): Project
    {
        /** @var Project $project */
        $project = Project::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($projectId)
            ->firstOrFail();

        return $project;
    }

    private function findContractOrFail(string $tenantId, string $projectId, string $contractId): Contract
    {
        /** @var Contract $contract */
        $contract = Contract::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->whereKey($contractId)
            ->firstOrFail();

        return $contract;
    }

    public function index(Request $request, string $project): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $this->findProjectOrFail($tenantId, $project);
        } catch (ModelNotFoundException) {
            return $this->notFound('Project not found');
        }

        $this->authorize('viewAny', Contract::class);

        $perPage = min((int) $request->input('per_page', 15), 100);

        $contracts = Contract::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $project)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->successResponse([
            'items' => $contracts->items(),
            'pagination' => [
                'page' => $contracts->currentPage(),
                'per_page' => $contracts->perPage(),
                'total' => $contracts->total(),
                'last_page' => $contracts->lastPage(),
            ],
        ], 'Contracts retrieved successfully');
    }

    public function show(Request $request, string $project, string $contract): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $contractModel = $this->findContractOrFail($tenantId, $project, $contract);
        } catch (ModelNotFoundException) {
            return $this->notFound('Contract not found');
        }

        $this->authorize('view', $contractModel);

        return $this->successResponse($contractModel, 'Contract retrieved successfully');
    }

    public function store(Request $request, string $project): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $this->findProjectOrFail($tenantId, $project);
        } catch (ModelNotFoundException) {
            return $this->notFound('Project not found');
        }

        $this->authorize('create', Contract::class);

        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('contracts', 'code')->where('tenant_id', $tenantId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'contract_type' => ['nullable', Rule::in(Contract::VALID_TYPES)],
            'status' => ['nullable', Rule::in(Contract::VALID_STATUSES)],
            'currency' => ['nullable', 'string', 'size:3'],
            'total_value' => ['nullable', 'numeric', 'min:0'],
            'signed_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $userId = Auth::id();

        $contractModel = Contract::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => $project,
            'code' => strtoupper((string) $request->string('code')),
            'title' => $request->string('title')->value(),
            'contract_type' => (string) $request->input('contract_type', Contract::TYPE_OTHER),
            'status' => (string) $request->input('status', Contract::STATUS_DRAFT),
            'currency' => strtoupper((string) $request->input('currency', 'USD')),
            'total_value' => (float) $request->input('total_value', 0),
            'signed_at' => $request->input('signed_at'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'created_by' => $userId ? (string) $userId : null,
        ]);

        return $this->successResponse($contractModel, 'Contract created successfully', 201);
    }

    public function update(Request $request, string $project, string $contract): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $contractModel = $this->findContractOrFail($tenantId, $project, $contract);
        } catch (ModelNotFoundException) {
            return $this->notFound('Contract not found');
        }

        $this->authorize('update', $contractModel);

        $validator = Validator::make($request->all(), [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('contracts', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($contractModel->id),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(Contract::VALID_STATUSES)],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'total_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'signed_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $payload = $request->only([
            'code',
            'title',
            'status',
            'currency',
            'total_value',
            'signed_at',
            'start_date',
            'end_date',
        ]);

        if (array_key_exists('code', $payload) && is_string($payload['code'])) {
            $payload['code'] = strtoupper($payload['code']);
        }

        if (array_key_exists('currency', $payload) && is_string($payload['currency'])) {
            $payload['currency'] = strtoupper($payload['currency']);
        }

        $contractModel->update($payload);

        return $this->successResponse($contractModel->fresh(), 'Contract updated successfully');
    }

    public function destroy(Request $request, string $project, string $contract): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $contractModel = $this->findContractOrFail($tenantId, $project, $contract);
        } catch (ModelNotFoundException) {
            return $this->notFound('Contract not found');
        }

        $this->authorize('delete', $contractModel);

        $contractModel->delete();

        return $this->successResponse(null, 'Contract deleted successfully');
    }

    public function materialReceipts(Request $request, string $project, string $contract): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $contractModel = $this->findContractOrFail($tenantId, $project, $contract);
        } catch (ModelNotFoundException) {
            return $this->notFound('Contract not found');
        }

        $this->authorize('view', $contractModel);

        $receipts = MaterialReceipt::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $project)
            ->where('contract_id', $contractModel->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->successResponse($receipts, 'Mapped receipts retrieved successfully');
    }

    public function costSummary(Request $request, string $project, string $contract): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $contractModel = $this->findContractOrFail($tenantId, $project, $contract);
        } catch (ModelNotFoundException) {
            return $this->notFound('Contract not found');
        }

        $this->authorize('view', $contractModel);

        $receiptIds = MaterialReceipt::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $project)
            ->where('contract_id', $contractModel->id)
            ->pluck('id');

        $lines = MaterialReceiptLine::query()
            ->whereIn('material_receipt_id', $receiptIds)
            ->get();

        $pricedLines = $lines->filter(fn ($l) => $l->unit_cost !== null);
        $unpricedLines = $lines->filter(fn ($l) => $l->unit_cost === null);

        $pricedTotal = $pricedLines->sum(fn ($l) => (float) $l->quantity_received * (float) $l->unit_cost);

        return $this->successResponse([
            'project_id' => $project,
            'contract_id' => (string) $contractModel->id,
            'summary' => [
                'mapped_receipt_count' => $receiptIds->count(),
                'line_count' => $lines->count(),
                'priced_line_count' => $pricedLines->count(),
                'unpriced_line_count' => $unpricedLines->count(),
                'priced_line_cost_total' => round($pricedTotal, 2),
            ],
        ], 'Cost summary retrieved successfully');
    }

    public function pdf(Request $request, string $project, string $contract, DeliverablePdfExportService $pdfService): JsonResponse|Response
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $contractModel = $this->findContractOrFail($tenantId, $project, $contract);
        } catch (ModelNotFoundException) {
            return $this->notFound('Contract not found');
        }

        $this->authorize('view', $contractModel);

        $html = view('contracts.pdf', ['contract' => $contractModel])->render();

        try {
            $pdf = $pdfService->render($html, [], [
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (DeliverablePdfExportUnavailableException $exception) {
            return $this->errorResponse($exception->getMessage(), 501);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="hop-dong-' . $contractModel->code . '.pdf"',
        ]);
    }
}
