<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\DesignItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * DesignItem — công việc thiết kế qua vòng duyệt nội bộ và phản hồi khách hàng.
 * Spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 1).
 */
class DesignItemController extends BaseApiController
{
    use ZenaContractResponseTrait;

    /** @var list<string> */
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'project_id',
        'work_instance_step_id',
        'name',
        'item_type',
        'review_status',
        'assigned_to',
        'due_to_client_at',
        'client_feedback_notes',
        'approval_evidence',
        'created_by',
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
        return DesignItem::query()->forTenant($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DesignItem $item): array
    {
        return Arr::only($item->attributesToArray(), self::RESPONSE_FIELDS);
    }

    private function rules(string $tenantId): array
    {
        return [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'work_instance_step_id' => [
                'nullable',
                'string',
                Rule::exists('work_instance_steps', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['nullable', Rule::in(DesignItem::VALID_TYPES)],
            'assigned_to' => [
                'nullable',
                'string',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'due_to_client_at' => ['nullable', 'date'],
        ];
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

        $this->authorize('viewAny', DesignItem::class);

        $query = $this->scopedQuery($tenantId);

        if ($request->filled('project_id')) {
            $query->where('project_id', (string) $request->input('project_id'));
        }

        if ($request->filled('review_status')) {
            $query->where('review_status', (string) $request->input('review_status'));
        }

        $items = $query
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (DesignItem $item): array => $this->serialize($item))
            ->values();

        return $this->zenaSuccessResponse($items, 'Design items retrieved successfully');
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

        $this->authorize('create', DesignItem::class);

        $validator = Validator::make($request->all(), $this->rules($tenantId));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $item = DesignItem::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $request->input('project_id'),
            'work_instance_step_id' => $request->input('work_instance_step_id'),
            'name' => (string) $request->input('name'),
            'item_type' => (string) $request->input('item_type', DesignItem::TYPE_OTHER),
            'review_status' => DesignItem::STATUS_DRAFT,
            'assigned_to' => $request->input('assigned_to'),
            'due_to_client_at' => $request->input('due_to_client_at'),
            'created_by' => (string) $user->id,
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($item->fresh() ?? $item),
            'Design item created successfully',
            201
        );
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

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('view', $item);

        return $this->zenaSuccessResponse($this->serialize($item), 'Design item retrieved successfully');
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

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('update', $item);

        $rules = $this->rules($tenantId);
        $rules['project_id'] = ['sometimes'] + $rules['project_id'];
        $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // review_status is deliberately excluded here — it is only ever changed via updateStatus(),
        // which enforces the transition graph and its side-effect rules. Silently ignore it if sent.
        $item->fill($request->only([
            'project_id', 'work_instance_step_id', 'name', 'item_type', 'assigned_to', 'due_to_client_at',
        ]));
        $item->save();

        return $this->zenaSuccessResponse($this->serialize($item->fresh() ?? $item), 'Design item updated successfully');
    }
}
