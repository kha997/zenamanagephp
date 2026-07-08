<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\SiteDiary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiteDiaryController extends BaseApiController
{
    use ZenaContractResponseTrait;

    /** @var list<string> */
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'project_id',
        'diary_number',
        'diary_date',
        'weather',
        'temperature',
        'manpower_count',
        'work_performed',
        'equipment_used',
        'materials_delivered',
        'safety_notes',
        'visitors',
        'delays_issues',
        'status',
        'created_by',
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
        return SiteDiary::query()->forTenant($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SiteDiary $siteDiary): array
    {
        return Arr::only($siteDiary->attributesToArray(), self::RESPONSE_FIELDS);
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

        $this->authorize('viewAny', SiteDiary::class);

        $query = $this->scopedQuery($tenantId);

        if ($request->filled('project_id')) {
            $query->where('project_id', (string) $request->input('project_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('diary_date', '>=', (string) $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('diary_date', '<=', (string) $request->input('date_to'));
        }

        $diaries = $query
            ->orderByDesc('diary_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SiteDiary $siteDiary): array => $this->serialize($siteDiary))
            ->values();

        return $this->zenaSuccessResponse($diaries, 'Site diaries retrieved successfully');
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

        $siteDiary = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$siteDiary instanceof SiteDiary) {
            return $this->notFound('Site diary not found');
        }

        $this->authorize('view', $siteDiary);

        return $this->zenaSuccessResponse(
            $this->serialize($siteDiary),
            'Site diary retrieved successfully'
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

        $this->authorize('create', SiteDiary::class);

        $validator = Validator::make($request->all(), [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'diary_date' => ['required', 'date'],
            'weather' => ['nullable', 'string', 'max:100'],
            'temperature' => ['nullable', 'string', 'max:50'],
            'manpower_count' => ['nullable', 'integer', 'min:0'],
            'work_performed' => ['required', 'string'],
            'equipment_used' => ['nullable', 'string'],
            'materials_delivered' => ['nullable', 'string'],
            'safety_notes' => ['nullable', 'string'],
            'visitors' => ['nullable', 'string'],
            'delays_issues' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $duplicateExists = SiteDiary::query()
            ->where('project_id', (string) $request->input('project_id'))
            ->whereDate('diary_date', (string) $request->input('diary_date'))
            ->exists();

        if ($duplicateExists) {
            return $this->validationError([
                'diary_date' => ['A site diary already exists for this project on this date.'],
            ]);
        }

        try {
            $siteDiary = SiteDiary::query()->create([
                'tenant_id' => $tenantId,
                'project_id' => (string) $request->input('project_id'),
                'diary_number' => 'SD-' . Str::upper((string) Str::ulid()),
                'diary_date' => (string) $request->input('diary_date'),
                'weather' => $request->input('weather'),
                'temperature' => $request->input('temperature'),
                'manpower_count' => (int) $request->input('manpower_count', 0),
                'work_performed' => (string) $request->input('work_performed'),
                'equipment_used' => $request->input('equipment_used'),
                'materials_delivered' => $request->input('materials_delivered'),
                'safety_notes' => $request->input('safety_notes'),
                'visitors' => $request->input('visitors'),
                'delays_issues' => $request->input('delays_issues'),
                'status' => SiteDiary::STATUS_DRAFT,
                'created_by' => (string) $user->id,
            ]);
        } catch (\Illuminate\Database\QueryException $exception) {
            // Concurrent create raced past the pre-check; the DB unique
            // constraint (project_id, diary_date) is the source of truth.
            if ((string) $exception->getCode() === '23000') {
                return $this->validationError([
                    'diary_date' => ['A site diary already exists for this project on this date.'],
                ]);
            }

            throw $exception;
        }

        return $this->zenaSuccessResponse(
            $this->serialize($siteDiary->fresh() ?? $siteDiary),
            'Site diary created successfully',
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

        $siteDiary = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$siteDiary instanceof SiteDiary) {
            return $this->notFound('Site diary not found');
        }

        $this->authorize('update', $siteDiary);

        if ((string) $siteDiary->status !== SiteDiary::STATUS_DRAFT) {
            return $this->validationError([
                'status' => ['Only draft site diaries can be updated.'],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'weather' => ['nullable', 'string', 'max:100'],
            'temperature' => ['nullable', 'string', 'max:50'],
            'manpower_count' => ['nullable', 'integer', 'min:0'],
            'work_performed' => ['sometimes', 'required', 'string'],
            'equipment_used' => ['nullable', 'string'],
            'materials_delivered' => ['nullable', 'string'],
            'safety_notes' => ['nullable', 'string'],
            'visitors' => ['nullable', 'string'],
            'delays_issues' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $siteDiary->fill($request->only([
            'weather',
            'temperature',
            'manpower_count',
            'work_performed',
            'equipment_used',
            'materials_delivered',
            'safety_notes',
            'visitors',
            'delays_issues',
        ]));
        $siteDiary->save();

        return $this->zenaSuccessResponse(
            $this->serialize($siteDiary->fresh() ?? $siteDiary),
            'Site diary updated successfully'
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

        $siteDiary = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$siteDiary instanceof SiteDiary) {
            return $this->notFound('Site diary not found');
        }

        $this->authorize('submit', $siteDiary);

        if ((string) $siteDiary->status !== SiteDiary::STATUS_DRAFT) {
            return $this->validationError([
                'status' => ['Only draft site diaries can be submitted.'],
            ]);
        }

        $siteDiary->status = SiteDiary::STATUS_SUBMITTED;
        $siteDiary->save();

        return $this->zenaSuccessResponse(
            $this->serialize($siteDiary->fresh() ?? $siteDiary),
            'Site diary submitted successfully'
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

        $siteDiary = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$siteDiary instanceof SiteDiary) {
            return $this->notFound('Site diary not found');
        }

        $this->authorize('approve', $siteDiary);

        if ((string) $siteDiary->status !== SiteDiary::STATUS_SUBMITTED) {
            return $this->validationError([
                'status' => ['Only submitted site diaries can be approved.'],
            ]);
        }

        $siteDiary->status = SiteDiary::STATUS_APPROVED;
        $siteDiary->approved_by = (string) $user->id;
        $siteDiary->approved_at = now();
        $siteDiary->save();

        return $this->zenaSuccessResponse(
            $this->serialize($siteDiary->fresh() ?? $siteDiary),
            'Site diary approved successfully'
        );
    }
}
