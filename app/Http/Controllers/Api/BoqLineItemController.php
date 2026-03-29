<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Component;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BoqLineItemController extends BaseApiController
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

    private function findLineItemOrFail(string $tenantId, Boq $boq, string $lineItemId): BoqLineItem
    {
        /** @var BoqLineItem $lineItem */
        $lineItem = BoqLineItem::query()
            ->where('tenant_id', $tenantId)
            ->where('boq_id', $boq->id)
            ->whereKey($lineItemId)
            ->firstOrFail();

        return $lineItem;
    }

    private function validatePayload(Request $request, Boq $boq, bool $partial = false)
    {
        $rules = [
            'code' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:100'],
            'name' => [$partial ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => [$partial ? 'sometimes' : 'required', 'required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'component_id' => ['nullable', 'string'],
        ];

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $boq): void {
            $componentId = $request->input('component_id');
            if ($componentId === null || $componentId === '') {
                return;
            }

            $exists = Component::query()
                ->whereKey($componentId)
                ->where('tenant_id', $boq->tenant_id)
                ->where('project_id', $boq->project_id)
                ->exists();

            if (!$exists) {
                $validator->errors()->add('component_id', 'The selected component must belong to the same project as the parent BOQ.');
            }
        });

        return $validator;
    }

    public function index(Request $request, string $boq): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $parentBoq = $this->findBoqOrFail($tenantId, $boq);
        } catch (ModelNotFoundException) {
            return $this->notFound('BOQ not found');
        }

        $this->authorize('view', $parentBoq);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $query = BoqLineItem::query()
            ->where('tenant_id', $tenantId)
            ->where('boq_id', $parentBoq->id)
            ->with('component:id,tenant_id,project_id,name')
            ->orderBy('name');

        return $this->listSuccessResponse($query->paginate($perPage), 'BOQ line items retrieved successfully');
    }

    public function store(Request $request, string $boq): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $parentBoq = $this->findBoqOrFail($tenantId, $boq);
        } catch (ModelNotFoundException) {
            return $this->notFound('BOQ not found');
        }

        $this->authorize('update', $parentBoq);

        $validator = $this->validatePayload($request, $parentBoq);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $lineItem = BoqLineItem::query()->create([
            'tenant_id' => $tenantId,
            'boq_id' => (string) $parentBoq->id,
            'component_id' => $request->input('component_id'),
            'code' => $request->input('code'),
            'name' => $request->string('name')->value(),
            'description' => $request->input('description'),
            'quantity' => (float) $request->input('quantity'),
            'unit' => $request->input('unit'),
        ]);

        return $this->successResponse($lineItem->load('component:id,tenant_id,project_id,name'), 'BOQ line item created successfully', 201);
    }

    public function show(Request $request, string $boq, string $lineItem): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $parentBoq = $this->findBoqOrFail($tenantId, $boq);
            $item = $this->findLineItemOrFail($tenantId, $parentBoq, $lineItem);
        } catch (ModelNotFoundException) {
            return $this->notFound('BOQ line item not found');
        }

        $this->authorize('view', $parentBoq);

        return $this->successResponse($item->load('component:id,tenant_id,project_id,name'), 'BOQ line item retrieved successfully');
    }

    public function update(Request $request, string $boq, string $lineItem): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $parentBoq = $this->findBoqOrFail($tenantId, $boq);
            $item = $this->findLineItemOrFail($tenantId, $parentBoq, $lineItem);
        } catch (ModelNotFoundException) {
            return $this->notFound('BOQ line item not found');
        }

        $this->authorize('update', $parentBoq);

        $validator = $this->validatePayload($request, $parentBoq, true);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $item->update($request->only([
            'component_id',
            'code',
            'name',
            'description',
            'quantity',
            'unit',
        ]));

        return $this->successResponse($item->fresh()->load('component:id,tenant_id,project_id,name'), 'BOQ line item updated successfully');
    }

    public function destroy(Request $request, string $boq, string $lineItem): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $parentBoq = $this->findBoqOrFail($tenantId, $boq);
            $item = $this->findLineItemOrFail($tenantId, $parentBoq, $lineItem);
        } catch (ModelNotFoundException) {
            return $this->notFound('BOQ line item not found');
        }

        $this->authorize('delete', $parentBoq);

        $item->delete();

        return $this->successResponse(null, 'BOQ line item deleted successfully');
    }
}
