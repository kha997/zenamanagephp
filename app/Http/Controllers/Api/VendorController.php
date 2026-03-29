<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VendorController extends BaseApiController
{
    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? data_get(Auth::user(), 'tenant_id');

        return $tenantId ? (string) $tenantId : '';
    }

    private function findVendorOrFail(string $tenantId, string $vendorId): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = Vendor::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($vendorId)
            ->firstOrFail();

        return $vendor;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('viewAny', Vendor::class);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $query = Vendor::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name');

        if (($term = trim((string) $request->input('q'))) !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('code', 'like', '%' . $term . '%')
                    ->orWhere('name', 'like', '%' . $term . '%')
                    ->orWhere('contact_name', 'like', '%' . $term . '%')
                    ->orWhere('email', 'like', '%' . $term . '%');
            });
        }

        return $this->listSuccessResponse($query->paginate($perPage), 'Vendors retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('create', Vendor::class);

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:100', Rule::unique('vendors', 'code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenantId,
            'code' => strtoupper((string) $request->string('code')),
            'name' => $request->string('name')->value(),
            'contact_name' => $request->input('contact_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'is_active' => (bool) $request->input('is_active', true),
        ]);

        return $this->successResponse($vendor, 'Vendor created successfully', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $vendor = $this->findVendorOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Vendor not found');
        }

        $this->authorize('view', $vendor);

        return $this->successResponse($vendor, 'Vendor retrieved successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $vendor = $this->findVendorOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Vendor not found');
        }

        $this->authorize('update', $vendor);

        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('vendors', 'code')->where('tenant_id', $tenantId)->ignore($vendor->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $payload = $request->only(['code', 'name', 'contact_name', 'email', 'phone', 'address', 'is_active']);
        if (array_key_exists('code', $payload) && is_string($payload['code'])) {
            $payload['code'] = strtoupper($payload['code']);
        }

        $vendor->update($payload);

        return $this->successResponse($vendor->fresh(), 'Vendor updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $vendor = $this->findVendorOrFail($tenantId, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound('Vendor not found');
        }

        $this->authorize('delete', $vendor);

        $vendor->delete();

        return $this->successResponse(null, 'Vendor deleted successfully');
    }
}
