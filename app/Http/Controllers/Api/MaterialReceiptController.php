<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\MaterialReceipt;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MaterialReceiptController extends BaseApiController
{
    /** @var list<string> */
    private const MATERIAL_REQUEST_RESPONSE_FIELDS = [
        'id',
        'project_id',
        'request_number',
        'description',
        'status',
        'estimated_cost',
        'required_date',
        'requested_by',
        'approved_by',
        'approved_at',
        'created_at',
        'updated_at',
    ];

    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? data_get(Auth::user(), 'tenant_id');

        return $tenantId ? (string) $tenantId : '';
    }

    private function baseQuery(string $tenantId)
    {
        return MaterialReceipt::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'project:id,tenant_id,name,code',
                'vendor:id,tenant_id,name,code',
            ]);
    }

    private function findReceiptOrFail(string $tenantId, string $receiptId): MaterialReceipt
    {
        /** @var MaterialReceipt $receipt */
        $receipt = $this->baseQuery($tenantId)
            ->whereKey($receiptId)
            ->firstOrFail();

        return $receipt;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMaterialRequest(MaterialRequest $materialRequest): array
    {
        return Arr::only($materialRequest->attributesToArray(), self::MATERIAL_REQUEST_RESPONSE_FIELDS);
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('viewAny', MaterialReceipt::class);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $query = $this->baseQuery($tenantId)
            ->orderByDesc('receipt_date')
            ->orderByDesc('created_at');

        if (($projectId = (string) $request->input('project_id', '')) !== '') {
            $query->where('project_id', $projectId);
        }

        if (($vendorId = (string) $request->input('vendor_id', '')) !== '') {
            $query->where('vendor_id', $vendorId);
        }

        if (($contractId = (string) $request->input('contract_id', '')) !== '') {
            $query->where('contract_id', $contractId);
        }

        if (($materialRequestId = (string) $request->input('material_request_id', '')) !== '') {
            $query->where('material_request_id', $materialRequestId);
        }

        if (($term = trim((string) $request->input('q'))) !== '') {
            $query->where('receipt_number', 'like', '%' . $term . '%');
        }

        return $this->listSuccessResponse($query->paginate($perPage), 'Material receipts retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('create', MaterialReceipt::class);

        $validator = Validator::make($request->all(), [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'vendor_id' => [
                'nullable',
                'string',
                Rule::exists('vendors', 'id')->where('tenant_id', $tenantId),
            ],
            'contract_id' => [
                'nullable',
                'string',
                Rule::exists('contracts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', (string) $request->input('project_id')),
            ],
            'material_request_id' => [
                'nullable',
                'string',
                Rule::exists('zena_material_requests', 'id')
                    ->where('project_id', (string) $request->input('project_id'))
                    ->whereIn('project_id', Project::query()->select('id')->where('tenant_id', $tenantId)),
            ],
            'receipt_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('material_receipts', 'receipt_number')->where('tenant_id', $tenantId),
            ],
            'receipt_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => $request->string('project_id')->value(),
            'vendor_id' => $request->input('vendor_id'),
            'contract_id' => $request->input('contract_id'),
            'material_request_id' => $request->input('material_request_id'),
            'receipt_number' => strtoupper((string) $request->string('receipt_number')),
            'receipt_date' => $request->input('receipt_date'),
        ]);

        return $this->successResponse(
            $receipt->load(['project:id,tenant_id,name,code', 'vendor:id,tenant_id,name,code']),
            'Material receipt created successfully',
            201
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $receipt = $this->findReceiptOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material receipt not found');
        }

        $this->authorize('view', $receipt);

        return $this->successResponse($receipt, 'Material receipt retrieved successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $receipt = $this->findReceiptOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material receipt not found');
        }

        $this->authorize('update', $receipt);

        if ($receipt->lines()->exists()) {
            return $this->validationError([
                'receipt' => ['Receipt header correction is not allowed once receipt lines exist.'],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('vendors', 'id')->where('tenant_id', $tenantId),
            ],
            'contract_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('contracts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', (string) $receipt->project_id),
            ],
            'material_request_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('zena_material_requests', 'id')
                    ->where('project_id', (string) $receipt->project_id)
                    ->whereIn('project_id', Project::query()->select('id')->where('tenant_id', $tenantId)),
            ],
            'receipt_date' => ['sometimes', 'required', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $updates = [];

        if ($request->exists('vendor_id')) {
            $updates['vendor_id'] = $request->input('vendor_id');
        }

        if ($request->exists('contract_id')) {
            $updates['contract_id'] = $request->input('contract_id');
        }

        if ($request->exists('material_request_id')) {
            $updates['material_request_id'] = $request->input('material_request_id');
        }

        if ($request->exists('receipt_date')) {
            $updates['receipt_date'] = $request->input('receipt_date');
        }

        if ($updates !== []) {
            $receipt->fill($updates);
            $receipt->save();
        }

        return $this->successResponse(
            $receipt->fresh()->load(['project:id,tenant_id,name,code', 'vendor:id,tenant_id,name,code']),
            'Material receipt updated successfully'
        );
    }

    public function materialRequest(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $receipt = $this->findReceiptOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material receipt not found');
        }

        if (!$receipt->material_request_id) {
            return $this->notFound('Material request not found');
        }

        $materialRequest = MaterialRequest::query()
            ->select(self::MATERIAL_REQUEST_RESPONSE_FIELDS)
            ->whereKey((string) $receipt->material_request_id)
            ->whereHas('project', function ($projectQuery) use ($tenantId): void {
                $projectQuery->where('tenant_id', $tenantId);
            })
            ->first();

        if (!$materialRequest instanceof MaterialRequest) {
            return $this->notFound('Material request not found');
        }

        return $this->successResponse(
            $this->serializeMaterialRequest($materialRequest),
            'Linked material request retrieved successfully'
        );
    }
}
