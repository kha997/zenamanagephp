<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptChecklist;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MaterialReceiptChecklistController extends BaseApiController
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

    private function findChecklistOrFail(string $tenantId, string $receiptId, string $checklistId): MaterialReceiptChecklist
    {
        /** @var MaterialReceiptChecklist $checklist */
        $checklist = MaterialReceiptChecklist::query()
            ->where('tenant_id', $tenantId)
            ->where('material_receipt_id', $receiptId)
            ->whereKey($checklistId)
            ->firstOrFail();

        return $checklist;
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

        $this->authorize('create', [MaterialReceiptChecklist::class, $materialReceipt]);

        $validator = Validator::make($request->all(), [
            'acceptance_summary' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_key' => ['required', 'string', 'max:100'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.result' => ['required', 'string', Rule::in(['pass', 'fail'])],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $checklist = MaterialReceiptChecklist::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $materialReceipt->project_id,
            'material_receipt_id' => (string) $materialReceipt->id,
            'acceptance_summary' => $request->input('acceptance_summary'),
            'items' => collect($request->input('items', []))
                ->map(static function (array $item): array {
                    return [
                        'item_key' => (string) $item['item_key'],
                        'label' => (string) $item['label'],
                        'result' => (string) $item['result'],
                        'notes' => array_key_exists('notes', $item) && $item['notes'] !== null
                            ? (string) $item['notes']
                            : null,
                    ];
                })
                ->values()
                ->all(),
        ]);

        return $this->successResponse(
            $this->transformChecklist($checklist),
            'Material receipt checklist created successfully',
            201
        );
    }

    public function show(Request $request, string $receipt, string $checklist): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $materialReceipt = $this->findReceiptOrFail($tenantId, $receipt);
            $receiptChecklist = $this->findChecklistOrFail($tenantId, (string) $materialReceipt->id, $checklist);
        } catch (ModelNotFoundException) {
            return $this->notFound('Material receipt checklist not found');
        }

        $this->authorize('view', $receiptChecklist);

        return $this->successResponse(
            $this->transformChecklist($receiptChecklist),
            'Material receipt checklist retrieved successfully'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function transformChecklist(MaterialReceiptChecklist $checklist): array
    {
        return [
            'id' => (string) $checklist->id,
            'material_receipt_id' => (string) $checklist->material_receipt_id,
            'project_id' => (string) $checklist->project_id,
            'acceptance_summary' => $checklist->acceptance_summary,
            'items' => collect($checklist->items ?? [])
                ->map(static function ($item): array {
                    if (!is_array($item)) {
                        return [];
                    }

                    return [
                        'item_key' => (string) ($item['item_key'] ?? ''),
                        'label' => (string) ($item['label'] ?? ''),
                        'result' => (string) ($item['result'] ?? ''),
                        'notes' => isset($item['notes']) ? (string) $item['notes'] : null,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
            'created_at' => optional($checklist->created_at)->toJSON(),
            'updated_at' => optional($checklist->updated_at)->toJSON(),
        ];
    }
}
