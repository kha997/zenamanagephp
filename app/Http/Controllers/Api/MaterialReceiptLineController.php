<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MaterialReceiptLineController extends BaseApiController
{
    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? data_get(Auth::user(), 'tenant_id');

        return $tenantId ? (string) $tenantId : '';
    }

    private function findReceiptOrFail(string $tenantId, string $receiptId): MaterialReceipt
    {
        /** @var MaterialReceipt $receipt */
        $receipt = MaterialReceipt::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($receiptId)
            ->firstOrFail();

        return $receipt;
    }

    private function findLineOrFail(string $tenantId, string $receiptId, string $lineId): MaterialReceiptLine
    {
        /** @var MaterialReceiptLine $line */
        $line = MaterialReceiptLine::query()
            ->where('tenant_id', $tenantId)
            ->where('material_receipt_id', $receiptId)
            ->whereKey($lineId)
            ->with('material:id,tenant_id,code,name,unit')
            ->firstOrFail();

        return $line;
    }

    private function validatePayload(Request $request, string $tenantId)
    {
        return Validator::make($request->all(), [
            'material_id' => [
                'required',
                'string',
                Rule::exists('materials', 'id')->where('tenant_id', $tenantId),
            ],
            'quantity_received' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    public function index(Request $request, string $receipt): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $materialReceipt = $this->findReceiptOrFail($tenantId, $receipt);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material receipt not found');
        }

        $this->authorize('viewAny', [MaterialReceiptLine::class, $materialReceipt]);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $query = MaterialReceiptLine::query()
            ->where('tenant_id', $tenantId)
            ->where('material_receipt_id', (string) $materialReceipt->id)
            ->with('material:id,tenant_id,code,name,unit')
            ->orderBy('created_at');

        return $this->listSuccessResponse(
            $query->paginate($perPage)->through(fn (MaterialReceiptLine $line): array => $this->transformLine($line)),
            'Material receipt lines retrieved successfully'
        );
    }

    public function store(Request $request, string $receipt): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $materialReceipt = $this->findReceiptOrFail($tenantId, $receipt);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material receipt not found');
        }

        $this->authorize('create', [MaterialReceiptLine::class, $materialReceipt]);

        $validator = $this->validatePayload($request, $tenantId);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $line = MaterialReceiptLine::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $materialReceipt->project_id,
            'material_receipt_id' => (string) $materialReceipt->id,
            'material_id' => $request->string('material_id')->value(),
            'quantity_received' => (float) $request->input('quantity_received'),
            'unit_cost' => $request->input('unit_cost') !== null
                ? (float) $request->input('unit_cost')
                : null,
            'notes' => $request->input('notes'),
        ])->load('material:id,tenant_id,code,name,unit');

        return $this->successResponse(
            $this->transformLine($line),
            'Material receipt line created successfully',
            201
        );
    }

    public function show(Request $request, string $receipt, string $line): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $materialReceipt = $this->findReceiptOrFail($tenantId, $receipt);
            $receiptLine = $this->findLineOrFail($tenantId, (string) $materialReceipt->id, $line);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material receipt line not found');
        }

        $this->authorize('view', $receiptLine);

        return $this->successResponse(
            $this->transformLine($receiptLine),
            'Material receipt line retrieved successfully'
        );
    }

    public function update(Request $request, string $receipt, string $line): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $materialReceipt = $this->findReceiptOrFail($tenantId, $receipt);
            $receiptLine = $this->findLineOrFail($tenantId, (string) $materialReceipt->id, $line);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material receipt line not found');
        }

        $this->authorize('update', [$receiptLine, $materialReceipt]);

        $validator = Validator::make($request->all(), [
            'unit_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        if ($request->exists('unit_cost')) {
            $receiptLine->unit_cost = $request->input('unit_cost') !== null
                ? (float) $request->input('unit_cost')
                : null;
        }

        if ($request->exists('notes')) {
            $receiptLine->notes = $request->input('notes');
        }

        if ($request->exists('unit_cost') || $request->exists('notes')) {
            $receiptLine->save();
        }

        return $this->successResponse(
            $this->transformLine($receiptLine->fresh('material:id,tenant_id,code,name,unit')),
            'Material receipt line updated successfully'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transformLine(MaterialReceiptLine $line): array
    {
        $material = $line->relationLoaded('material') ? $line->material : null;

        return [
            'id' => (string) $line->id,
            'material_receipt_id' => (string) $line->material_receipt_id,
            'project_id' => (string) $line->project_id,
            'material_id' => (string) $line->material_id,
            'quantity_received' => (float) $line->quantity_received,
            'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
            'notes' => $line->notes,
            'material' => $material instanceof Material ? [
                'id' => (string) $material->id,
                'code' => $material->code,
                'name' => $material->name,
                'unit' => $material->unit,
            ] : null,
            'created_at' => optional($line->created_at)->toJSON(),
            'updated_at' => optional($line->updated_at)->toJSON(),
        ];
    }
}
