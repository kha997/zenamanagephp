<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Services\Crm\OpportunityServiceLineClassificationService;
use App\Services\Crm\OpportunityStageTransitionService;
use App\Services\ZenaBoqIntegrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        'external_boq_project_code',
        'external_quote_id',
        'external_quote_snapshot',
        'external_quote_synced_at',
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
        if (! Auth::check()) {
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
        if (! Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (! $opportunity instanceof Opportunity) {
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

        if (! $user) {
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

        $opportunity = DB::transaction(function () use ($request, $tenantId, $user): Opportunity {
            $legacyCategory = $request->input('service_category');

            $opportunity = Opportunity::query()->create([
                'tenant_id' => $tenantId,
                'account_id' => (string) $request->input('account_id'),
                'opportunity_name' => (string) $request->input('opportunity_name'),
                'service_category' => $legacyCategory,
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

            // GAP-048 §4 — legacy->canonical synchronization, shared mapper,
            // same atomic operation as the Opportunity creation itself.
            $mappedLine = \App\Support\LegacyServiceCategoryMapper::mapToServiceLine($legacyCategory);
            if ($mappedLine !== null) {
                $opportunity->serviceLines()->create([
                    'service_line' => $mappedLine,
                    'provenance' => \App\Support\ServiceLineProvenance::INFERRED,
                    'source' => 'writer:store',
                ]);
            }

            return $opportunity;
        });

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
        if (! Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (! $opportunity instanceof Opportunity) {
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

        $opportunityId = $opportunity->id;
        $categoryChanging = $request->has('service_category');
        $incomingCategory = $request->input('service_category');

        $opportunity = DB::transaction(function () use ($opportunityId, $request, $categoryChanging, $incomingCategory): Opportunity {
            // Canonical lock order: Opportunity row first (GAP-048 §19).
            $locked = Opportunity::query()
                ->whereKey($opportunityId)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->fill($request->only([
                'account_id', 'opportunity_name', 'service_category', 'service_scope_summary',
                'forecast_category', 'estimated_fee', 'estimated_project_value', 'probability',
                'expected_close_date', 'sales_owner_id', 'technical_owner_id', 'priority',
            ]));
            $locked->save();

            if ($categoryChanging) {
                // GAP-048 §4 rule C — mapper-owned INFERRED reconciliation
                // only. A CONFIRMED row is structurally never selected by
                // the `provenance = INFERRED` filter below, so rule §4.2
                // ("CONFIRMED is never overwritten/demoted/deleted by the
                // legacy mapper") holds by construction.
                $mappedLine = \App\Support\LegacyServiceCategoryMapper::mapToServiceLine($incomingCategory);

                $mapperOwnedRows = \App\Models\OpportunityServiceLine::query()
                    ->where('opportunity_id', $locked->id)
                    ->where('provenance', \App\Support\ServiceLineProvenance::INFERRED)
                    ->get();

                foreach ($mapperOwnedRows as $row) {
                    if ($row->service_line !== $mappedLine) {
                        $row->delete();
                    }
                }

                if ($mappedLine !== null) {
                    $exists = \App\Models\OpportunityServiceLine::query()
                        ->where('opportunity_id', $locked->id)
                        ->where('service_line', $mappedLine)
                        ->exists();

                    if (! $exists) {
                        $locked->serviceLines()->create([
                            'service_line' => $mappedLine,
                            'provenance' => \App\Support\ServiceLineProvenance::INFERRED,
                            'source' => 'writer:update',
                        ]);
                    }
                }
            }

            return $locked;
        });

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            'Opportunity updated successfully'
        );
    }

    /**
     * GAP-048 §3/§5 — explicit "Confirm classification" write. The desired
     * canonical Service-Line set is submitted whole; the atomic
     * reconciliation service handles CONFIRMED promotion, mapper-owned
     * INFERRED removal, the lifecycle invariant, and the audit trail.
     */
    public function updateServiceLines(Request $request, string $id, OpportunityServiceLineClassificationService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();
        if (! $opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('update', $opportunity);

        $validator = Validator::make($request->all(), [
            'service_lines' => ['present', 'array'],
            'service_lines.*' => [Rule::in(\App\Support\ServiceLine::VALUES)],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $opportunity = $service->reconcile($user, $opportunity, $request->input('service_lines', []));
        } catch (ValidationException $exception) {
            return $this->validationError($exception->errors());
        }

        return $this->zenaSuccessResponse($this->serialize($opportunity), 'Service-Line classification updated successfully');
    }

    public function updateStage(Request $request, string $id): JsonResponse
    {
        if (! Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (! $opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('update', $opportunity);

        $validator = Validator::make($request->all(), [
            'pipeline_stage' => ['required', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $opportunity = app(OpportunityStageTransitionService::class)->transition(
                $request->user(),
                $opportunity,
                (string) $request->input('pipeline_stage'),
                $request->input('lost_reason')
            );
        } catch (ValidationException $exception) {
            return $this->validationError($exception->errors());
        }

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity),
            'Opportunity stage updated successfully'
        );
    }

    /**
     * WON → tạo Project (nối phễu sale sang vận hành).
     */
    public function convert(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (! $opportunity instanceof Opportunity) {
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
                'code' => 'PRJ-'.Str::upper(Str::random(8)),
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

    public function createContract(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (! $opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $existingContract = Contract::query()
            ->where('tenant_id', $tenantId)
            ->where('source_opportunity_id', $opportunity->id)
            ->first();

        if ($existingContract instanceof Contract) {
            return $this->zenaSuccessResponse(
                [
                    'contract_id' => (string) $existingContract->id,
                    'project_id' => (string) $existingContract->project_id,
                ],
                'A contract already exists for this opportunity'
            );
        }

        if ((string) $opportunity->pipeline_stage !== Opportunity::STAGE_WON) {
            return $this->validationError([
                'pipeline_stage' => ['Only won opportunities can generate a contract.'],
            ]);
        }

        $snapshot = $opportunity->external_quote_snapshot ?? [];
        $nativeQuote = Quote::query()
            ->where('opportunity_id', (string) $opportunity->id)
            ->where('tenant_id', $tenantId)
            ->where('status', Quote::STATUS_ACCEPTED)
            ->first();

        $hasExternalAccepted = ($snapshot['status'] ?? null) === 'ACCEPTED';
        $hasNativeAccepted = $nativeQuote instanceof Quote;

        if (! $hasNativeAccepted && ! $hasExternalAccepted) {
            return $this->validationError([
                'quote' => ['Either a native accepted quote or an accepted external quote is required to generate a contract.'],
            ]);
        }

        $projectId = $opportunity->converted_project_id;

        if (! $projectId) {
            $this->authorize('convert', $opportunity);

            $project = DB::transaction(function () use ($opportunity, $user, $tenantId): Project {
                $project = Project::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => (string) $opportunity->opportunity_name,
                    'code' => 'PRJ-'.Str::upper(Str::random(8)),
                    'description' => $opportunity->service_scope_summary,
                    'status' => 'planning',
                    'progress' => 0,
                    'budget_total' => $opportunity->estimated_project_value ?? ($opportunity->estimated_fee ?? 0),
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

            $projectId = (string) $project->id;
        }

        $this->authorize('create', Contract::class);

        $account = $opportunity->account;
        $clientName = $account?->display_name ?? '';

        $contract = DB::transaction(function () use (
            $tenantId, $projectId, $opportunity, $user, $clientName,
            $hasNativeAccepted, $nativeQuote, $snapshot
        ): Contract {
            $contract = Contract::query()->create([
                'tenant_id' => $tenantId,
                'project_id' => $projectId,
                'source_opportunity_id' => (string) $opportunity->id,
                'source_quote_id' => $hasNativeAccepted ? (string) $nativeQuote->id : ($opportunity->external_quote_id ?? null),
                'source_quote_revision' => $hasNativeAccepted ? $nativeQuote->revision_no : ($snapshot['revision'] ?? null),
                'code' => $this->generateContractCode(),
                'title' => 'Hợp đồng dịch vụ - '.$clientName,
                'client_name' => $clientName,
                'total_value' => $hasNativeAccepted ? (float) ($nativeQuote->total ?: $nativeQuote->subtotal) : (float) ($snapshot['total'] ?? 0),
                'currency' => 'VND',
                'created_by' => (string) $user->id,
            ]);

            // When native quote: create BOQ + copy lines
            if ($hasNativeAccepted) {
                $boq = Boq::query()->create([
                    'tenant_id' => $tenantId,
                    'project_id' => $projectId,
                    'contract_id' => (string) $contract->id,
                    'code' => 'BOQ-'.$contract->code,
                    'name' => $clientName,
                ]);

                $quoteLines = QuoteLineItem::query()
                    ->where('quote_id', (string) $nativeQuote->id)
                    ->where('tenant_id', $tenantId)
                    ->orderBy('sort_order')
                    ->get();

                foreach ($quoteLines as $ql) {
                    BoqLineItem::query()->create([
                        'tenant_id' => $tenantId,
                        'boq_id' => (string) $boq->id,
                        'code' => $ql->code,
                        'name' => $ql->name,
                        'quantity' => $ql->quantity,
                        'unit' => $ql->unit,
                        'unit_price' => $ql->unit_price,
                    ]);
                }
            }

            return $contract;
        });

        $this->recordEvent($opportunity, 'crm.opportunity.contract_created', [
            'contract_id' => (string) $contract->id,
            'project_id' => $projectId,
            'total_value' => (float) $contract->total_value,
        ]);

        return $this->zenaSuccessResponse(
            [
                'contract_id' => (string) $contract->id,
                'project_id' => $projectId,
            ],
            'Contract created successfully',
            201
        );
    }

    private function generateContractCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = 'CTR-'.Str::upper(Str::random(8));
            if (! Contract::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'CTR-'.Str::upper((string) Str::ulid());
    }

    public function linkExternalBoqProject(Request $request, string $id, ZenaBoqIntegrationService $boqService): JsonResponse
    {
        if (! Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (! $boqService->isTenantAuthorized($tenantId)) {
            return $this->forbidden('This tenant is not authorized for the zena-boq-core integration');
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (! $opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('update', $opportunity);

        $validator = Validator::make($request->all(), [
            'external_boq_project_code' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $opportunity->external_boq_project_code = (string) $request->input('external_boq_project_code');
        $opportunity->save();

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            'Opportunity linked to zena-boq-core project successfully'
        );
    }

    public function syncExternalQuote(Request $request, string $id, ZenaBoqIntegrationService $boqService): JsonResponse
    {
        if (! Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (! $boqService->isTenantAuthorized($tenantId)) {
            return $this->forbidden('This tenant is not authorized for the zena-boq-core integration');
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (! $opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('update', $opportunity);

        if (! $opportunity->external_boq_project_code) {
            return $this->validationError([
                'external_boq_project_code' => ['Link this opportunity to a zena-boq-core project before syncing.'],
            ]);
        }

        $quote = $boqService->fetchLatestQuote((string) $opportunity->external_boq_project_code);

        if ($quote !== null) {
            $opportunity->external_quote_id = $quote['id'];
            $opportunity->external_quote_snapshot = [
                'revision' => $quote['revision'],
                'subtotal' => $quote['subtotal'],
                'vat_amount' => $quote['vat_amount'],
                'total' => $quote['total'],
                'status' => $quote['status'],
                'calibration' => $quote['calibration'],
                'issued_at' => $quote['issued_at'],
            ];
            $opportunity->external_quote_synced_at = now();
            $opportunity->save();

            $this->recordEvent($opportunity, 'crm.opportunity.boq_synced', [
                'external_quote_id' => $quote['id'],
                'total' => $quote['total'],
            ]);
        }
        // $quote === null: zena-boq-core unreachable or returned an error. Degrade gracefully —
        // keep whatever was already cached and do not overwrite external_quote_synced_at, so the
        // UI can keep showing "last synced at X" instead of silently going blank. Never a 500 here.

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            $quote !== null ? 'Quote synced successfully' : 'Could not reach zena-boq-core — showing last synced data'
        );
    }
}
