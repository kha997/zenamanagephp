<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\Account;
use App\Models\EventRecord;
use App\Models\Lead;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Lead Inbox — capture nhanh và convert thành Opportunity (spec crm-zena).
 */
class LeadController extends BaseApiController
{
    use ZenaContractResponseTrait;

    /** @var list<string> */
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'contact_hint',
        'project_description',
        'source',
        'status',
        'converted_opportunity_id',
        'notes',
        'captured_by',
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
        return Lead::query()->forTenant($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Lead $lead): array
    {
        return Arr::only($lead->attributesToArray(), self::RESPONSE_FIELDS);
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

        $this->authorize('viewAny', Lead::class);

        $query = $this->scopedQuery($tenantId);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('source')) {
            $query->where('source', (string) $request->input('source'));
        }

        $leads = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Lead $lead): array => $this->serialize($lead))
            ->values();

        return $this->zenaSuccessResponse($leads, 'Leads retrieved successfully');
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

        $this->authorize('create', Lead::class);

        $validator = Validator::make($request->all(), [
            'contact_hint' => ['required', 'string', 'max:255'],
            'project_description' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', Rule::in(Lead::VALID_SOURCES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $lead = Lead::query()->create([
            'tenant_id' => $tenantId,
            'contact_hint' => (string) $request->input('contact_hint'),
            'project_description' => $request->input('project_description'),
            'source' => (string) $request->input('source', 'other'),
            'status' => Lead::STATUS_NEW,
            'notes' => $request->input('notes'),
            'captured_by' => (string) $user->id,
        ]);

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'lead',
            'aggregate_id' => (string) $lead->id,
            'event_key' => 'crm.lead.captured',
            'actor_user_id' => (string) $user->id,
            'payload' => ['contact_hint' => $lead->contact_hint, 'source' => $lead->source],
            'occurred_at' => now(),
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($lead->fresh() ?? $lead),
            'Lead captured successfully',
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

        $lead = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$lead instanceof Lead) {
            return $this->notFound('Lead not found');
        }

        $this->authorize('update', $lead);

        if ((string) $lead->status !== Lead::STATUS_NEW) {
            return $this->validationError([
                'status' => ['Only new leads can be edited.'],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'contact_hint' => ['required', 'string', 'max:255'],
            'project_description' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', Rule::in(Lead::VALID_SOURCES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $lead->contact_hint = (string) $request->input('contact_hint');
        $lead->project_description = $request->input('project_description');
        $lead->source = (string) $request->input('source', $lead->source);
        $lead->notes = $request->input('notes');
        $lead->save();

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'lead',
            'aggregate_id' => (string) $lead->id,
            'event_key' => 'crm.lead.updated',
            'actor_user_id' => (string) Auth::id(),
            'payload' => ['contact_hint' => $lead->contact_hint, 'source' => $lead->source],
            'occurred_at' => now(),
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($lead->fresh() ?? $lead),
            'Lead updated successfully'
        );
    }

    public function discard(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $lead = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$lead instanceof Lead) {
            return $this->notFound('Lead not found');
        }

        $this->authorize('update', $lead);

        if ((string) $lead->status !== Lead::STATUS_NEW) {
            return $this->validationError([
                'status' => ['Only new leads can be discarded.'],
            ]);
        }

        $lead->status = Lead::STATUS_DISCARDED;
        $lead->save();

        return $this->zenaSuccessResponse(
            $this->serialize($lead->fresh() ?? $lead),
            'Lead discarded successfully'
        );
    }

    /**
     * Convert lead thô → Account (tạo mới hoặc gắn sẵn) + Opportunity.
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

        $lead = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$lead instanceof Lead) {
            return $this->notFound('Lead not found');
        }

        $this->authorize('convert', $lead);

        if ((string) $lead->status !== Lead::STATUS_NEW) {
            return $this->validationError([
                'status' => ['Only new leads can be converted.'],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'account_id' => [
                'nullable',
                'string',
                Rule::exists('accounts', 'id')->where('tenant_id', $tenantId),
            ],
            'account_name' => ['required_without:account_id', 'nullable', 'string', 'max:255'],
            'account_type' => ['nullable', Rule::in(Account::VALID_TYPES)],
            'opportunity_name' => ['required', 'string', 'max:255'],
            'service_category' => ['nullable', Rule::in(Opportunity::VALID_SERVICE_CATEGORIES)],
            'service_scope_summary' => ['nullable', 'string', 'max:2000'],
            'estimated_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        [$account, $opportunity] = DB::transaction(function () use ($lead, $request, $user, $tenantId): array {
            $account = $request->filled('account_id')
                ? Account::query()->forTenant($tenantId)->findOrFail((string) $request->input('account_id'))
                : Account::query()->create([
                    'tenant_id' => $tenantId,
                    'account_type' => (string) $request->input('account_type', Account::TYPE_INDIVIDUAL),
                    'display_name' => (string) $request->input('account_name'),
                    'source_summary' => (string) $lead->source,
                    'owner_id' => (string) $user->id,
                    'status' => Account::STATUS_ACTIVE,
                ]);

            $legacyCategory = $request->input('service_category');

            $opportunity = Opportunity::query()->create([
                'tenant_id' => $tenantId,
                'account_id' => (string) $account->id,
                'opportunity_name' => (string) $request->input('opportunity_name'),
                'service_category' => $legacyCategory,
                'service_scope_summary' => $request->input('service_scope_summary', $lead->project_description),
                'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
                'estimated_fee' => $request->input('estimated_fee'),
                'sales_owner_id' => (string) $user->id,
                'created_by' => (string) $user->id,
            ]);

            // GAP-048 §4 — identical shared-mapper synchronization to
            // OpportunityController::store(), same atomic operation.
            $mappedLine = \App\Support\LegacyServiceCategoryMapper::mapToServiceLine($legacyCategory);
            if ($mappedLine !== null) {
                $opportunity->serviceLines()->create([
                    'service_line' => $mappedLine,
                    'provenance' => \App\Support\ServiceLineProvenance::INFERRED,
                    'source' => 'writer:lead_convert',
                ]);
            }

            $lead->status = Lead::STATUS_CONVERTED;
            $lead->converted_opportunity_id = (string) $opportunity->id;
            $lead->save();

            return [$account, $opportunity];
        });

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'lead',
            'aggregate_id' => (string) $lead->id,
            'event_key' => 'crm.lead.converted',
            'actor_user_id' => (string) $user->id,
            'payload' => [
                'account_id' => (string) $account->id,
                'opportunity_id' => (string) $opportunity->id,
            ],
            'occurred_at' => now(),
        ]);

        return $this->zenaSuccessResponse(
            [
                'lead' => $this->serialize($lead->fresh() ?? $lead),
                'account' => ['id' => (string) $account->id, 'display_name' => (string) $account->display_name],
                'opportunity' => ['id' => (string) $opportunity->id, 'opportunity_name' => (string) $opportunity->opportunity_name],
            ],
            'Lead converted successfully',
            201
        );
    }
}
