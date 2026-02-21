<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Contract;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        $input = $request->all();
        if (array_key_exists('code', $input) && is_string($input['code'])) {
            $input['code'] = strtoupper(trim($input['code']));
        }

        $validator = Validator::make($input, [
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

        $validated = $validator->validated();
        $userId = Auth::id();

        $contractModel = Contract::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => $project,
            'code' => $validated['code'],
            'title' => $validated['title'],
            'status' => $validated['status'] ?? Contract::STATUS_DRAFT,
            'currency' => strtoupper($validated['currency'] ?? 'USD'),
            'total_value' => (float) ($validated['total_value'] ?? 0),
            'signed_at' => $validated['signed_at'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
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

        $input = $request->all();
        if (array_key_exists('code', $input) && is_string($input['code'])) {
            $input['code'] = strtoupper(trim($input['code']));
        }

        $validator = Validator::make($input, [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('contracts', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($contractModel->getKey()),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(Contract::VALID_STATUSES)],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'total_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'signed_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $payload = $validator->validated();

        if (($payload['status'] ?? null) === null) {
            unset($payload['status']);
        }

        if (($payload['currency'] ?? null) === null) {
            unset($payload['currency']);
        }

        if (array_key_exists('code', $payload) && is_string($payload['code'])) {
            $payload['code'] = strtoupper(trim($payload['code']));
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
}
