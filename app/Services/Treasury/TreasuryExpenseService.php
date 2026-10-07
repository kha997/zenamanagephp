<?php declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Treasury\TreasuryCostSettlementAllocation as Allocation;
use App\Models\Treasury\TreasuryExpenseApproval as Approval;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-066 — Treasury S3 expenses & approvals.
 *
 * GAP-037 v17: expense = source wallet → payee party (§2.3), one debit (§5a);
 * approval log + §10.1 posting gate; closed graph draft → submitted → approved
 * → posted_unreconciled, rejected terminal (§2.1a); mandatory many-to-many
 * cost allocation (architecture A3/A.5, §6–§7) with the §6.3 cap; lock order
 * (§11) 0 wallet → 2 cost sources → 4 shared balance read → 5 document.
 * Owner Gate 1 answers: a new ContractExpense may be created atomically when
 * no cost record exists; approval posts immediately; rejected expenses are
 * copied into a new draft; Z spends only from wallets Z holds.
 */
class TreasuryExpenseService
{
    public const COST_CONTRACT_EXPENSE = 'contract_expense';
    public const COST_MATERIAL_LINE = 'material_receipt_line';

    public function __construct(private readonly TreasuryPostingService $posting)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createDraft(Project $project, User $actor, array $data): Document
    {
        [$wallet, $party, $plan] = $this->validateDraft($project, $actor, $data);

        return DB::transaction(function () use ($project, $actor, $data, $wallet, $party, $plan): Document {
            $document = Document::query()->create([
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'document_type' => Document::TYPE_EXPENSE,
                'status' => Document::STATUS_DRAFT,
                'amount' => TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents((string) $data['amount'])),
                'source_wallet_id' => (string) $wallet->id,
                'destination_party_id' => (string) $party->id,
                'expense_plan' => $plan,
                'created_by' => (string) $actor->id,
            ] + $this->commonFields($data));
            $this->posting->audit($actor, $document, 'treasury.expense.drafted', ['status_path' => null, 'expense_plan' => $plan]);

            return $document;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateDraft(Project $project, User $actor, Document $expense, array $data): Document
    {
        $this->requireExpenseOf($project, $expense);
        if ($expense->status !== Document::STATUS_DRAFT) {
            throw new TreasuryRuleViolation('Chỉ sửa được khoản chi đang là nháp.');
        }
        if ((string) $expense->created_by !== (string) $actor->id) {
            throw new TreasuryRuleViolation('Chỉ người tạo mới sửa được nháp này.');
        }
        [$wallet, $party, $plan] = $this->validateDraft($project, $actor, $data);

        return DB::transaction(function () use ($actor, $expense, $data, $wallet, $party, $plan): Document {
            $expense->fill([
                'amount' => TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents((string) $data['amount'])),
                'source_wallet_id' => (string) $wallet->id,
                'destination_party_id' => (string) $party->id,
                'expense_plan' => $plan,
            ] + $this->commonFields($data));
            $expense->save();
            $this->posting->audit($actor, $expense, 'treasury.expense.updated', ['status_path' => null, 'expense_plan' => $plan]);

            return $expense;
        });
    }

    public function submit(Project $project, User $actor, Document $expense): Document
    {
        $this->requireExpenseOf($project, $expense);
        if ($expense->status !== Document::STATUS_DRAFT) {
            throw new TreasuryRuleViolation('Chỉ gửi duyệt được khoản chi đang là nháp.');
        }
        if ((string) $expense->created_by !== (string) $actor->id) {
            throw new TreasuryRuleViolation('Chỉ người tạo mới gửi duyệt được khoản chi này.');
        }

        return DB::transaction(function () use ($actor, $expense): Document {
            $this->transition($actor, $expense, 'submitted', Document::STATUS_DRAFT, Document::STATUS_SUBMITTED);
            $expense->status = Document::STATUS_SUBMITTED;
            $expense->save();
            $this->posting->audit($actor, $expense, 'treasury.expense.submitted', ['status_path' => null]);

            return $expense;
        });
    }

    /**
     * Approval posts immediately (Owner answer 2).
     */
    public function approve(Project $project, User $actor, Document $expense, ?string $note = null): Document
    {
        $this->requireExpenseOf($project, $expense);
        $selfApproval = (string) $expense->created_by === (string) $actor->id;
        if ($selfApproval && !$actor->hasPermission('treasury.self_approve_expense')) {
            throw new TreasuryRuleViolation('Bạn không được tự duyệt khoản chi do chính mình tạo.');
        }

        /** @var TreasuryWallet $wallet */
        $wallet = TreasuryWallet::query()->whereKey($expense->source_wallet_id)->firstOrFail();
        $plan = (array) ($expense->expense_plan ?? []);

        return DB::transaction(function () use ($project, $actor, $expense, $note, $selfApproval, $wallet, $plan): Document {
            // Class 0 → class 2 → class 4 → class 5.
            $this->posting->lockWallet($wallet);
            $this->lockCostSources($plan);

            $allocations = $this->allocationsFromPlan($project, $actor, $expense, $plan);
            foreach ($allocations as [$type, $id, $cents]) {
                $remaining = $this->incurredCents($type, $id) - $this->netAllocationCents($type, $id, locking: true);
                if ($cents > $remaining) {
                    throw new TreasuryRuleViolation('Số tiền gắn vượt phần chi phí còn phải trả (còn ' . TreasuryBalanceService::fromCents(max(0, $remaining)) . ').', 'allocations');
                }
            }
            $this->posting->requireBalance($wallet, (string) $expense->amount, 'source_wallet_id');

            /** @var Document $locked */
            $locked = Document::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Document::STATUS_SUBMITTED) {
                throw new TreasuryRuleViolation('Khoản chi không còn ở trạng thái chờ duyệt.');
            }

            $mode = $selfApproval ? 'self_approval' : 'standard';
            $this->transition($actor, $locked, 'approved', Document::STATUS_SUBMITTED, Document::STATUS_APPROVED, $note, ['approval_mode' => $mode]);

            // v17 §10.1 gate: posting only follows an `approved` approval row.
            $latest = Approval::query()->where('financial_document_id', (string) $locked->id)->orderByDesc('created_at')->orderByDesc('id')->first();
            if (data_get($latest, 'to_status') !== Document::STATUS_APPROVED) {
                throw new TreasuryRuleViolation('Thiếu bước duyệt trước khi ghi sổ.');
            }

            $locked->fill([
                'status' => Document::STATUS_POSTED_UNRECONCILED,
                'posting_path' => Document::POSTING_PATH_DIRECT,
                'approved_by' => (string) $actor->id,
                'posted_at' => now(),
            ]);
            $locked->save();
            $this->posting->postEntries($locked, [[(string) $wallet->id, Entry::DIRECTION_DEBIT, 'expense']]);

            foreach ($allocations as [$type, $id, $cents]) {
                Allocation::query()->create([
                    'tenant_id' => (string) $locked->tenant_id,
                    'financial_document_id' => (string) $locked->id,
                    'cost_source_contract_expense_id' => $type === self::COST_CONTRACT_EXPENSE ? $id : null,
                    'cost_source_material_receipt_line_id' => $type === self::COST_MATERIAL_LINE ? $id : null,
                    'direction' => Allocation::DIRECTION_APPLY,
                    'allocated_amount' => TreasuryBalanceService::fromCents($cents),
                ]);
            }

            $this->transition($actor, $locked, 'posted', Document::STATUS_APPROVED, Document::STATUS_POSTED_UNRECONCILED);
            $this->posting->audit($actor, $locked, 'treasury.expense.posted', [
                'status_path' => [Document::STATUS_SUBMITTED, Document::STATUS_APPROVED, Document::STATUS_POSTED_UNRECONCILED],
                'approval_mode' => $mode,
            ]);

            return $locked;
        });
    }

