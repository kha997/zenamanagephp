<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Treasury\TreasuryAdvance;
use App\Models\Treasury\TreasuryAdvanceSettlement;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryExpenseService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-096 S5 — operator pages for advances: request one (from the project
 * Treasury page), the advance page with its settlements, settlement requests,
 * returns and settlement removal. Rules live in TreasuryAdvanceService.
 */
class TreasuryAdvancePageController extends Controller
{
    private const AMOUNT_RULES = ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'];

    public function __construct(
        private readonly TreasuryAdvanceService $advances,
        private readonly TreasuryExpenseService $expenses,
        private readonly TreasuryBalanceService $balances,
    ) {
    }

    public function show(string $project, string $treasuryAdvance): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $advanceDoc = $this->findAdvance($model, $treasuryAdvance);
        $user = $this->user();
        $payables = $this->expenses->payables($model);

        return view('treasury.advance', [
            'project' => $model,
            'advance' => $this->advances->detail($advanceDoc),
            'wallets' => TreasuryWallet::query()->where('tenant_id', (string) $model->tenant_id)->where('project_id', (string) $model->id)->orderBy('name')->get(),
            'walletBalances' => $this->balances->projectSummary($model)['wallets'],
            'parties' => TreasuryFinancialParty::query()->where('tenant_id', (string) $model->tenant_id)->pluck('name', 'id')->all(),
            'users' => User::query()->where('tenant_id', (string) $model->tenant_id)->pluck('name', 'id')->all(),
            'payables' => $payables,
            'costLabels' => collect($payables)->mapWithKeys(static fn (array $p): array => [$p['type'] . ':' . $p['id'] => $p['label']])->all(),
            'can' => [
                'request' => Gate::forUser($user)->allows('treasury.submit-expense', $model),
                'approve' => Gate::forUser($user)->allows('treasury.approve-expense', $model),
                'reverse' => Gate::forUser($user)->allows('treasury.reverse', $model),
            ],
        ]);
    }

    public function store(Request $request, string $project): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.create-expense', $model);
        $data = $request->validate([
            'source_wallet_id' => ['required', 'string'],
            'destination_party_id' => ['required', 'string'],
            'amount' => self::AMOUNT_RULES,
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $draft = $this->advances->createDraft($model, $this->user(), $data);
            if ($request->input('action') === 'submit') {
                $this->authorize('treasury.submit-expense', $model);
                $this->advances->submit($model, $this->user(), $draft);
            }
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'advance' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.show', $model->id)
            ->with('success', $request->input('action') === 'submit' ? 'Đã lập và gửi duyệt đề nghị tạm ứng.' : 'Đã lưu nháp đề nghị tạm ứng.');
    }

    public function action(Request $request, string $project, string $treasuryAdvance, string $action): RedirectResponse
    {
        $model = $this->findProject($project);
        $advanceDoc = $this->findAdvance($model, $treasuryAdvance);
        $this->authorize(match ($action) {
            'submit' => 'treasury.submit-expense',
            'approve', 'reject' => 'treasury.approve-expense',
            default => 'treasury.create-expense',
        }, $model);

        try {
            match ($action) {
                'submit' => $this->advances->submit($model, $this->user(), $advanceDoc),
                'approve' => $this->advances->approve($model, $this->user(), $advanceDoc, $request->input('note')),
                'reject' => $this->advances->reject($model, $this->user(), $advanceDoc, (string) $request->input('note', '')),
                default => $this->advances->copyToDraft($model, $this->user(), $advanceDoc),
            };
        } catch (TreasuryRuleViolation $e) {
            return back()->with('error', $e->getMessage());
        }

        $messages = ['submit' => 'Đã gửi duyệt.', 'approve' => 'Đã duyệt và chi tạm ứng.', 'reject' => 'Đã từ chối đề nghị tạm ứng.', 'copy' => 'Đã sao chép thành nháp mới.'];
        if ($action === 'approve') {
            return redirect()->route('operator.treasury.projects.advances.show', [$model->id, $advanceDoc->id])->with('success', $messages[$action]);
        }

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', $messages[$action]);
    }

    public function requestSettlement(Request $request, string $project, string $treasuryAdvance): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.submit-expense', $model);
        $advanceDoc = $this->findAdvance($model, $treasuryAdvance);
        $data = $request->validate([
            'amount' => self::AMOUNT_RULES,
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array', 'max:3'],
            'lines.*.cost' => ['nullable', 'string'],
            'lines.*.amount' => ['nullable', 'numeric', 'decimal:0,2'],
        ]);
        $allocations = [];
        foreach ((array) ($data['lines'] ?? []) as $line) {
            $cost = (string) ($line['cost'] ?? '');
            if ($cost === '' || !str_contains($cost, ':')) {
                continue;
            }
            [$type, $id] = explode(':', $cost, 2);
            $allocations[] = ['cost_source_type' => $type, 'cost_source_id' => $id, 'amount' => (string) ($line['amount'] ?? '0')];
        }

        try {
            $this->advances->requestSettlement($model, $this->user(), $advanceDoc, ['amount' => $data['amount'], 'note' => $data['note'] ?? null, 'allocations' => $allocations]);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'settlement' => $e->getMessage()]);
        }

        return $this->toAdvance($model, $advanceDoc, 'Đã gửi đề nghị quyết toán.');
    }

    public function settlementAction(Request $request, string $project, string $treasuryAdvance, string $action): RedirectResponse
    {
        $model = $this->findProject($project);
        $advanceDoc = $this->findAdvance($model, $treasuryAdvance);
        $this->authorize($action === 'withdraw' ? 'treasury.submit-expense' : 'treasury.approve-expense', $model);

        try {
            match ($action) {
                'withdraw' => $this->advances->withdrawSettlementRequest($model, $this->user(), $advanceDoc),
                'approve' => $this->advances->approveSettlementRequest($model, $this->user(), $advanceDoc, $request->input('note')),
                default => $this->advances->rejectSettlementRequest($model, $this->user(), $advanceDoc, (string) $request->input('note', '')),
            };
        } catch (TreasuryRuleViolation $e) {
            return back()->with('error', $e->getMessage());
        }

        $messages = ['withdraw' => 'Đã rút đề nghị quyết toán.', 'approve' => 'Đã duyệt quyết toán.', 'reject' => 'Đã từ chối đề nghị quyết toán.'];

        return $this->toAdvance($model, $advanceDoc, $messages[$action]);
    }

    public function storeReturn(Request $request, string $project, string $treasuryAdvance): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.reverse', $model);
        $advanceDoc = $this->findAdvance($model, $treasuryAdvance);
        $data = $request->validate([
            'destination_wallet_id' => ['required', 'string'],
            'amount' => self::AMOUNT_RULES,
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->advances->recordReturn($model, $this->user(), $advanceDoc, $data);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'return' => $e->getMessage()]);
        }

        return $this->toAdvance($model, $advanceDoc, 'Đã ghi nộp lại tiền tạm ứng.');
    }

    public function removeSettlement(Request $request, string $project, string $treasuryAdvanceSettlement): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.reverse', $model);
        /** @var TreasuryAdvanceSettlement|null $settlement */
        $settlement = TreasuryAdvanceSettlement::query()
            ->whereKey($treasuryAdvanceSettlement)
            ->whereIn('advance_id', TreasuryAdvance::query()->where('project_id', (string) $model->id)->select('id'))
            ->first();
        abort_if($settlement === null, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        try {
            $this->advances->removeSettlement($model, $this->user(), $settlement, (string) $data['reason']);
        } catch (TreasuryRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'settlement' => $e->getMessage()]);
        }
        $documentId = (string) TreasuryAdvance::query()->whereKey($settlement->advance_id)->value('originating_financial_document_id');

        return redirect()->route('operator.treasury.projects.advances.show', [$model->id, $documentId])->with('success', 'Đã gỡ quyết toán.');
    }

    private function toAdvance(Project $project, TreasuryFinancialDocument $advanceDoc, string $message): RedirectResponse
    {
        return redirect()->route('operator.treasury.projects.advances.show', [$project->id, $advanceDoc->id])->with('success', $message);
    }

    private function findAdvance(Project $project, string $id): TreasuryFinancialDocument
    {
        /** @var TreasuryFinancialDocument $advanceDoc */
        $advanceDoc = TreasuryFinancialDocument::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', TreasuryFinancialDocument::TYPE_ADVANCE)
            ->whereKey($id)
            ->firstOrFail();

        return $advanceDoc;
    }

    private function findProject(string $id): Project
    {
        /** @var Project $project */
        $project = Project::query()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $project;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
