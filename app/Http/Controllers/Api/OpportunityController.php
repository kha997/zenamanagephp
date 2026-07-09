<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Opportunity — pipeline sale 14 stage (spec crm-zena).
 * WON + convert = điểm nối phễu sale → vận hành dự án.
 */
class OpportunityController extends BaseApiController
{
    use ZenaContractResponseTrait;

    /** @var list<string> */
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'account_id',
        'opportunity_name',
        'service_category',
        'service_scope_summary',
        'pipeline_stage',
        'forecast_category',
        'estimated_fee',
        'estimated_project_value',
        'probability',
        'expected_close_date',
        'sales_owner_id',
        'technical_owner_id',
        'priority',
        'lost_reason',
        'converted_project_id',
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
        return Opportunity::query()->forTenant($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Opportunity $opportunity): array
    {
        return Arr::only($opportunity->attributesToArray(), self::RESPONSE_FIELDS);
    }

    private function recordEvent(Opportunity $opportunity, string $eventKey, array $payload): void
    {
        EventRecord::query()->create([
            'tenant_id' => (string) $opportunity->tenant_id,
            'project_id' => $opportunity->converted_project_id,
            'aggregate_type' => 'opportunity',
            'aggregate_id' => (string) $opportunity->id,
            'event_key' => $eventKey,
            'actor_user_id' => Auth::id() ? (string) Auth::id() : null,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    private function rules(string $tenantId): array
    {
        return [
            'account_id' => [
                'required',
                'string',
                Rule::exists('accounts', 'id')->where('tenant_id', $tenantId),
            ],
            'opportunity_name' => ['required', 'string', 'max:255'],
            'service_category' => ['nullable', Rule::in(Opportunity::VALID_SERVICE_CATEGORIES)],
            'service_scope_summary' => ['nullable', 'string', 'max:5000'],
            'forecast_category' => ['nullable', Rule::in(Opportunity::VALID_FORECAST_CATEGORIES)],
            'estimated_fee' => ['nullable', 'numeric', 'min:0'],
            'estimated_project_value' => ['nullable', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'sales_owner_id' => [
                'nullable',
                'string',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'technical_owner_id' => [
                'nullable',
                'string',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'priority' => ['nullable', Rule::in(Opportunity::VALID_PRIORITIES)],
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

        $this->authorize('viewAny', Opportunity::class);

        $query = $this->scopedQuery($tenantId);

        if ($request->filled('pipeline_stage')) {
            $query->where('pipeline_stage', (string) $request->input('pipeline_stage'));
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', (string) $request->input('account_id'));
        }

        if ($request->filled('sales_owner_id')) {
            $query->where('sales_owner_id', (string) $request->input('sales_owner_id'));
        }

        $opportunities = $query
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Opportunity $opportunity): array => $this->serialize($opportunity))
            ->values();

        return $this->zenaSuccessResponse($opportunities, 'Opportunities retrieved successfully');
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

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('view', $opportunity);

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity),
            'Opportunity retrieved successfully'
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

        $this->authorize('create', Opportunity::class);

        $validator = Validator::make($request->all(), $this->rules($tenantId));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $opportunity = Opportunity::query()->create([
            'tenant_id' => $tenantId,
            'account_id' => (string) $request->input('account_id'),
            'opportunity_name' => (string) $request->input('opportunity_name'),
            'service_category' => (string) $request->input('service_category', 'architecture'),
            'service_scope_summary' => $request->input('service_scope_summary'),
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'forecast_category' => (string) $request->input('forecast_category', 'pipeline'),
            'estimated_fee' => $request->input('estimated_fee'),
            'estimated_project_value' => $request->input('estimated_project_value'),
            'probability' => $request->input('probability'),
            'expected_close_date' => $request->input('expected_close_date'),
            'sales_owner_id' => $request->input('sales_owner_id', (string) $user->id),
            'technical_owner_id' => $request->input('technical_owner_id'),
            'priority' => (string) $request->input('priority', 'medium'),
            'created_by' => (string) $user->id,
        ]);

        $this->recordEvent($opportunity, 'crm.opportunity.created', [
            'opportunity_name' => $opportunity->opportunity_name,
            'service_category' => $opportunity->service_category,
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            'Opportunity created successfully',
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

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('update', $opportunity);

        if ($opportunity->isTerminal()) {
            return $this->validationError([
                'pipeline_stage' => ['Won/lost/no-bid opportunities can no longer be edited.'],
            ]);
        }

        $rules = $this->rules($tenantId);
        $rules['account_id'] = ['sometimes'] + $rules['account_id'];
        $rules['opportunity_name'] = ['sometimes', 'required', 'string', 'max:255'];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $opportunity->fill($request->only([
            'account_id', 'opportunity_name', 'service_category', 'service_scope_summary',
            'forecast_category', 'estimated_fee', 'estimated_project_value', 'probability',
            'expected_close_date', 'sales_owner_id', 'technical_owner_id', 'priority',
        ]));
        $opportunity->save();

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            'Opportunity updated successfully'
        );
    }

    public function updateStage(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('update', $opportunity);

        $validator = Validator::make($request->all(), [
            'pipeline_stage' => ['required', Rule::in(Opportunity::VALID_STAGES)],
            'lost_reason' => [
                'required_if:pipeline_stage,' . Opportunity::STAGE_LOST,
                'nullable', 'string', 'max:500',
            ],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        if ($opportunity->isTerminal()) {
            return $this->validationError([
                'pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.'],
            ]);
        }

        $from = (string) $opportunity->pipeline_stage;
        $to = (string) $request->input('pipeline_stage');

        $opportunity->pipeline_stage = $to;
        $opportunity->lost_reason = $to === Opportunity::STAGE_LOST
            ? (string) $request->input('lost_reason')
            : null;

        if ($to === Opportunity::STAGE_WON) {
            $opportunity->forecast_category = 'closed_won';
        } elseif (in_array($to, [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID], true)) {
            $opportunity->forecast_category = 'closed_lost';
        }

        $opportunity->save();

        $this->recordEvent($opportunity, 'crm.opportunity.stage_changed', ['from' => $from, 'to' => $to]);

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            'Opportunity stage updated successfully'
        );
    }

    /**
     * WON → tạo Project (nối phễu sale sang vận hành).
     */
    public function convert(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('convert', $opportunity);

        if ((string) $opportunity->pipeline_stage !== Opportunity::STAGE_WON) {
            return $this->validationError([
                'pipeline_stage' => ['Only won opportunities can be converted to a project.'],
            ]);
        }

        if ($opportunity->converted_project_id) {
            return $this->validationError([
                'converted_project_id' => ['Opportunity has already been converted.'],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'project_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $project = DB::transaction(function () use ($opportunity, $request, $user, $tenantId): Project {
            $project = Project::query()->create([
                'tenant_id' => $tenantId,
                'name' => (string) $request->input('project_name', $opportunity->opportunity_name),
                'code' => 'PRJ-' . Str::upper(Str::random(8)),
                'description' => $opportunity->service_scope_summary,
                'status' => 'planning',
                'progress' => 0,
                'budget_total' => $opportunity->estimated_project_value ?? ($opportunity->estimated_fee ?? 0),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'pm_id' => $opportunity->technical_owner_id ?? $opportunity->sales_owner_id,
                'created_by' => (string) $user->id,
            ]);

            $opportunity->converted_project_id = (string) $project->id;
            $opportunity->save();

            return $project;
        });

        $this->recordEvent($opportunity, 'crm.opportunity.converted', [
            'project_id' => (string) $project->id,
            'project_name' => (string) $project->name,
        ]);

        return $this->zenaSuccessResponse(
            [
                'opportunity' => $this->serialize($opportunity->fresh() ?? $opportunity),
                'project' => [
                    'id' => (string) $project->id,
                    'name' => (string) $project->name,
                    'code' => (string) $project->code,
                ],
            ],
            'Opportunity converted to project successfully',
            201
        );
    }
}