    public function reject(Project $project, User $actor, Document $expense, string $note): Document
    {
        $this->requireExpenseOf($project, $expense);
        if (trim($note) === '') {
            throw new TreasuryRuleViolation('Từ chối bắt buộc ghi lý do.', 'note');
        }

        return DB::transaction(function () use ($actor, $expense, $note): Document {
            /** @var Document $locked */
            $locked = Document::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Document::STATUS_SUBMITTED) {
                throw new TreasuryRuleViolation('Chỉ từ chối được khoản chi đang chờ duyệt.');
            }
            $this->transition($actor, $locked, 'rejected', Document::STATUS_SUBMITTED, Document::STATUS_REJECTED, $note);
            $locked->status = Document::STATUS_REJECTED;
            $locked->save();
            $this->posting->audit($actor, $locked, 'treasury.expense.rejected', ['status_path' => null, 'note' => $note]);

            return $locked;
        });
    }

    /** Owner answer 3: a rejected expense is never revived; it is copied. */
    public function copyToDraft(Project $project, User $actor, Document $rejected): Document
    {
        $this->requireExpenseOf($project, $rejected);
        if ($rejected->status !== Document::STATUS_REJECTED) {
            throw new TreasuryRuleViolation('Chỉ sao chép được khoản chi đã bị từ chối.');
        }

        return $this->createDraft($project, $actor, [
            'source_wallet_id' => (string) $rejected->source_wallet_id,
            'destination_party_id' => (string) $rejected->destination_party_id,
            'amount' => (string) $rejected->amount,
            'transaction_date' => $rejected->transaction_date?->toDateString() ?? now()->toDateString(),
            'reference' => $rejected->reference,
            'description' => $rejected->description,
        ] + (array) ($rejected->expense_plan ?? []));
    }

    /**
     * Cost records of the project with incurred, paid and remaining amounts.
     *
     * @return list<array{type: string, id: string, label: string, incurred: string, paid: string, remaining: string}>
     */
    public function payables(Project $project): array
    {
        $rows = [];
        $contractIds = Contract::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)->pluck('id');
        $expenses = DB::table('contract_expenses')->where('tenant_id', (string) $project->tenant_id)->whereIn('contract_id', $contractIds)->orderBy('expense_date')->get();
        foreach ($expenses as $expense) {
            $rows[] = $this->payableRow(self::COST_CONTRACT_EXPENSE, (string) data_get($expense, 'id'),
                (string) data_get($expense, 'description') . ' (' . (string) data_get($expense, 'category') . ')');
        }
        $lines = DB::table('material_receipt_lines')->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)->orderBy('created_at')->get();
        foreach ($lines as $line) {
            $rows[] = $this->payableRow(self::COST_MATERIAL_LINE, (string) data_get($line, 'id'),
                'Phiếu nhập — ' . (string) (data_get($line, 'notes') ?? data_get($line, 'id')));
        }

        return $rows;
    }

    public function incurredCents(string $type, string $id): int
    {
        if ($type === self::COST_CONTRACT_EXPENSE) {
            return TreasuryBalanceService::toCents((string) DB::table('contract_expenses')->where('id', $id)->value('amount'));
        }
        $line = DB::table('material_receipt_lines')->where('id', $id)->first(['quantity_received', 'unit_cost']);
        $milli = self::toMilli((string) data_get($line, 'quantity_received', '0'));
        $unitCents = TreasuryBalanceService::toCents((string) (data_get($line, 'unit_cost') ?? '0'));

        return intdiv($milli * $unitCents + 500, 1000);
    }

    /**
     * v17 §6.1 net allocation. With $locking the read takes a shared lock, so
     * it sees the latest committed allocations whatever the transaction's
     * snapshot (same reason as S2's locked balance read).
     */
    public function netAllocationCents(string $type, string $id, bool $locking = false): int
    {
        $column = $type === self::COST_CONTRACT_EXPENSE ? 'cost_source_contract_expense_id' : 'cost_source_material_receipt_line_id';
        $query = Allocation::query()->withoutGlobalScopes()->where($column, $id);
        if ($locking) {
            $query->sharedLock();
        }
        $rows = $query->get(['direction', 'allocated_amount']);
        $cents = 0;
        foreach ($rows as $row) {
            $amount = TreasuryBalanceService::toCents((string) data_get($row, 'allocated_amount'));
            $cents += data_get($row, 'direction') === Allocation::DIRECTION_APPLY ? $amount : -$amount;
        }

        return $cents;
    }

    /**
     * @return array{type: string, id: string, label: string, incurred: string, paid: string, remaining: string}
     */
    private function payableRow(string $type, string $id, string $label): array
    {
        $incurred = $this->incurredCents($type, $id);
        $paid = $this->netAllocationCents($type, $id);

        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'incurred' => TreasuryBalanceService::fromCents($incurred),
            'paid' => TreasuryBalanceService::fromCents($paid),
            'remaining' => TreasuryBalanceService::fromCents($incurred - $paid),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: TreasuryWallet, 1: TreasuryFinancialParty, 2: array<string, mixed>}
     */
    private function validateDraft(Project $project, User $actor, array $data): array
    {
        /** @var TreasuryWallet|null $wallet */
        $wallet = TreasuryWallet::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)
            ->whereKey((string) ($data['source_wallet_id'] ?? ''))->first();
        if ($wallet === null) {
            throw new TreasuryRuleViolation('Ví không thuộc dự án này.', 'source_wallet_id');
        }
        if (!Gate::forUser($actor)->allows('treasury.transfer-from-wallet', $wallet)) {
            throw new TreasuryRuleViolation('Bạn chỉ được chi từ ví mình đang giữ.', 'source_wallet_id');
        }
        /** @var TreasuryFinancialParty|null $party */
        $party = TreasuryFinancialParty::query()->where('tenant_id', (string) $project->tenant_id)
            ->whereKey((string) ($data['destination_party_id'] ?? ''))->first();
        if ($party === null) {
            throw new TreasuryRuleViolation('Người nhận không hợp lệ.', 'destination_party_id');
        }

        $amountCents = TreasuryBalanceService::toCents((string) $data['amount']);
        $allocations = [];
        $total = 0;
        foreach ((array) ($data['allocations'] ?? []) as $line) {
            $type = (string) data_get($line, 'cost_source_type');
            $id = (string) data_get($line, 'cost_source_id');
            $cents = TreasuryBalanceService::toCents((string) data_get($line, 'amount', '0'));
            if ($id === '' && $cents === 0) {
                continue;
            }
            if ($cents <= 0) {
                throw new TreasuryRuleViolation('Số tiền gắn chi phí phải lớn hơn 0.', 'allocations');
            }
            $this->requireCostSourceOf($project, $type, $id);
            $allocations[] = ['cost_source_type' => $type, 'cost_source_id' => $id, 'amount' => TreasuryBalanceService::fromCents($cents)];
            $total += $cents;
        }

        $new = null;
        $newData = $data['new_contract_expense'] ?? null;
        if (is_array($newData) && (string) ($newData['contract_id'] ?? '') !== '') {
            $contract = Contract::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)
                ->whereKey((string) $newData['contract_id'])->first();
            if ($contract === null) {
                throw new TreasuryRuleViolation('Hợp đồng không thuộc dự án này.', 'new_contract_expense');
            }
            $category = (string) ($newData['category'] ?? '');
            if (!in_array($category, [ContractExpense::CATEGORY_LABOR, ContractExpense::CATEGORY_SUBCONTRACTOR, ContractExpense::CATEGORY_DESIGN_OUTSOURCE, ContractExpense::CATEGORY_MISC], true)) {
                throw new TreasuryRuleViolation('Loại chi phí không hợp lệ.', 'new_contract_expense');
            }
            $cents = TreasuryBalanceService::toCents((string) ($newData['amount'] ?? '0'));
            if ($cents <= 0) {
                throw new TreasuryRuleViolation('Số tiền chi phí mới phải lớn hơn 0.', 'new_contract_expense');
            }
            $new = [
                'contract_id' => (string) $contract->id,
                'category' => $category,
                'description' => trim((string) ($newData['description'] ?? '')) ?: 'Chi phí phát sinh từ khoản chi Ngân quỹ',
                'amount' => TreasuryBalanceService::fromCents($cents),
            ];
            $total += $cents;
        }

        if ($allocations === [] && $new === null) {
            throw new TreasuryRuleViolation('Khoản chi phải gắn với ít nhất một chi phí.', 'allocations');
        }
        if ($total !== $amountCents) {
            throw new TreasuryRuleViolation('Tổng phần gắn chi phí phải bằng đúng số tiền chi.', 'allocations');
        }

        return [$wallet, $party, ['allocations' => $allocations, 'new_contract_expense' => $new]];
    }

    private function requireCostSourceOf(Project $project, string $type, string $id): void
    {
        $exists = match ($type) {
            self::COST_CONTRACT_EXPENSE => DB::table('contract_expenses')
                ->join('contracts', 'contracts.id', '=', 'contract_expenses.contract_id')
                ->where('contract_expenses.id', $id)
                ->where('contract_expenses.tenant_id', (string) $project->tenant_id)
                ->where('contracts.project_id', (string) $project->id)
                ->exists(),
            self::COST_MATERIAL_LINE => DB::table('material_receipt_lines')
                ->where('id', $id)
                ->where('tenant_id', (string) $project->tenant_id)
                ->where('project_id', (string) $project->id)
                ->exists(),
            default => false,
        };
        if (!$exists) {
            throw new TreasuryRuleViolation('Chi phí được chọn không thuộc dự án này.', 'allocations');
        }
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function lockCostSources(array $plan): void
    {
        $byType = [self::COST_CONTRACT_EXPENSE => [], self::COST_MATERIAL_LINE => []];
        foreach ((array) ($plan['allocations'] ?? []) as $line) {
            $byType[(string) data_get($line, 'cost_source_type')][] = (string) data_get($line, 'cost_source_id');
        }
        foreach ([self::COST_CONTRACT_EXPENSE => 'contract_expenses', self::COST_MATERIAL_LINE => 'material_receipt_lines'] as $type => $table) {
            $ids = array_values(array_unique($byType[$type]));
            sort($ids);
            if ($ids !== []) {
                DB::table($table)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
            }
        }
    }

    /**
     * Resolves the plan into allocation lines, creating the planned
     * ContractExpense in this same transaction (A3).
     *
     * @param array<string, mixed> $plan
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function allocationsFromPlan(Project $project, User $actor, Document $expense, array $plan): array
    {
        $lines = [];
        foreach ((array) ($plan['allocations'] ?? []) as $line) {
            $lines[] = [(string) data_get($line, 'cost_source_type'), (string) data_get($line, 'cost_source_id'), TreasuryBalanceService::toCents((string) data_get($line, 'amount'))];
        }
        $new = $plan['new_contract_expense'] ?? null;
        if (is_array($new)) {
            $contractExpense = ContractExpense::query()->create([
                'tenant_id' => (string) $project->tenant_id,
                'contract_id' => (string) $new['contract_id'],
                'expense_date' => $expense->transaction_date?->toDateString() ?? now()->toDateString(),
                'amount' => (string) $new['amount'],
                'category' => (string) $new['category'],
                'description' => (string) $new['description'],
                'recorded_by' => (string) $actor->id,
            ]);
            $lines[] = [self::COST_CONTRACT_EXPENSE, (string) $contractExpense->id, TreasuryBalanceService::toCents((string) $new['amount'])];
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function transition(User $actor, Document $expense, string $event, string $from, string $to, ?string $note = null, array $context = []): void
    {
        Approval::query()->create([
            'tenant_id' => (string) $expense->tenant_id,
            'financial_document_id' => (string) $expense->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => (string) $actor->id,
            'note' => $note,
            'context' => $context === [] ? null : $context,
        ]);
    }

    private function requireExpenseOf(Project $project, Document $expense): void
    {
        if ($expense->document_type !== Document::TYPE_EXPENSE
            || (string) $expense->project_id !== (string) $project->id
            || (string) $expense->tenant_id !== (string) $project->tenant_id) {
            throw new TreasuryRuleViolation('Khoản chi không thuộc dự án này.');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function commonFields(array $data): array
    {
        $reference = trim((string) ($data['reference'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        return [
            'transaction_date' => (string) $data['transaction_date'],
            'reference' => $reference === '' ? null : $reference,
            'description' => $description === '' ? null : $description,
        ];
    }

    private static function toMilli(string $quantity): int
    {
        [$whole, $fraction] = array_pad(explode('.', ltrim(trim($quantity), '+'), 2), 2, '');
        $fraction = substr(str_pad($fraction, 3, '0'), 0, 3);

        return ((int) ($whole === '' ? '0' : $whole)) * 1000 + (int) $fraction;
    }
}
