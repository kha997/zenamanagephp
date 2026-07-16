<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\MaterialRequest;
use App\Models\MaterialReceipt;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MaterialRequestController extends BaseApiController
{
    use ZenaContractResponseTrait;

    /** @var list<string> */
    private const RESPONSE_FIELDS = [
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
            ?? Auth::user()?->tenant_id;

        return $tenantId ? (string) $tenantId : '';
    }

    private function scopedQuery(string $tenantId): Builder
    {
        return MaterialRequest::query()
            ->select(self::RESPONSE_FIELDS)
            ->whereHas('project', function ($projectQuery) use ($tenantId): void {
                $projectQuery->where('tenant_id', $tenantId);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MaterialRequest $materialRequest): array
    {
        return Arr::only($materialRequest->attributesToArray(), self::RESPONSE_FIELDS);
    }

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('viewAny', MaterialRequest::class);

        $query = $this->scopedQuery($tenantId);

        if ($request->filled('project_id')) {
            $query->where('project_id', (string) $request->input('project_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $materialRequests = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MaterialRequest $materialRequest): array => $this->serialize($materialRequest))
            ->values();

        return $this->zenaSuccessResponse($materialRequests, 'Material requests retrieved successfully');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $materialRequest = $this->scopedQuery($tenantId)
            ->whereKey($id)
            ->first();

        if (!$materialRequest instanceof MaterialRequest) {
            return $this->notFound('Material request not found');
        }

        $this->authorize('view', $materialRequest);

        return $this->zenaSuccessResponse(
            $this->serialize($materialRequest),
            'Material request retrieved successfully'
        );
    }

    public function receipts(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $materialRequest = $this->scopedQuery($tenantId)
            ->whereKey($id)
            ->first();

        if (!$materialRequest instanceof MaterialRequest) {
            return $this->notFound('Material request not found');
        }

        $this->authorize('view', $materialRequest);

        $receipts = MaterialReceipt::query()
            ->where('tenant_id', $tenantId)
            ->where('material_request_id', (string) $materialRequest->id)
            ->with([
                'project:id,tenant_id,name,code',
                'vendor:id,tenant_id,name,code',
            ])
            ->orderByDesc('receipt_date')
            ->orderByDesc('created_at')
            ->get()
            ->values();

        return $this->zenaSuccessResponse(
            $receipts,
            'Linked material receipts retrieved successfully'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('create', MaterialRequest::class);

        $validator = Validator::make($request->all(), [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'description' => ['required', 'string'],
            'estimated_cost' => ['nullable', 'numeric'],
            'required_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => (string) $request->input('project_id'),
            'request_number' => $this->generateRequestNumber(),
            'description' => (string) $request->input('description'),
            'status' => 'draft',
            'estimated_cost' => $request->input('estimated_cost'),
            'required_date' => $request->input('required_date'),
            'requested_by' => (string) $user->id,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($materialRequest->fresh() ?? $materialRequest),
            'Material request created successfully',
            201
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $materialRequest = $this->scopedQuery($tenantId)
            ->whereKey($id)
            ->first();

        if (!$materialRequest instanceof MaterialRequest) {
            return $this->notFound('Material request not found');
        }

        $this->authorize('update', $materialRequest);

        $validator = Validator::make($request->all(), [
            'description' => ['sometimes', 'required', 'string'],
            'estimated_cost' => ['nullable', 'numeric'],
            'required_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        if ((string) $materialRequest->status !== 'draft') {
            return $this->validationError([
                'status' => ['Only draft material requests can be updated.'],
            ]);
        }

        $materialRequest->fill($request->only([
            'description',
            'estimated_cost',
            'required_date',
        ]));
        $materialRequest->save();

        return $this->zenaSuccessResponse(
            $this->serialize($materialRequest->fresh() ?? $materialRequest),
            'Material request updated successfully'
        );
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $materialRequest = $this->scopedQuery($tenantId)
            ->whereKey($id)
            ->first();

        if (!$materialRequest instanceof MaterialRequest) {
            return $this->notFound('Material request not found');
        }

        $this->authorize('submit', $materialRequest);

        if ((string) $materialRequest->status !== 'draft') {
            return $this->validationError([
                'status' => ['Only draft material requests can be submitted.'],
            ]);
        }

        $materialRequest->status = 'submitted';
        $materialRequest->save();

        return $this->zenaSuccessResponse(
            $this->serialize($materialRequest->fresh() ?? $materialRequest),
            'Material request submitted successfully'
        );
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $materialRequest = $this->scopedQuery($tenantId)
            ->whereKey($id)
            ->first();

        if (!$materialRequest instanceof MaterialRequest) {
            return $this->notFound('Material request not found');
        }

        $this->authorize('approve', $materialRequest);

        if ((string) $materialRequest->status !== 'submitted') {
            return $this->validationError([
                'status' => ['Only submitted material requests can be approved.'],
            ]);
        }

        $materialRequest->status = 'approved';
        $materialRequest->approved_by = (string) $user->id;
        $materialRequest->approved_at = now();
        $materialRequest->save();

        return $this->zenaSuccessResponse(
            $this->serialize($materialRequest->fresh() ?? $materialRequest),
            'Material request approved successfully'
        );
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $materialRequest = $this->scopedQuery($tenantId)
            ->whereKey($id)
            ->first();

        if (!$materialRequest instanceof MaterialRequest) {
            return $this->notFound('Material request not found');
        }

        $this->authorize('reject', $materialRequest);

        if ((string) $materialRequest->status !== 'submitted') {
            return $this->validationError([
                'status' => ['Only submitted material requests can be rejected.'],
            ]);
        }

        $materialRequest->status = 'rejected';
        $materialRequest->save();

        return $this->zenaSuccessResponse(
            $this->serialize($materialRequest->fresh() ?? $materialRequest),
            'Material request rejected successfully'
        );
    }

    public function fulfill(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $materialRequest = $this->scopedQuery($tenantId)
            ->whereKey($id)
            ->first();

        if (!$materialRequest instanceof MaterialRequest) {
            return $this->notFound('Material request not found');
        }

        $this->authorize('fulfill', $materialRequest);

        if ((string) $materialRequest->status !== 'approved') {
            return $this->validationError([
                'status' => ['Only approved material requests can be fulfilled.'],
            ]);
        }

        $materialRequest->status = 'fulfilled';
        $materialRequest->save();

        return $this->zenaSuccessResponse(
            $this->serialize($materialRequest->fresh() ?? $materialRequest),
            'Material request fulfilled successfully'
        );
    }

    private function generateRequestNumber(): string
    {
        return 'MR-' . Str::upper((string) Str::ulid());
    }
}
