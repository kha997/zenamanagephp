<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\AccountController as ApiAccountController;
use App\Http\Controllers\Api\LeadController as ApiLeadController;
use App\Http\Controllers\Api\OpportunityController as ApiOpportunityController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Account;
use App\Models\DeliverableTemplate;
use App\Models\EventRecord;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityAppointment;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use App\Services\AiAssistService;
use App\Services\Crm\OpportunityStageTransitionService;
use App\Services\DocumentContext\DocumentContextRegistry;
use App\Services\ZenaBoqIntegrationService;
use App\Services\DeliverablePdfExportService;
use App\Services\DeliverableTemplateVersionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class CrmPageController extends Controller
{
    use DelegatesToApiControllers;

    /**
     * Nhóm 14 stage thành cột hiển thị board. Key là định danh ổn định dùng
     * trong data-board-group / JS — KHÔNG đổi key này, chỉ 'label' mới là
     * chuỗi hiển thị được phép đổi.
     */
    private const BOARD_GROUPS = [
        'new' => [
            'label' => 'Mới',
            'stages' => [Opportunity::STAGE_NEW_LEAD, Opportunity::STAGE_QUALIFIED, Opportunity::STAGE_CONTACTED],
            'default_entry_stage' => Opportunity::STAGE_NEW_LEAD,
        ],
        'consulting_survey' => [
            'label' => 'Tư vấn / Khảo sát',
            'stages' => [Opportunity::STAGE_BRIEF_DISCOVERY, Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED, Opportunity::STAGE_SCOPE_DEFINED],
            'default_entry_stage' => Opportunity::STAGE_BRIEF_DISCOVERY,
        ],
        'quote' => [
            'label' => 'Báo giá',
            'stages' => [Opportunity::STAGE_PROPOSAL_DRAFT, Opportunity::STAGE_PROPOSAL_SENT],
            'default_entry_stage' => Opportunity::STAGE_PROPOSAL_DRAFT,
        ],
        'negotiation_contract' => [
            'label' => 'Đàm phán / Hợp đồng',
            'stages' => [Opportunity::STAGE_NEGOTIATION, Opportunity::STAGE_CONTRACTING],
            'default_entry_stage' => Opportunity::STAGE_NEGOTIATION,
        ],
        'won' => [
            'label' => 'Thắng',
            'stages' => [Opportunity::STAGE_WON],
            'default_entry_stage' => Opportunity::STAGE_WON,
        ],
        'lost_nurture' => [
            'label' => 'Thua / Nurture',
            'stages' => [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID, Opportunity::STAGE_NURTURE],
            'default_entry_stage' => null,
            'requires_choice' => true,
            'choice_options' => [
                ['stage' => Opportunity::STAGE_LOST, 'label' => 'Thua', 'requires_reason' => true, 'terminal' => true],
                ['stage' => Opportunity::STAGE_NO_BID, 'label' => 'Không tham gia', 'requires_reason' => false, 'terminal' => true],
                ['stage' => Opportunity::STAGE_NURTURE, 'label' => 'Nuôi dưỡng', 'requires_reason' => false, 'terminal' => false],
            ],
        ],
    ];

    private function tenantId(): string
    {
        return (string) Auth::user()?->tenant_id;
    }

    private function actorUserId(): string
    {
        return (string) Auth::id();
    }

    public function index(): View
    {
        $this->authorize('viewAny', Opportunity::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        $opportunities = Opportunity::query()
            ->forTenant($tenantId)
            ->with('account:id,tenant_id,display_name', 'salesOwner:id,name')
            ->orderByDesc('updated_at')
            ->get();

        $board = [];
        foreach (self::BOARD_GROUPS as $groupKey => $group) {
            $items = $opportunities->whereIn('pipeline_stage', $group['stages'])->values();
            $board[$groupKey] = [
                'label' => $group['label'],
                'items' => $items,
                'count' => $items->count(),
                'total_fee' => (float) $items->sum('estimated_fee'),
                'default_entry_stage' => $group['default_entry_stage'],
                'requires_choice' => $group['requires_choice'] ?? false,
                'choice_options' => $group['choice_options'] ?? null,
            ];
        }

        return view('crm.index', [
            'board' => $board,
            'newLeadCount' => Lead::query()->forTenant($tenantId)->where('status', Lead::STATUS_NEW)->count(),
        ]);
    }

    public function leads(): View
    {
        $this->authorize('viewAny', Lead::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        return view('crm.leads', [
            'leads' => Lead::query()
                ->forTenant($tenantId)
                ->with('capturedBy:id,name')
                ->orderByDesc('created_at')
                ->get(),
            'accounts' => Account::query()
                ->forTenant($tenantId)
                ->orderBy('display_name')
                ->get(['id', 'tenant_id', 'display_name']),
        ]);
    }

    public function storeLead(Request $request, ApiLeadController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'contact_hint' => ['required', 'string', 'max:255'],
            'project_description' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $response = $apiController->store($this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null)));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.leads'), 'Đã ghi nhận lead');
    }

    public function updateLead(Request $request, string $id, ApiLeadController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'contact_hint' => ['required', 'string', 'max:255'],
            'project_description' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $response = $apiController->update($this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null)), $id);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.leads'), 'Đã cập nhật lead');
    }

    public function convertLead(Request $request, string $id, ApiLeadController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['nullable', 'string'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'opportunity_name' => ['required', 'string', 'max:255'],
            'service_category' => ['nullable', 'string'],
            'service_scope_summary' => ['nullable', 'string', 'max:2000'],
            'estimated_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $response = $apiController->convert($this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null && $value !== '')), $id);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.index'), 'Đã chuyển lead thành cơ hội');
    }

    public function discardLead(Request $request, string $id, ApiLeadController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->discard($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.leads'), 'Đã loại lead');
    }

    public function suggestLeadConversion(Request $request, string $id, AiAssistService $aiAssistService): JsonResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $lead = Lead::query()->forTenant($tenantId)->whereKey($id)->first();

        if (!$lead instanceof Lead) {
            return response()->json(['success' => false, 'message' => 'Lead not found'], 404);
        }

        $suggestion = $aiAssistService->suggestLeadConversion((string) $lead->project_description);

        if ($suggestion === null) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo gợi ý lúc này.',
            ], 503);
        }

        return response()->json(['success' => true, 'data' => $suggestion]);
    }

    /**
     * Build the anonymized context sent to the AI for an opportunity summary.
     * STRICT WHITELIST (spec: 2026-07-19-goal8-opportunity-ai-summary-design.md, "Data policy"):
     * never include opportunity_name, appointment location, or any account identity field.
     * tests/Unit/OpportunitySummaryContextTest.php asserts the exact key set.
     *
     * @return array{opportunity: array<string, mixed>, lead_origin: array<string, mixed>|null, appointments: list<array<string, mixed>>, quotes: list<array<string, mixed>>}
     */
    public function buildOpportunitySummaryContext(\App\Models\Opportunity $opportunity): array
    {
        $tenantId = (string) $opportunity->tenant_id;

        $lead = \App\Models\Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('converted_opportunity_id', (string) $opportunity->id)
            ->first();

        $appointments = \App\Models\OpportunityAppointment::query()
            ->where('tenant_id', $tenantId)
            ->where('opportunity_id', (string) $opportunity->id)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (\App\Models\OpportunityAppointment $a) => [
                'type' => $a->type,
                'scheduled_at' => $a->scheduled_at?->toDateTimeString(),
                'status' => $a->status,
                'outcome_notes' => $a->outcome_notes,
            ])
            ->values()
            ->all();

        $quotes = \App\Models\Quote::query()
            ->where('tenant_id', $tenantId)
            ->where('opportunity_id', (string) $opportunity->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (\App\Models\Quote $q) => [
                'quote_number' => $q->quote_number,
                'revision_no' => $q->revision_no,
                'status' => $q->status,
                'total' => $q->total,
                'sent_at' => $q->sent_at?->toDateTimeString(),
                'decided_at' => $q->decided_at?->toDateTimeString(),
                'valid_until' => $q->valid_until?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'opportunity' => [
                'service_category' => $opportunity->service_category,
                'service_scope_summary' => $opportunity->service_scope_summary,
                'pipeline_stage' => $opportunity->pipeline_stage,
                'forecast_category' => $opportunity->forecast_category,
                'estimated_fee' => $opportunity->estimated_fee,
                'estimated_project_value' => $opportunity->estimated_project_value,
                'probability' => $opportunity->probability,
                'expected_close_date' => $opportunity->expected_close_date,
                'priority' => $opportunity->priority,
                'lost_reason' => $opportunity->lost_reason,
                'created_at' => $opportunity->created_at?->toDateTimeString(),
            ],
            'lead_origin' => $lead === null ? null : [
                'project_description' => $lead->project_description,
                'created_at' => $lead->created_at?->toDateTimeString(),
            ],
            'appointments' => $appointments,
            'quotes' => $quotes,
        ];
    }

    public function summarizeOpportunity(Request $request, string $id, AiAssistService $aiAssistService): JsonResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $opportunity = \App\Models\Opportunity::query()->forTenant($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof \App\Models\Opportunity) {
            return response()->json(['success' => false, 'message' => 'Opportunity not found'], 404);
        }

        $summary = $aiAssistService->summarizeOpportunity(
            $this->buildOpportunitySummaryContext($opportunity)
        );

        if ($summary === null) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo tóm tắt lúc này.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary['summary'],
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function accounts(): View
    {
        $this->authorize('viewAny', Account::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        return view('crm.accounts', [
            'accounts' => Account::query()
                ->forTenant($tenantId)
                ->withCount('opportunities')
                ->orderBy('display_name')
                ->get(),
        ]);
    }

    public function storeAccount(Request $request, ApiAccountController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'account_type' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'province_or_city' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $response = $apiController->store($this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null)));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.accounts'), 'Đã tạo khách hàng');
    }

    public function showOpportunity(string $id, ZenaBoqIntegrationService $boqService): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $opportunity = Opportunity::query()
            ->forTenant($tenantId)
            ->with('account:id,tenant_id,display_name,phone,email', 'salesOwner:id,name', 'technicalOwner:id,name', 'convertedProject:id,name,code')
            ->findOrFail($id);

        $this->authorize('view', $opportunity);

        return view('crm.opportunity-show', [
            'opportunity' => $opportunity,
            'boqIntegrationEnabled' => $boqService->isTenantAuthorized($tenantId),
            'boqCard' => $this->buildBoqCardViewModel($opportunity),
            'canManageBoq' => (bool) Auth::user()?->hasPermission('crm.manage'),
            'contractCard' => $this->buildContractCardViewModel($opportunity),
            'users' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'appointments' => OpportunityAppointment::query()
                ->where('tenant_id', $tenantId)
                ->where('opportunity_id', $id)
                ->with('assignee:id,name')
                ->orderByDesc('scheduled_at')
                ->get(),
            'events' => \App\Models\EventRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('aggregate_type', 'opportunity')
                ->where('aggregate_id', $id)
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * @return array{project_code: string, subtotal: ?float, vat_amount: ?float, total: ?float, status: ?string, calibration: ?string, synced_at: ?\Illuminate\Support\Carbon, is_stale: bool, external_url: ?string}|null
     */
    private function buildBoqCardViewModel(Opportunity $opportunity): ?array
    {
        if (!$opportunity->external_boq_project_code) {
            return null;
        }

        $snapshot = $opportunity->external_quote_snapshot ?? [];
        $syncedAt = $opportunity->external_quote_synced_at;
        $baseUrl = rtrim((string) config('zena_boq.base_url'), '/');

        return [
            'project_code' => (string) $opportunity->external_boq_project_code,
            'subtotal' => isset($snapshot['subtotal']) ? (float) $snapshot['subtotal'] : null,
            'vat_amount' => isset($snapshot['vat_amount']) ? (float) $snapshot['vat_amount'] : null,
            'total' => isset($snapshot['total']) ? (float) $snapshot['total'] : null,
            'status' => $snapshot['status'] ?? null,
            'calibration' => $snapshot['calibration'] ?? null,
            'synced_at' => $syncedAt,
            'is_stale' => $syncedAt !== null && $syncedAt->diffInDays(now()) > 14,
            'external_url' => $opportunity->external_quote_id ? "{$baseUrl}/quotes/{$opportunity->external_quote_id}" : null,
        ];
    }

    /**
     * @return array{eligible: bool, contract: array{id: string, code: string}|null, has_drift: bool}|null
     */
    private function buildContractCardViewModel(Opportunity $opportunity): ?array
    {
        $snapshot = $opportunity->external_quote_snapshot ?? [];
        $eligible = (string) $opportunity->pipeline_stage === Opportunity::STAGE_WON
            && ($snapshot['status'] ?? null) === 'ACCEPTED';

        $existingContract = \App\Models\Contract::query()
            ->where('tenant_id', (string) $opportunity->tenant_id)
            ->where('source_opportunity_id', $opportunity->id)
            ->first();

        if (!$eligible && !$existingContract instanceof \App\Models\Contract) {
            return null;
        }

        $hasDrift = false;
        $contractData = null;

        if ($existingContract instanceof \App\Models\Contract) {
            $hasDrift = $existingContract->source_quote_id !== $opportunity->external_quote_id
                || $existingContract->source_quote_revision !== ($snapshot['revision'] ?? null);

            $contractData = [
                'id' => (string) $existingContract->id,
                'code' => (string) $existingContract->code,
            ];
        }

        return [
            'eligible' => $eligible,
            'contract' => $contractData,
            'has_drift' => $hasDrift,
        ];
    }

    public function updateStage(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'pipeline_stage' => ['required', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $this->tenantId();
        $opportunity = Opportunity::query()->forTenant($tenantId)->findOrFail($id);

        try {
            $opportunity = app(OpportunityStageTransitionService::class)->transition(
                Auth::user(),
                $opportunity,
                (string) $validated['pipeline_stage'],
                $validated['lost_reason'] ?? null
            );
        } catch (AuthorizationException) {
            if ($request->wantsJson()) {
                throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
            }
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }
            return back()->withErrors($exception->errors())->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Đã cập nhật giai đoạn.',
                'data' => [
                    'id' => $opportunity->id,
                    'pipeline_stage' => $opportunity->pipeline_stage,
                    'is_terminal' => $opportunity->isTerminal(),
                ],
            ], 200);
        }

        return back()->with('success', 'Đã chuyển giai đoạn');
    }

    public function convertOpportunity(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        try {
            $response = $apiController->convert($this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null && $value !== '')), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.index'), 'Đã tạo dự án từ cơ hội');
    }

    public function linkBoqProject(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'external_boq_project_code' => ['required', 'string', 'max:100'],
        ]);

        try {
            $response = $apiController->linkExternalBoqProject($this->buildApiRequest($request, $validated), $id, app(\App\Services\ZenaBoqIntegrationService::class));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.opportunities.show', $id), 'Đã liên kết dự án zena-boq-core');
    }

    public function syncBoqQuote(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->syncExternalQuote($this->buildApiRequest($request), $id, app(\App\Services\ZenaBoqIntegrationService::class));
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.opportunities.show', $id), 'Đã đồng bộ báo giá');
    }

    public function createContract(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->createContract($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.opportunities.show', $id), 'Đã tạo hợp đồng');
    }

    // ── Native Quotes ────────────────────────────────────────────────

    public function showQuote(string $id): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $quote = Quote::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
            ->where('quotes.id', $id)
            ->where('quotes.tenant_id', $tenantId)
            ->select('quotes.*')
            ->firstOrFail();

        $this->authorize('view', $quote);

        $quoteTemplates = \App\Models\DeliverableTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('context', 'quote')
            ->with('latestPublishedVersion')
            ->get()
            ->filter(fn ($t) => $t->latestPublishedVersion !== null)
            ->values();

        return view('crm.quote-show', compact('quote', 'quoteTemplates'));
    }

    public function storeQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->tenantId();

        $opp = Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$opp instanceof Opportunity) {
            return back()->with('error', 'Không tìm thấy cơ hội.');
        }

        $quote = Quote::query()->create([
            'tenant_id' => $tenantId,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => Quote::nextNumber($tenantId),
            'revision_no' => Quote::nextRevision((string) $opp->id),
            'status' => Quote::STATUS_DRAFT,
            'created_by' => $this->actorUserId(),
        ]);

        return redirect()->route('operator.crm.quotes.show', $quote->id);
    }

    public function storeAppointment(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $actorUserId = $this->actorUserId();

        $opportunity = Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', OpportunityAppointment::VALID_TYPES)],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string'],
        ]);

        if (($validated['assigned_to'] ?? null) !== null) {
            $assigneeExists = User::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($validated['assigned_to'])
                ->exists();

            if (!$assigneeExists) {
                return back()->withInput()->withErrors(['assigned_to' => 'Người phụ trách không hợp lệ.']);
            }
        }

        $appointment = OpportunityAppointment::query()->create([
            'tenant_id' => $tenantId,
            'opportunity_id' => (string) $opportunity->getKey(),
            'type' => $validated['type'],
            'scheduled_at' => $validated['scheduled_at'],
            'location' => $validated['location'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'status' => OpportunityAppointment::STATUS_SCHEDULED,
            'created_by' => $actorUserId,
        ]);

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'opportunity_appointment',
            'aggregate_id' => (string) $appointment->id,
            'event_key' => 'opportunity_appointment.scheduled',
            'actor_user_id' => $actorUserId,
            'payload' => [
                'opportunity_id' => (string) $opportunity->getKey(),
                'type' => $appointment->type,
                'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
            ],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Đã đặt lịch hẹn.');
    }

    public function completeAppointment(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $actorUserId = $this->actorUserId();

        $validated = $request->validate([
            'outcome_notes' => ['required', 'string'],
        ]);

        $appointment = OpportunityAppointment::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        if (!OpportunityAppointment::canTransition($appointment->status, OpportunityAppointment::STATUS_COMPLETED)) {
            return back()->with('error', 'Không thể chuyển trạng thái.');
        }

        $appointment->update([
            'status' => OpportunityAppointment::STATUS_COMPLETED,
            'outcome_notes' => $validated['outcome_notes'],
        ]);

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'opportunity_appointment',
            'aggregate_id' => (string) $appointment->id,
            'event_key' => 'opportunity_appointment.completed',
            'actor_user_id' => $actorUserId,
            'payload' => [
                'opportunity_id' => (string) $appointment->opportunity_id,
                'status' => OpportunityAppointment::STATUS_COMPLETED,
            ],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Đã hoàn thành lịch hẹn.');
    }

    public function cancelAppointment(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $actorUserId = $this->actorUserId();

        $validated = $request->validate([
            'outcome_notes' => ['nullable', 'string'],
        ]);

        $appointment = OpportunityAppointment::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        if (!OpportunityAppointment::canTransition($appointment->status, OpportunityAppointment::STATUS_CANCELLED)) {
            return back()->with('error', 'Không thể chuyển trạng thái.');
        }

        $appointment->update([
            'status' => OpportunityAppointment::STATUS_CANCELLED,
            'outcome_notes' => $validated['outcome_notes'] ?? null,
        ]);

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'opportunity_appointment',
            'aggregate_id' => (string) $appointment->id,
            'event_key' => 'opportunity_appointment.cancelled',
            'actor_user_id' => $actorUserId,
            'payload' => [
                'opportunity_id' => (string) $appointment->opportunity_id,
                'status' => OpportunityAppointment::STATUS_CANCELLED,
            ],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Đã hủy lịch hẹn.');
    }

    public function rescheduleAppointment(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $actorUserId = $this->actorUserId();

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $appointment = OpportunityAppointment::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        if (!OpportunityAppointment::canTransition($appointment->status, OpportunityAppointment::STATUS_RESCHEDULED)) {
            return back()->with('error', 'Không thể chuyển trạng thái.');
        }

        DB::transaction(function () use ($actorUserId, $appointment, $tenantId, $validated) {
            $replacement = OpportunityAppointment::query()->create([
                'tenant_id' => $tenantId,
                'opportunity_id' => (string) $appointment->opportunity_id,
                'type' => $appointment->type,
                'scheduled_at' => $validated['scheduled_at'],
                'location' => $appointment->location,
                'assigned_to' => $appointment->assigned_to,
                'status' => OpportunityAppointment::STATUS_SCHEDULED,
                'created_by' => $actorUserId,
            ]);

            $appointment->update([
                'status' => OpportunityAppointment::STATUS_RESCHEDULED,
            ]);

            EventRecord::query()->create([
                'tenant_id' => $tenantId,
                'aggregate_type' => 'opportunity_appointment',
                'aggregate_id' => (string) $appointment->id,
                'event_key' => 'opportunity_appointment.rescheduled',
                'actor_user_id' => $actorUserId,
                'payload' => [
                    'opportunity_id' => (string) $appointment->opportunity_id,
                    'replacement_appointment_id' => (string) $replacement->id,
                    'status' => OpportunityAppointment::STATUS_RESCHEDULED,
                ],
                'occurred_at' => now(),
            ]);
        });

        return back()->with('success', 'Đã dời lịch hẹn.');
    }

    public function saveQuoteLines(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $quote = Quote::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$quote instanceof Quote) {
            return back()->with('error', 'Không tìm thấy báo giá.');
        }

        if ($quote->status !== Quote::STATUS_DRAFT) {
            return back()->with('error', 'Chỉ có thể chỉnh sửa dòng khi ở trạng thái nháp.');
        }

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['required', 'string', 'max:30'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.price_note' => ['nullable', 'string', 'max:500'],
            'lines.*.code' => ['nullable', 'string', 'max:50'],
            'lines.*.benchmark_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Models\PriceReferenceEntry::VALID_BENCHMARK_TYPES)],
            'lines.*.evidence_note' => ['nullable', 'string', 'max:500'],
            'lines.*.evidence_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        DB::transaction(function () use ($quote, $validated, $tenantId) {
            QuoteLineItem::query()
                ->where('quote_id', $quote->id)
                ->where('tenant_id', $tenantId)
                ->delete();

            $subtotal = 0;

            foreach ($validated['lines'] as $index => $line) {
                $amount = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $subtotal += $amount;

                QuoteLineItem::query()->create([
                    'tenant_id' => $tenantId,
                    'quote_id' => (string) $quote->id,
                    'sort_order' => $index + 1,
                    'code' => $line['code'] ?? null,
                    'name' => $line['name'],
                    'unit' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'amount' => $amount,
                    'price_note' => $line['price_note'] ?? null,
                ]);

                if (!empty($line['benchmark_type']) && !empty($line['code'])) {
                    \App\Models\PriceReferenceEntry::create([
                        'tenant_id' => $tenantId,
                        'work_item_code' => $line['code'],
                        'work_item_name' => $line['name'],
                        'unit' => $line['unit'],
                        'unit_price' => $line['unit_price'],
                        'benchmark_type' => $line['benchmark_type'],
                        'evidence_note' => $line['evidence_note'] ?? null,
                        'evidenced_at' => $line['evidence_date'] ?? now()->toDateString(),
                        'created_by' => (string) Auth::id(),
                    ]);
                }
            }

            $totals = Quote::computeTotals($subtotal, (float) $quote->discount_percent, (float) $quote->vat_percent);
            $quote->update(array_merge(['subtotal' => $subtotal], $totals));
        });

        return back()->with('success', 'Đã lưu dòng báo giá.');
    }

    public function lookupPriceReference(Request $request): JsonResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;
        $code = (string) $request->query('code', '');
        $unit = (string) $request->query('unit', '');

        if ($code === '' || $unit === '') {
            return response()->json(['data' => null]);
        }

        $entry = \App\Models\PriceReferenceEntry::latestFor($tenantId, $code, $unit);

        if (!$entry) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->serializePriceReferenceEntry($entry)]);
    }

    public function priceReferenceHistory(Request $request): JsonResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;
        $code = (string) $request->query('code', '');
        $unit = (string) $request->query('unit', '');

        $entries = \App\Models\PriceReferenceEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('work_item_code', $code)
            ->where('unit', $unit)
            ->orderByDesc('evidenced_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (\App\Models\PriceReferenceEntry $entry) => $this->serializePriceReferenceEntry($entry))
            ->values();

        return response()->json(['data' => $entries]);
    }

    /** @return array{unit_price: float, benchmark_type: string, benchmark_type_label: string, evidence_note: string|null, evidenced_at: string} */
    private function serializePriceReferenceEntry(\App\Models\PriceReferenceEntry $entry): array
    {
        return [
            'unit_price' => $entry->unit_price,
            'benchmark_type' => $entry->benchmark_type,
            'benchmark_type_label' => \App\Models\PriceReferenceEntry::BENCHMARK_TYPE_LABELS[$entry->benchmark_type] ?? $entry->benchmark_type,
            'evidence_note' => $entry->evidence_note,
            'evidenced_at' => $entry->evidenced_at->format('Y-m-d'),
        ];
    }

    public function sendQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $quote = Quote::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$quote instanceof Quote) {
            return back()->with('error', 'Không tìm thấy báo giá.');
        }

        if (!Quote::canTransition($quote->status, Quote::STATUS_SENT)) {
            return back()->with('error', 'Không thể chuyển trạng thái.');
        }

        $hasLines = QuoteLineItem::query()
            ->where('quote_id', $quote->id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$hasLines) {
            return back()->with('error', 'Cần ít nhất một dòng để gửi báo giá.');
        }

        $subtotal = QuoteLineItem::query()
            ->where('quote_id', $quote->id)
            ->where('tenant_id', $tenantId)
            ->sum('amount');

        $totals = Quote::computeTotals($subtotal, (float) $quote->discount_percent, (float) $quote->vat_percent);
        $quote->update([
            'status' => Quote::STATUS_SENT,
            'sent_at' => now(),
            'subtotal' => $subtotal,
        ] + $totals);

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'quote',
            'aggregate_id' => (string) $quote->id,
            'event_key' => 'quote.sent',
            'actor_user_id' => Auth::id() ? (string) Auth::id() : null,
            'payload' => ['quote_number' => $quote->quote_number],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Đã gửi báo giá.');
    }

    public function acceptQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $quote = Quote::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$quote instanceof Quote) {
            return back()->with('error', 'Không tìm thấy báo giá.');
        }

        if (!Quote::canTransition($quote->status, Quote::STATUS_ACCEPTED)) {
            return back()->with('error', 'Không thể chuyển trạng thái.');
        }

        try {
            app(\App\Services\QuoteLifecycleService::class)->accept($quote, [
                'actor_user_id' => Auth::id() ? (string) Auth::id() : null,
                'source' => 'operator',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã chấp nhận báo giá.');
    }

    public function rejectQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $quote = Quote::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$quote instanceof Quote) {
            return back()->with('error', 'Không tìm thấy báo giá.');
        }

        if (!Quote::canTransition($quote->status, Quote::STATUS_REJECTED)) {
            return back()->with('error', 'Không thể chuyển trạng thái.');
        }

        try {
            app(\App\Services\QuoteLifecycleService::class)->reject($quote, [
                'actor_user_id' => Auth::id() ? (string) Auth::id() : null,
                'source' => 'operator',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã từ chối báo giá.');
    }

    public function reviseQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $original = Quote::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$original instanceof Quote) {
            return back()->with('error', 'Không tìm thấy báo giá.');
        }

        $newQuote = DB::transaction(function () use ($original, $tenantId) {
            $newQuote = Quote::query()->create([
                'tenant_id' => $tenantId,
                'opportunity_id' => $original->opportunity_id,
                'quote_number' => Quote::nextNumber($tenantId),
                'revision_no' => Quote::nextRevision($original->opportunity_id),
                'status' => Quote::STATUS_DRAFT,
                'notes' => $original->notes,
                'created_by' => (string) Auth::id(),
                'discount_percent' => $original->discount_percent,
                'vat_percent' => $original->vat_percent,
                'payment_terms' => $original->payment_terms,
            ]);

            $lines = QuoteLineItem::query()
                ->where('quote_id', $original->id)
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->get();

            foreach ($lines as $line) {
                QuoteLineItem::query()->create([
                    'tenant_id' => $tenantId,
                    'quote_id' => (string) $newQuote->id,
                    'sort_order' => $line->sort_order,
                    'code' => $line->code,
                    'name' => $line->name,
                    'unit' => $line->unit,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'amount' => $line->amount,
                    'price_note' => $line->price_note,
                ]);
            }

            $subtotal = (float) $lines->sum('amount');
            $totals = Quote::computeTotals($subtotal, (float) $newQuote->discount_percent, (float) $newQuote->vat_percent);
            $newQuote->update(array_merge(['subtotal' => $subtotal], $totals));

            return $newQuote;
        });

        return redirect()->route('operator.crm.quotes.show', $newQuote->id);
    }

    public function saveQuoteCommercial(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $quote = Quote::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$quote instanceof Quote) {
            return back()->with('error', 'Không tìm thấy báo giá.');
        }

        $this->authorize('update', $quote);

        $validated = $request->validate([
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valid_until' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $discountPercent = (float) ($validated['discount_percent'] ?? $quote->discount_percent);
        $vatPercent = (float) ($validated['vat_percent'] ?? $quote->vat_percent);
        $totals = Quote::computeTotals((float) $quote->subtotal, $discountPercent, $vatPercent);

        $quote->update(array_merge($validated, $totals));

        return back()->with('success', 'Đã lưu thông tin thương mại.');
    }

    public function quotePdf(string $id, DeliverablePdfExportService $pdfService): SymfonyResponse
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $quote = Quote::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->first();

        if (!$quote instanceof Quote) {
            abort(404);
        }

        $lines = $quote->lines()->get();
        $account = $quote->opportunity?->account;
        $opportunity = $quote->opportunity;

        $html = view('crm.quote-pdf', [
            'quote' => $quote,
            'lines' => $lines,
            'account' => $account,
            'opportunity' => $opportunity,
            'amountInWords' => \App\Support\VietnameseMoneyWords::toWords((float) $quote->total),
        ])->render();

        try {
            $pdfBytes = $pdfService->render($html);
        } catch (\App\Exceptions\DeliverablePdfExportUnavailableException) {
            return back()->with('error', 'Không thể tạo PDF báo giá vào lúc này.');
        }

        $filename = 'bao-gia-' . $quote->quote_number . '.pdf';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function renderQuoteDocument(string $id, string $template, DeliverableTemplateVersionService $versionService, DocumentContextRegistry $contextRegistry, DeliverablePdfExportService $pdfService): SymfonyResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = \Illuminate\Support\Facades\Auth::user();
        $tenantId = (string) $authUser->tenant_id;

        /** @var Quote $quote */
        $quote = Quote::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
            ->where('quotes.id', $id)
            ->where('quotes.tenant_id', $tenantId)
            ->select('quotes.*')
            ->firstOrFail();

        $tpl = DeliverableTemplate::query()->where('tenant_id', $tenantId)->where('context', 'quote')->findOrFail($template);

        $version = $tpl->latestPublishedVersion()->first();
        if ($version === null) {
            abort(404);
        }

        $html = (string) Storage::disk('local')->get($version->storage_path);
        $context = $contextRegistry->get('quote')->build($quote);
        $rendered = $versionService->renderHtml($html, $context);

        try {
            $pdfBytes = $pdfService->render($rendered);
        } catch (\App\Exceptions\DeliverablePdfExportUnavailableException) {
            return back()->with('error', 'Không thể tạo PDF vào lúc này.');
        }

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bao-gia-' . Str::slug($tpl->name) . '-' . $quote->quote_number . '.pdf"',
        ]);
    }
}
