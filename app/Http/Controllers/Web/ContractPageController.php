<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\ContractController as ApiContractController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Contract;
use App\Models\Project;
use App\Services\DeliverablePdfExportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ContractPageController extends Controller
{
    use DelegatesToApiControllers;

    private const ADVANCE_PAYMENT_NAME = 'Tạm ứng theo hợp đồng';

    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $query = Contract::query()
            ->where('tenant_id', $tenantId)
            ->with('project:id,tenant_id,name,code');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return view('contracts.index', [
            'contracts' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'currentStatus' => (string) $request->query('status', ''),
            'currentSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function create(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('contracts.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    public function store(Request $request, ApiContractController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'code' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'contract_type' => ['nullable', 'in:design,construction,other'],
            'status' => ['nullable', 'in:draft,active,closed,cancelled'],
            'currency' => ['nullable', 'string', 'size:3'],
            'total_value' => ['nullable', 'numeric', 'min:0'],
            'signed_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $projectId = (string) $validated['project_id'];
        unset($validated['project_id']);
        $validated = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated), $projectId);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        $payload = $response->getData(true);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $contractId = (string) data_get($payload, 'data.id');

            return redirect()
                ->route('operator.contracts.show', $contractId)
                ->with('success', 'Tạo hợp đồng thành công');
        }

        return $this->handleErrorResponse($response);
    }

    public function storeExpense(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'category' => ['required', \Illuminate\Validation\Rule::in(\App\Models\ContractExpense::VALID_CATEGORIES)],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $tenantId = (string) auth()->user()?->tenant_id;
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        \App\Models\ContractExpense::query()->create([
            'tenant_id' => $tenantId,
            'contract_id' => (string) $contract->id,
            'expense_date' => $validated['expense_date'],
            'amount' => (float) $validated['amount'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'recorded_by' => (string) auth()->id(),
        ]);

        return back()->with('success', 'Đã ghi khoản chi.');
    }

    public function deleteExpense(string $id, string $expense): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        \App\Models\ContractExpense::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->findOrFail($expense)
            ->delete();

        return back()->with('success', 'Đã xóa khoản chi.');
    }

    public function show(Request $request, string $id, ApiContractController $apiController): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $contract = Contract::query()
            ->where('tenant_id', $tenantId)
            ->with('project:id,tenant_id,name,code')
            ->findOrFail($id);

        $summary = null;
        $summaryUnavailableMessage = null;

        try {
            $summaryResponse = $apiController->costSummary(
                $this->buildApiRequest($request),
                (string) $contract->project_id,
                (string) $contract->id
            );

            if ($summaryResponse->getStatusCode() >= 200 && $summaryResponse->getStatusCode() < 300) {
                $summary = data_get($summaryResponse->getData(true), 'data.summary');
            } else {
                $summaryUnavailableMessage = 'Không thể tải tổng hợp chi phí hợp đồng vào lúc này.';
            }
        } catch (AuthorizationException|Throwable) {
            $summaryUnavailableMessage = 'Không thể tải tổng hợp chi phí hợp đồng vào lúc này.';
        }

        $hasDrift = false;
        if ($contract->source_opportunity_id) {
            $sourceOpportunity = \App\Models\Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->find($contract->source_opportunity_id);

            if ($sourceOpportunity) {
                $snapshot = $sourceOpportunity->external_quote_snapshot ?? [];
                $hasDrift = $contract->source_quote_id !== $sourceOpportunity->external_quote_id
                    || $contract->source_quote_revision !== ($snapshot['revision'] ?? null);
            }
        }

        $payments = $contract->payments()->orderBy('due_date')->get();
        $expenses = $contract->expenses()->orderBy('expense_date')->get();

        $paidTotal = (float) $payments->where('status', \App\Models\ContractPayment::STATUS_PAID)->sum('amount');
        $manualExpenseTotal = (float) $expenses->sum('amount');
        $materialCostTotal = $summary !== null ? (float) data_get($summary, 'priced_line_cost_total', 0) : null;

        $finance = [
            'total_value' => (float) $contract->total_value,
            'paid_total' => $paidTotal,
            'remaining' => (float) $contract->total_value - $paidTotal,
            'overdue_count' => $payments
                ->where('status', '!=', \App\Models\ContractPayment::STATUS_PAID)
                ->filter(fn ($p) => $p->due_date !== null && $p->due_date->isPast())
                ->count(),
            'manual_expense_total' => $manualExpenseTotal,
            'material_cost_total' => $materialCostTotal,
            'expense_total' => $manualExpenseTotal + ($materialCostTotal ?? 0.0),
            'balance' => $paidTotal - ($manualExpenseTotal + ($materialCostTotal ?? 0.0)),
        ];

        $progress = ['type' => (string) $contract->contract_type];

        if ($contract->contract_type === Contract::TYPE_DESIGN) {
            $designItems = \App\Models\DesignItem::query()
                ->where('project_id', (string) $contract->project_id)
                ->with('assignee:id,name')
                ->orderBy('created_at')
                ->get();

            $progress['designItems'] = $designItems;
            $progress['blockedItems'] = $designItems->whereNotNull('blocked_at')->map(fn ($i) => [
                'type' => 'Hạng mục thiết kế',
                'name' => $i->name,
                'note' => $i->blocker_note,
                'blocked_at' => $i->blocked_at,
            ])->values();
        }

        if ($contract->contract_type === Contract::TYPE_CONSTRUCTION) {
            $progress['tasks'] = \App\Models\Task::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', (string) $contract->project_id)
                ->with('assignee:id,name')
                ->orderBy('created_at')
                ->get();
            $progress['inspectionCount'] = \App\Models\QcInspection::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', (string) $contract->project_id)
                ->count();
            $progress['openNcrCount'] = \App\Models\Ncr::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', (string) $contract->project_id)
                ->where('status', '!=', 'closed')
                ->count();
            $progress['receiptCount'] = \App\Models\MaterialReceipt::query()
                ->where('tenant_id', $tenantId)
                ->where('contract_id', (string) $contract->id)
                ->count();
        }

        // Load BOQ + certificates for construction contracts
        $boq = null;
        $boqLines = collect();
        $certificates = collect();
        $cumulativeRetention = 0.0;
        $cumulativeAdvanceDeduction = 0.0;
        $advanceRemaining = 0.0;

        if ($contract->contract_type === Contract::TYPE_CONSTRUCTION) {
            $boq = $contract->boq;
            if ($boq) {
                $boqLines = $boq->lineItems()->get();
            }
            $certificates = \App\Models\PaymentCertificate::query()
                ->where('tenant_id', $tenantId)
                ->where('contract_id', (string) $contract->id)
                ->orderBy('period_no')
                ->get();

            $cumulativeRetention = (float) $certificates->where('status', \App\Models\PaymentCertificate::STATUS_APPROVED)->sum('retention_amount');
            $cumulativeAdvanceDeduction = (float) $certificates->where('status', \App\Models\PaymentCertificate::STATUS_APPROVED)->sum('advance_deduction');
            $advanceRemaining = (float) $contract->advance_amount - $cumulativeAdvanceDeduction;
        }

        return view('contracts.show', [
            'contract' => $contract,
            'summary' => $summary,
            'summaryUnavailableMessage' => $summaryUnavailableMessage,
            'hasQuoteDrift' => $hasDrift,
            'payments' => $payments,
            'expenses' => $expenses,
            'finance' => $finance,
            'progress' => $progress,
            'boq' => $boq,
            'boqLines' => $boqLines,
            'certificates' => $certificates,
            'cumulativeRetention' => $cumulativeRetention,
            'cumulativeAdvanceDeduction' => $cumulativeAdvanceDeduction,
            'advanceRemaining' => $advanceRemaining,
        ]);
    }

    public function downloadPdf(Request $request, string $id, ApiContractController $apiController, DeliverablePdfExportService $pdfService): SymfonyResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $contract = Contract::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        try {
            $response = $apiController->pdf(
                $this->buildApiRequest($request),
                (string) $contract->project_id,
                (string) $contract->id,
                $pdfService
            );
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể tạo PDF hợp đồng vào lúc này.');
        }

        if ($response instanceof JsonResponse) {
            return $this->handleErrorResponse($response);
        }

        return $response;
    }

    // ─── BOQ Lines (contract-scoped) ────────────────────────────────

    public function updateFinanceSettings(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'retention_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'advance_amount' => ['required', 'numeric', 'min:0'],
            'advance_recovery_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($contract, $tenantId, $validated): void {
            $contract->forceFill($validated)->save();

            $advance = (float) $validated['advance_amount'];
            if ($advance > 0) {
                $payment = \App\Models\ContractPayment::query()
                    ->where('tenant_id', $tenantId)
                    ->where('contract_id', (string) $contract->id)
                    ->where('name', self::ADVANCE_PAYMENT_NAME)
                    ->first();

                if ($payment === null) {
                    \App\Models\ContractPayment::query()->create([
                        'tenant_id' => $tenantId,
                        'contract_id' => (string) $contract->id,
                        'name' => self::ADVANCE_PAYMENT_NAME,
                        'amount' => $advance,
                        'status' => \App\Models\ContractPayment::STATUS_PLANNED,
                        'due_date' => now()->addDays(7),
                    ]);
                } elseif ($payment->status === \App\Models\ContractPayment::STATUS_PLANNED) {
                    $payment->forceFill(['amount' => $advance])->save();
                }
                // đã paid: không đụng — UI hiển thị ghi chú lệch.
            }
        });

        return back()->with('success', 'Đã lưu thiết lập tài chính hợp đồng.');
    }

    /**
     * Tính và gán retention_amount / advance_deduction / net_payable trên chứng chỉ (chưa save).
     *
     * @param  float|null  $override  giá trị nhập tay từ request (null = dùng gợi ý tự động).
     * @throws \Illuminate\Validation\ValidationException
     */
    private function applyDeductions(Contract $contract, \App\Models\PaymentCertificate $cert, ?float $override): void
    {
        $total = (float) $cert->total_this_period;
        $retentionPercent = (float) $contract->retention_percent;
        $advanceAmount = (float) $contract->advance_amount;
        $recoveryPercent = (float) $contract->advance_recovery_percent;

        $retentionAmount = round($retentionPercent / 100 * $total, 2);

        // Advance remaining = advance_amount − Σ advance_deduction của certs APPROVED (loại trừ cert hiện tại)
        $advanceRemaining = $advanceAmount - (float) \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $this->currentTenantId())
            ->where('contract_id', (string) $contract->id)
            ->where('status', \App\Models\PaymentCertificate::STATUS_APPROVED)
            ->where('id', '!=', (string) $cert->id)
            ->sum('advance_deduction');

        $suggested = min(round($recoveryPercent / 100 * $total, 2), max($advanceRemaining, 0.0));
        $deduction = $override !== null ? $override : $suggested;

        // Validate
        if ($deduction < 0 || $deduction > max($advanceRemaining, 0.0)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'advance_deduction' => "Thu hồi tạm ứng phải từ 0 đến {$advanceRemaining}.",
            ]);
        }

        if ($retentionAmount + $deduction > $total) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'advance_deduction' => 'Tổng giữ lại và thu hồi tạm ứng vượt giá trị kỳ này.',
            ]);
        }

        $netPayable = round($total - $retentionAmount - $deduction, 2);

        $cert->retention_amount = $retentionAmount;
        $cert->advance_deduction = $deduction;
        $cert->net_payable = $netPayable;
    }

    /**
     * Get tenant_id from authenticated user via Auth facade (avoids auth() helper baseline inflation).
     */
    private function currentTenantId(): string
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return (string) $user->tenant_id;
    }

    private function assertBoqUnlocked(Contract $contract): ?RedirectResponse
    {
        $hasApproved = \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $this->currentTenantId())
            ->where('contract_id', (string) $contract->id)
            ->where('status', \App\Models\PaymentCertificate::STATUS_APPROVED)
            ->exists();

        if ($hasApproved) {
            return back()->withErrors(['boq' => 'Bảng khối lượng đã khóa (đã có chứng chỉ được duyệt).']);
        }

        return null;
    }

    private function ensureContractBoq(Contract $contract, string $tenantId): \App\Models\Boq
    {
        return \App\Models\Boq::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'contract_id' => (string) $contract->id],
            ['project_id' => (string) $contract->project_id, 'code' => 'BOQ-' . $contract->code, 'name' => 'Bảng khối lượng ' . $contract->code]
        );
    }

    public function storeBoqLine(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if ($redirect = $this->assertBoqUnlocked($contract)) {
            return $redirect;
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $boq = $this->ensureContractBoq($contract, $tenantId);

        \App\Models\BoqLineItem::query()->create([
            'tenant_id' => $tenantId,
            'boq_id' => (string) $boq->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'quantity' => (float) $validated['quantity'],
            'unit_price' => isset($validated['unit_price']) ? (float) $validated['unit_price'] : null,
        ]);

        return back()->with('success', 'Đã thêm dòng BOQ.');
    }

    public function updateBoqLine(Request $request, string $id, string $line): RedirectResponse
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if ($redirect = $this->assertBoqUnlocked($contract)) {
            return $redirect;
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $boq = $contract->boq;
        if (! $boq) {
            abort(404);
        }

        \App\Models\BoqLineItem::query()
            ->where('tenant_id', $tenantId)
            ->where('boq_id', (string) $boq->id)
            ->findOrFail($line)
            ->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'unit' => $validated['unit'],
                'quantity' => (float) $validated['quantity'],
                'unit_price' => isset($validated['unit_price']) ? (float) $validated['unit_price'] : null,
            ]);

        return back()->with('success', 'Đã cập nhật dòng BOQ.');
    }

    public function deleteBoqLine(string $id, string $line): RedirectResponse
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if ($redirect = $this->assertBoqUnlocked($contract)) {
            return $redirect;
        }

        if (\App\Models\PaymentCertificateLine::query()->where('boq_line_item_id', $line)->exists()) {
            abort(422, 'Dòng BOQ đã dùng trong chứng chỉ.');
        }

        $boq = $contract->boq;
        if (! $boq) {
            abort(404);
        }

        \App\Models\BoqLineItem::query()
            ->where('tenant_id', $tenantId)
            ->where('boq_id', (string) $boq->id)
            ->findOrFail($line)
            ->delete();

        return back()->with('success', 'Đã xóa dòng BOQ.');
    }

    // ─── Payment Certificates ───────────────────────────────────────

    public function storeCertificate(Request $request, string $id): RedirectResponse
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
        ]);

        $maxPeriod = \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->max('period_no') ?? 0;

        $cert = \App\Models\PaymentCertificate::query()->create([
            'tenant_id' => $tenantId,
            'contract_id' => (string) $contract->id,
            'period_no' => (int) $maxPeriod + 1,
            'period_from' => $validated['period_from'],
            'period_to' => $validated['period_to'],
            'status' => \App\Models\PaymentCertificate::STATUS_DRAFT,
        ]);

        return redirect()->route('operator.contracts.certificates.show', [(string) $contract->id, (string) $cert->id]);
    }

    public function showCertificate(string $id, string $certificate): View
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $cert = \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->findOrFail($certificate);

        $boqLines = $contract->boq ? $contract->boq->lineItems()->get() : collect();

        $summaryService = new \App\Services\PaymentCertificateSummaryService();
        $lineSummaries = $summaryService->lineSummaries($cert);

        // Compute advance remaining for this cert
        $advanceRemaining = (float) $contract->advance_amount - (float) \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->where('status', \App\Models\PaymentCertificate::STATUS_APPROVED)
            ->where('id', '!=', (string) $cert->id)
            ->sum('advance_deduction');

        $suggestedAdvance = min(
            round((float) $contract->advance_recovery_percent / 100 * (float) $cert->total_this_period, 2),
            max($advanceRemaining, 0.0)
        );

        return view('contracts.certificate-show', [
            'contract' => $contract,
            'certificate' => $cert,
            'boqLines' => $boqLines,
            'lineSummaries' => $lineSummaries,
            'advanceRemaining' => $advanceRemaining,
            'suggestedAdvance' => $suggestedAdvance,
        ]);
    }

    public function saveCertificateLines(Request $request, string $id, string $certificate): RedirectResponse
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $cert = \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->findOrFail($certificate);

        if ($cert->status !== \App\Models\PaymentCertificate::STATUS_DRAFT) {
            return back()->withErrors(['status' => 'Chỉ chứng chỉ nháp mới được chỉnh sửa dòng.']);
        }

        $lines = $request->input('lines', []);

        $advanceDeductionInput = $request->has('advance_deduction') ? (float) $request->input('advance_deduction') : null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($tenantId, $contract, $cert, $lines, $advanceDeductionInput): void {
            // Delete existing lines for this cert
            \App\Models\PaymentCertificateLine::query()
                ->where('tenant_id', $tenantId)
                ->where('payment_certificate_id', (string) $cert->id)
                ->delete();

            $total = 0.0;

            foreach ($lines as $boqLineItemId => $qty) {
                $qty = (float) $qty;
                if ($qty <= 0) {
                    continue;
                }

                $boqLine = \App\Models\BoqLineItem::query()
                    ->where('tenant_id', $tenantId)
                    ->find($boqLineItemId);

                if (!$boqLine) {
                    continue;
                }

                $unitPrice = (float) ($boqLine->unit_price ?? 0);
                $amount = $qty * $unitPrice;

                \App\Models\PaymentCertificateLine::query()->create([
                    'tenant_id' => $tenantId,
                    'payment_certificate_id' => (string) $cert->id,
                    'boq_line_item_id' => (string) $boqLineItemId,
                    'qty_this_period' => $qty,
                    'unit_price_snapshot' => $unitPrice,
                    'amount_this_period' => $amount,
                ]);

                $total += $amount;
            }

            $cert->total_this_period = $total;
            $this->applyDeductions($contract, $cert, $advanceDeductionInput);
            $cert->save();
        });

        return back()->with('success', 'Đã lưu chứng chỉ.');
    }

    public function submitCertificate(string $id, string $certificate): RedirectResponse
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $cert = \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->findOrFail($certificate);

        if (!\App\Models\PaymentCertificate::canTransition($cert->status, \App\Models\PaymentCertificate::STATUS_SUBMITTED)) {
            return back()->withErrors(['status' => 'Không thể chuyển trạng thái từ ' . $cert->status . ' sang submitted.']);
        }

        $cert->update([
            'status' => \App\Models\PaymentCertificate::STATUS_SUBMITTED,
            'submitted_by' => (string) Auth::id(),
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Đã gửi chứng chỉ.');
    }

    public function approveCertificate(string $id, string $certificate): RedirectResponse
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $cert = \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->findOrFail($certificate);

        if (!\App\Models\PaymentCertificate::canTransition($cert->status, \App\Models\PaymentCertificate::STATUS_APPROVED)) {
            return back()->withErrors(['status' => 'Không thể chuyển trạng thái từ ' . $cert->status . ' sang approved.']);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($tenantId, $contract, $cert): void {
            // Recompute total from lines
            $total = \App\Models\PaymentCertificateLine::query()
                ->where('tenant_id', $tenantId)
                ->where('payment_certificate_id', (string) $cert->id)
                ->sum('amount_this_period');

            $cert->total_this_period = (float) $total;

            // Recompute deductions — if user already saved an override via saveCertificateLines, keep it
            $effectiveOverride = $cert->advance_deduction > 0 ? (float) $cert->advance_deduction : null;
            $this->applyDeductions($contract, $cert, $effectiveOverride);

            $cert->update([
                'status' => \App\Models\PaymentCertificate::STATUS_APPROVED,
                'total_this_period' => (float) $total,
                'retention_amount' => $cert->retention_amount,
                'advance_deduction' => $cert->advance_deduction,
                'net_payable' => $cert->net_payable,
                'approved_by' => (string) Auth::id(),
                'approved_at' => now(),
            ]);

            // Create ContractPayment — amount is net_payable, not total
            \App\Models\ContractPayment::query()->create([
                'tenant_id' => $tenantId,
                'contract_id' => (string) $contract->id,
                'name' => 'Nghiệm thu KL kỳ ' . $cert->period_no,
                'amount' => (float) $cert->net_payable,
                'status' => \App\Models\ContractPayment::STATUS_PLANNED,
                'due_date' => now()->addDays(14),
            ]);

            // Write EventRecord — includes deduction fields
            \App\Models\EventRecord::query()->create([
                'tenant_id' => $tenantId,
                'project_id' => (string) $contract->project_id,
                'aggregate_type' => 'payment_certificate',
                'aggregate_id' => (string) $cert->id,
                'event_key' => 'payment_certificate.approved',
                'actor_user_id' => (string) Auth::id(),
                'occurred_at' => now(),
                'payload' => [
                    'period_no' => $cert->period_no,
                    'total' => (float) $total,
                    'retention_amount' => (float) $cert->retention_amount,
                    'advance_deduction' => (float) $cert->advance_deduction,
                    'net_payable' => (float) $cert->net_payable,
                ],
            ]);
        });

        return back()->with('success', 'Đã duyệt chứng chỉ.');
    }

    public function certificatePdf(string $id, string $certificate, DeliverablePdfExportService $pdfService): SymfonyResponse
    {
        $tenantId = $this->currentTenantId();
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $cert = \App\Models\PaymentCertificate::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->findOrFail($certificate);

        if ($cert->status !== \App\Models\PaymentCertificate::STATUS_APPROVED) {
            return back()->with('error', 'Chỉ chứng chỉ đã duyệt mới có thể xuất PDF.');
        }

        $boqLines = $contract->boq ? $contract->boq->lineItems()->get() : collect();
        $boqLinesById = $boqLines->keyBy('id');

        $summaryService = new \App\Services\PaymentCertificateSummaryService();
        $lineSummaries = $summaryService->lineSummaries($cert);

        $html = view('contracts.certificate-pdf', [
            'contract' => $contract,
            'certificate' => $cert,
            'boqLinesById' => $boqLinesById,
            'lineSummaries' => $lineSummaries,
            'tenantName' => $contract->tenant->name ?? '',
            'amountInWords' => \App\Support\VietnameseMoneyWords::toWords((float) $cert->net_payable),
        ])->render();

        try {
            $pdfBytes = $pdfService->render($html);
        } catch (\App\Exceptions\DeliverablePdfExportUnavailableException) {
            return back()->with('error', 'Không thể tạo PDF hợp đồng vào lúc này.');
        }

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bien-ban-nghiem-thu-' . $cert->period_no . '.pdf"',
        ]);
    }
}
