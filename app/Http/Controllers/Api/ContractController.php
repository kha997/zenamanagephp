<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Contract;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    private function findProject(string $tenantId, string $projectId): ?Project
    {
        return Project::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($projectId)
            ->first();
    }

    public function index(Request $request, string $project): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (!$this->findProject($tenantId, $project)) {
            return $this->notFound('Project not found');
        }

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

        $contractModel = Contract::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $project)
            ->whereKey($contract)
            ->first();

        if (!$contractModel) {
            return $this->notFound('Contract not found');
        }

        return $this->successResponse($contractModel, 'Contract retrieved successfully');
    }

    public function store(Request $request, string $project): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (!$this->findProject($tenantId, $project)) {
            return $this->notFound('Project not found');
        }

        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('contracts', 'code')->where('tenant_id', $tenantId),
            ],
            'title' => ['required', 'string', 'max:255'],
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

        $contractModel = Contract::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $project)
            ->whereKey($contract)
            ->first();

        if (!$contractModel) {
            return $this->notFound('Contract not found');
        }

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

        $contractModel = Contract::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $project)
            ->whereKey($contract)
            ->first();

        if (!$contractModel) {
            return $this->notFound('Contract not found');
        }

        $contractModel->delete();

        return $this->successResponse(null, 'Contract deleted successfully');
    }
}
