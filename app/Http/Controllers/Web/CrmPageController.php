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
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\User;
use App\Services\AiAssistService;
use App\Services\DocumentContext\DocumentContextRegistry;
use App\Services\ZenaBoqIntegrationService;
use App\Services\DeliverablePdfExportService;
use App\Services\DeliverableTemplateVersionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class CrmPageController extends Controller
{
    use DelegatesToApiControllers;

    /** Nhóm 14 stage thành cột hiển thị board. */
    private const BOARD_GROUPS = [
        'Mới' => [Opportunity::STAGE_NEW_LEAD, Opportunity::STAGE_QUALIFIED, Opportunity::STAGE_CONTACTED],
        'Tư vấn / Khảo sát' => [Opportunity::STAGE_BRIEF_DISCOVERY, Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED, Opportunity::STAGE_SCOPE_DEFINED],
        'Báo giá' => [Opportunity::STAGE_PROPOSAL_DRAFT, Opportunity::STAGE_PROPOSAL_SENT],
        'Đàm phán / Hợp đồng' => [Opportunity::STAGE_NEGOTIATION, Opportunity::STAGE_CONTRACTING],
        'Thắng' => [Opportunity::STAGE_WON],
        'Thua / Nurture' => [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID, Opportunity::STAGE_NURTURE],
    ];

    public function index(): View
    {
        $this->authorize('viewAny', Opportunity::class);

        $tenantId = (string) auth()->user()?->tenant_id;

        $opportunities = Opportunity::query()
            ->forTenant($tenantId)
            ->with('account:id,tenant_id,display_name', 'salesOwner:id,name')
            ->orderByDesc('updated_at')
            ->get();

        $board = [];
        foreach (self::BOARD_GROUPS as $label => $stages) {
            $items = $opportunities->whereIn('pipeline_stage', $stages)->values();
            $board[$label] = [
                'items' => $items,
                'count' => $items->count(),
                'total_fee' => (float) $items->sum('estimated_fee'),
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

        $tenantId = (string) auth()->user()?->tenant_id;

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
        $tenantId = (string) auth()->user()?->tenant_id;

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

    public function accounts(): View
    {
        $this->authorize('viewAny', Account::class);

        $tenantId = (string) auth()->user()?->tenant_id;

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
        $tenantId = (string) auth()->user()?->tenant_id;

        $opportunity = Opportunity::query()
            ->forTenant($tenantId)
            ->with('account:id,tenant_id,display_name,phone,email', 'salesOwner:id,name', 'technicalOwner:id,name', 'convertedProject:id,name,code')
            ->findOrFail($id);

        $this->authorize('view', $opportunity);

        return view('crm.opportunity-show', [
            'opportunity' => $opportunity,
            'boqIntegrationEnabled' => $boqService->isTenantAuthorized($tenantId),
            'boqCard' => $this->buildBoqCardViewModel($opportunity),
            'canManageBoq' => (bool) auth()->user()?->hasPermission('crm.manage'),
            'contractCard' => $this->buildContractCardViewModel($opportunity),
            'users' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
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

    public function updateStage(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'pipeline_stage' => ['required', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $response = $apiController->updateStage($this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null)), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, url()->previous(), 'Đã chuyển giai đoạn');
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
        $tenantId = (string) auth()->user()?->tenant_id;

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
        $tenantId = (string) auth()->user()?->tenant_id;

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
            'created_by' => (string) auth()->id(),
        ]);

        return redirect()->route('operator.crm.quotes.show', $quote->id);
    }

    public function saveQuoteLines(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

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
            }

            $totals = Quote::computeTotals($subtotal, (float) $quote->discount_percent, (float) $quote->vat_percent);
            $quote->update(array_merge(['subtotal' => $subtotal], $totals));
        });

        return back()->with('success', 'Đã lưu dòng báo giá.');
    }

    public function sendQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

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
            'actor_user_id' => auth()->id() ? (string) auth()->id() : null,
            'payload' => ['quote_number' => $quote->quote_number],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Đã gửi báo giá.');
    }

    public function acceptQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

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
                'actor_user_id' => auth()->id() ? (string) auth()->id() : null,
                'source' => 'operator',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã chấp nhận báo giá.');
    }

    public function rejectQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

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
                'actor_user_id' => auth()->id() ? (string) auth()->id() : null,
                'source' => 'operator',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã từ chối báo giá.');
    }

    public function reviseQuote(Request $request, string $id): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

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
                'created_by' => (string) auth()->id(),
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
        $tenantId = (string) auth()->user()?->tenant_id;

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
        $tenantId = (string) auth()->user()?->tenant_id;

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
        $tenantId = (string) auth()->user()?->tenant_id;

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
