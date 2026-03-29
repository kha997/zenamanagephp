<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Boq;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BoqController extends BaseApiController
{
    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? data_get(Auth::user(), 'tenant_id');

        return $tenantId ? (string) $tenantId : '';
    }

    private function findBoqOrFail(string $tenantId, string $boqId): Boq
    {
        /** @var Boq $boq */
        $boq = Boq::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($boqId)
            ->firstOrFail();

        return $boq;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('viewAny', Boq::class);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $query = Boq::query()
            ->where('tenant_id', $tenantId)
            ->with('project:id,tenant_id,name,code')
            ->orderBy('name');

        if (($projectId = (string) $request->input('project_id', '')) !== '') {
            $query->where('project_id', $projectId);
        }

        if (($term = trim((string) $request->input('q'))) !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('code', 'like', '%' . $term . '%')
                    ->orWhere('name', 'like', '%' . $term . '%')
                    ->orWhere('description', 'like', '%' . $term . '%');
            });
        }

        return $this->listSuccessResponse($query->paginate($perPage), 'BOQs retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('create', Boq::class);

        $validator = Validator::make($request->all(), [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('boqs', 'code')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $boq = Boq::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => $request->string('project_id')->value(),
            'code' => strtoupper((string) $request->string('code')),
            'name' => $request->string('name')->value(),
            'description' => $request->input('description'),
        ]);

        return $this->successResponse($boq->load('project:id,tenant_id,name,code'), 'BOQ created successfully', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $boq = $this->findBoqOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('BOQ not found');
        }

        $this->authorize('view', $boq);

        return $this->successResponse(
            $boq->load([
                'project:id,tenant_id,name,code',
                'lineItems:id,tenant_id,boq_id,component_id,code,name,description,quantity,unit',
            ]),
            'BOQ retrieved successfully'
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $boq = $this->findBoqOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('BOQ not found');
        }

        $this->authorize('update', $boq);

        $validator = Validator::make($request->all(), [
            'project_id' => [
                'sometimes',
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('boqs', 'code')->where('tenant_id', $tenantId)->ignore($boq->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $payload = $request->only(['project_id', 'code', 'name', 'description']);
        if (array_key_exists('code', $payload) && is_string($payload['code'])) {
            $payload['code'] = strtoupper($payload['code']);
        }

        $boq->update($payload);

        return $this->successResponse($boq->fresh()->load('project:id,tenant_id,name,code'), 'BOQ updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $boq = $this->findBoqOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('BOQ not found');
        }

        $this->authorize('delete', $boq);

        $boq->delete();

        return $this->successResponse(null, 'BOQ deleted successfully');
    }
}
