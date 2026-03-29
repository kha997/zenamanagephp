<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Material;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MaterialController extends BaseApiController
{
    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? data_get(Auth::user(), 'tenant_id');

        return $tenantId ? (string) $tenantId : '';
    }

    private function findMaterialOrFail(string $tenantId, string $materialId): Material
    {
        /** @var Material $material */
        $material = Material::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($materialId)
            ->firstOrFail();

        return $material;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('viewAny', Material::class);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $query = Material::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name');

        if (($term = trim((string) $request->input('q'))) !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('code', 'like', '%' . $term . '%')
                    ->orWhere('name', 'like', '%' . $term . '%')
                    ->orWhere('category', 'like', '%' . $term . '%');
            });
        }

        return $this->listSuccessResponse($query->paginate($perPage), 'Materials retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('create', Material::class);

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:100', Rule::unique('materials', 'code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $material = Material::query()->create([
            'tenant_id' => $tenantId,
            'code' => strtoupper((string) $request->string('code')),
            'name' => $request->string('name')->value(),
            'category' => $request->input('category'),
            'unit' => $request->input('unit'),
            'description' => $request->input('description'),
            'is_active' => (bool) $request->input('is_active', true),
        ]);

        return $this->successResponse($material, 'Material created successfully', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $material = $this->findMaterialOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material not found');
        }

        $this->authorize('view', $material);

        return $this->successResponse($material, 'Material retrieved successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $material = $this->findMaterialOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material not found');
        }

        $this->authorize('update', $material);

        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('materials', 'code')->where('tenant_id', $tenantId)->ignore($material->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $payload = $request->only(['code', 'name', 'category', 'unit', 'description', 'is_active']);
        if (array_key_exists('code', $payload) && is_string($payload['code'])) {
            $payload['code'] = strtoupper($payload['code']);
        }

        $material->update($payload);

        return $this->successResponse($material->fresh(), 'Material updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $material = $this->findMaterialOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material not found');
        }

        $this->authorize('delete', $material);

        $material->delete();

        return $this->successResponse(null, 'Material deleted successfully');
    }
}
