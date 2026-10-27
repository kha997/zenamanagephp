<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\Project;
use App\Models\Treasury\TreasuryAdvance as Advance;
use App\Models\Treasury\TreasuryAdvanceSettlement as Settlement;
use App\Models\Treasury\TreasuryCostSettlementAllocation as Allocation;
use App\Models\Treasury\TreasuryExpenseApproval as Approval;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-096 — Treasury S5 advances and settlements (approved Gate 2 Option A).
 *
 * GAP-037 v17: an advance (wallet → party, direct) posts its debit and its
 * treasury_advances row in one write (§7.5); outstanding = amount − Σapply +
 * Σreverse, 0 once the advance is reversed (§6.5/§6.6); an approved-expense
 * settlement carries cost allocations summing to its amount and has no ledger
 * entry (§7.4, §6.3 cap shared with S3); an advance return (party → wallet)
 * creates its cash_return/apply atomically (§7.6/§7.7). Lock order (§11):
 * 0 wallet → 2 cost sources → 3 advance → 4 → 5 document.
 * Owner Gate 1 answers: the advance is requested and approved like an S3
 * expense; a settlement request is filed by the holder and approved; cash is
 * returned straight into a project wallet; ageing buckets 30/60/90 days.
 * Option A: a settlement request lives in the advance document's approval log
 * (`settlement_requested`, closed by `settlement_withdrawn` / `_rejected` /
 * `_approved`); settlement rows are written only on approval.
 */
class TreasuryAdvanceService
{
    public const BUCKET_UNDER_30 = 'under_30';
    public const BUCKET_30_60 = '30_60';
    public const BUCKET_60_90 = '60_90';
    public const BUCKET_OVER_90 = 'over_90';

    public const BUCKETS = [self::BUCKET_UNDER_30, self::BUCKET_30_60, self::BUCKET_60_90, self::BUCKET_OVER_90];

    /** The approval event of an advance; S3's `approved` feeds the self-approved expense total, this does not. */
    public const EVENT_APPROVED = 'advance_approved';

    public const EVENT_REQUESTED = 'settlement_requested';
    public const EVENT_WITHDRAWN = 'settlement_withdrawn';
    public const EVENT_REJECTED = 'settlement_rejected';
    public const EVENT_SETTLED = 'settlement_approved';
    public const EVENT_REMOVED = 'settlement_removed';

    private const POSTED = [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED];

    public function __construct(
        private readonly TreasuryPostingService $posting,
        private readonly TreasuryExpenseService $expenses,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createDraft(Project $project, User $actor, array $data): Document
    {
        [$wallet, $party] = $this->validateDraft($project, $actor, $data);

        return DB::transaction(function () use ($project, $actor, $data, $wallet, $party): Document {
            $document = Document::query()->create([
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'document_type' => Document::TYPE_ADVANCE,
                'status' => Document::STATUS_DRAFT,
                'amount' => self::money((string) $data['amount']),
                'source_wallet_id' => (string) $wallet->id,
                'destination_party_id' => (string) $party->id,
                'created_by' => (string) $actor->id,
            ] + $this->commonFields($data));
            $this->posting->audit($actor, $document, 'treasury.advance.drafted', ['status_path' => null]);

            return $document;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateDraft(Project $project, User $actor, Document $advance, array $data): Document
    {
        $this->requireAdvanceOf($project, $advance);
        if ($advance->status !== Document::STATUS_DRAFT) {
            throw new TreasuryRuleViolation('Chỉ sửa được đề nghị tạm ứng đang là nháp.');
        }
        if ((string) $advance->created_by !== (string) $actor->id) {
            throw new TreasuryRuleViolation('Chỉ người tạo mới sửa được nháp này.');
        }
        [$wallet, $party] = $this->validateDraft($project, $actor, $data);

        return DB::transaction(function () use ($actor, $advance, $data, $wallet, $party): Document {
            $advance->fill([
                'amount' => self::money((string) $data['amount']),
                'source_wallet_id' => (string) $wallet->id,
                'destination_party_id' => (string) $party->id,
            ] + $this->commonFields($data));
            $advance->save();
            $this->posting->audit($actor, $advance, 'treasury.advance.updated', ['status_path' => null]);

            return $advance;
        });
    }

    public function submit(Project $project, User $actor, Document $advance): Document
    {
        $this->requireAdvanceOf($project, $advance);
        if ($advance->status !== Document::STATUS_DRAFT) {
            throw new TreasuryRuleViolation('Chỉ gửi duyệt được đề nghị tạm ứng đang là nháp.');
        }
        if ((string) $advance->created_by !== (string) $actor->id) {
            throw new TreasuryRuleViolation('Chỉ người tạo mới gửi duyệt được đề nghị này.');
        }

        return DB::transaction(function () use ($actor, $advance): Document {
            $this->expenses->transition($actor, $advance, 'submitted', Document::STATUS_DRAFT, Document::STATUS_SUBMITTED);
            $advance->status = Document::STATUS_SUBMITTED;
            $advance->save();
            $this->posting->audit($actor, $advance, 'treasury.advance.submitted', ['status_path' => null]);

            return $advance;
        });
    }

    /**
     * Approval pays the advance out at once and opens it (v17 §7.5).
     */
    public function approve(Project $project, User $actor, Document $advance, ?string $note = null): Document
    {
        $this->requireAdvanceOf($project, $advance);
        $selfApproval = (string) $advance->created_by === (string) $actor->id;
        if ($selfApproval && !$actor->hasPermission('treasury.self_approve_expense')) {
            throw new TreasuryRuleViolation('Bạn không được tự duyệt đề nghị tạm ứng do chính mình tạo.');
        }

        /** @var TreasuryWallet $wallet */
        $wallet = TreasuryWallet::query()->whereKey($advance->source_wallet_id)->firstOrFail();

        return DB::transaction(function () use ($actor, $advance, $note, $selfApproval, $wallet): Document {
            // Class 0 → class 4 (balance) → class 5; the advance row (class 3)
            // is inserted, never read, so no earlier lock is needed.
            $this->posting->lockWallet($wallet);
            $this->posting->requireBalance($wallet, (string) $advance->amount, 'source_wallet_id');

            /** @var Document $locked */
            $locked = Document::query()->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Document::STATUS_SUBMITTED) {
                throw new TreasuryRuleViolation('Đề nghị tạm ứng không còn ở trạng thái chờ duyệt.');
            }

            $mode = $selfApproval ? 'self_approval' : 'standard';
            $this->expenses->transition($actor, $locked, self::EVENT_APPROVED, Document::STATUS_SUBMITTED, Document::STATUS_APPROVED, $note, ['approval_mode' => $mode]);

            $locked->fill([
                'status' => Document::STATUS_POSTED_UNRECONCILED,
                'posting_path' => Document::POSTING_PATH_DIRECT,
                'approved_by' => (string) $actor->id,
                'posted_at' => now(),
            ]);
            $locked->save();
            $this->posting->postEntries($locked, [[(string) $wallet->id, Entry::DIRECTION_DEBIT, 'advance']]);
            Advance::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => (string) $locked->project_id,
                'financial_party_id' => (string) $locked->destination_party_id,
                'originating_financial_document_id' => (string) $locked->id,
                'amount' => (string) $locked->amount,
            ]);

            $this->expenses->transition($actor, $locked, 'posted', Document::STATUS_APPROVED, Document::STATUS_POSTED_UNRECONCILED);
            $this->posting->audit($actor, $locked, 'treasury.advance.posted', [
                'status_path' => [Document::STATUS_SUBMITTED, Document::STATUS_APPROVED, Document::STATUS_POSTED_UNRECONCILED],
                'approval_mode' => $mode,
            ]);

            return $locked;
        });
    }

    public function reject(Project $project, User $actor, Document $advance, string $note): Document
    {
        $this->requireAdvanceOf($project, $advance);
        if (trim($note) === '') {
            throw new TreasuryRuleViolation('Từ chối bắt buộc ghi lý do.', 'note');
        }

        return DB::transaction(function () use ($actor, $advance, $note): Document {
            /** @var Document $locked */
            $locked = Document::query()->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Document::STATUS_SUBMITTED) {
                throw new TreasuryRuleViolation('Chỉ từ chối được đề nghị tạm ứng đang chờ duyệt.');
            }
            $this->expenses->transition($actor, $locked, 'rejected', Document::STATUS_SUBMITTED, Document::STATUS_REJECTED, $note);
            $locked->status = Document::STATUS_REJECTED;
            $locked->save();
            $this->posting->audit($actor, $locked, 'treasury.advance.rejected', ['status_path' => null, 'note' => $note]);

            return $locked;
        });
    }

    public function copyToDraft(Project $project, User $actor, Document $rejected): Document
    {
        $this->requireAdvanceOf($project, $rejected);
        if ($rejected->status !== Document::STATUS_REJECTED) {
            throw new TreasuryRuleViolation('Chỉ sao chép được đề nghị tạm ứng đã bị từ chối.');
        }

        return $this->createDraft($project, $actor, [
            'source_wallet_id' => (string) $rejected->source_wallet_id,
            'destination_party_id' => (string) $rejected->destination_party_id,
            'amount' => (string) $rejected->amount,
            'transaction_date' => $rejected->transaction_date?->toDateString() ?? now()->toDateString(),
            'reference' => $rejected->reference,
            'description' => $rejected->description,
        ]);
    }

    /**
     * v17 §6.5 / §6.6. With $locking the settlement rows are read with a
     * shared lock, under the caller's class-3 advance lock.
     */
    public function outstandingCents(Advance $advance, bool $locking = false): int
    {
        $query = Document::query()->withoutGlobalScopes()->whereKey($advance->originating_financial_document_id);
        if ($locking) {
            $query->sharedLock();
        }
        if ($query->value('status') === Document::STATUS_REVERSED) {
            return 0;
        }

        return TreasuryBalanceService::toCents((string) $advance->amount) - $this->settledCents($advance, null, $locking);
    }

    /**
     * Files a settlement request (Owner answer 2) in the advance's approval log.
     *
     * @param array<string, mixed> $data amount, allocations, note
     */
    public function requestSettlement(Project $project, User $actor, Document $document, array $data): Approval
    {
        $advance = $this->advanceOf($project, $document);
        $amountCents = TreasuryBalanceService::toCents((string) ($data['amount'] ?? '0'));
        if ($amountCents <= 0) {
            throw new TreasuryRuleViolation('Số tiền quyết toán phải lớn hơn 0.', 'amount');
        }
        $lines = $this->validateAllocations($project, $data, $amountCents);

        return DB::transaction(function () use ($actor, $advance, $data, $amountCents, $lines): Approval {
            $this->lockAdvance($advance);
            $locked = $this->lockDocument($advance);
            if ($this->openRequest($locked) !== null) {
                throw new TreasuryRuleViolation('Khoản tạm ứng này đã có một đề nghị quyết toán đang chờ duyệt.');
            }
            $outstanding = $this->outstandingCents($advance, locking: true);
            if ($amountCents > $outstanding) {
                throw new TreasuryRuleViolation('Số tiền quyết toán vượt số còn nợ (còn ' . TreasuryBalanceService::fromCents($outstanding) . ').', 'amount');
            }
            $note = trim((string) ($data['note'] ?? ''));
            $event = $this->event($actor, $locked, self::EVENT_REQUESTED, $note === '' ? null : $note, [
                'amount' => TreasuryBalanceService::fromCents($amountCents),
                'allocations' => $lines,
            ]);
            $this->posting->audit($actor, $locked, 'treasury.advance.settlement_requested', ['status_path' => null, 'request_id' => (string) $event->id]);

            return $event;
        });
    }

    public function withdrawSettlementRequest(Project $project, User $actor, Document $document): Approval
    {
        $advance = $this->advanceOf($project, $document);

        return DB::transaction(function () use ($actor, $advance): Approval {
            $this->lockAdvance($advance);
            $locked = $this->lockDocument($advance);
            $request = $this->requireOpenRequest($locked);
            if ((string) $request->actor_id !== (string) $actor->id) {
                throw new TreasuryRuleViolation('Chỉ người lập mới rút được đề nghị quyết toán.');
            }
            $event = $this->event($actor, $locked, self::EVENT_WITHDRAWN, null, ['request_id' => (string) $request->id]);
            $this->posting->audit($actor, $locked, 'treasury.advance.settlement_withdrawn', ['status_path' => null, 'request_id' => (string) $request->id]);

            return $event;
        });
    }

    public function rejectSettlementRequest(Project $project, User $actor, Document $document, string $note): Approval
    {
        $advance = $this->advanceOf($project, $document);
        if (trim($note) === '') {
            throw new TreasuryRuleViolation('Từ chối bắt buộc ghi lý do.', 'note');
        }

        return DB::transaction(function () use ($actor, $advance, $note): Approval {
            $this->lockAdvance($advance);
            $locked = $this->lockDocument($advance);
            $request = $this->requireOpenRequest($locked);
            $event = $this->event($actor, $locked, self::EVENT_REJECTED, $note, ['request_id' => (string) $request->id]);
            $this->posting->audit($actor, $locked, 'treasury.advance.settlement_rejected', ['status_path' => null, 'request_id' => (string) $request->id, 'note' => $note]);

            return $event;
        });
    }

    /**
     * Applies the open request (v17 §7.4): caps on outstanding and on each
     * cost's incurred amount are re-checked under locks 2 → 3 → 5.
     */
    public function approveSettlementRequest(Project $project, User $actor, Document $document, ?string $note = null): Settlement
    {
        $advance = $this->advanceOf($project, $document);
        $pending = $this->openRequest($document);
        if ($pending === null) {
            throw new TreasuryRuleViolation('Không có đề nghị quyết toán nào đang chờ duyệt.');
        }
        $selfApproval = (string) $pending->actor_id === (string) $actor->id;
        if ($selfApproval && !$actor->hasPermission('treasury.self_approve_expense')) {
            throw new TreasuryRuleViolation('Bạn không được tự duyệt đề nghị quyết toán do chính mình lập.');
        }
        $plan = ['allocations' => (array) data_get($pending->context, 'allocations', [])];

        return DB::transaction(function () use ($actor, $advance, $pending, $plan, $note, $selfApproval): Settlement {
            $this->expenses->lockCostSources($plan);
            $this->lockAdvance($advance);
            $locked = $this->lockDocument($advance);
            $request = $this->requireOpenRequest($locked);
            if ((string) $request->id !== (string) $pending->id) {
                throw new TreasuryRuleViolation('Đề nghị quyết toán đã thay đổi, hãy tải lại trang.');
            }

            $amountCents = TreasuryBalanceService::toCents((string) data_get($request->context, 'amount'));
            $outstanding = $this->outstandingCents($advance, locking: true);
            if ($amountCents > $outstanding) {
                throw new TreasuryRuleViolation('Số tiền quyết toán vượt số còn nợ (còn ' . TreasuryBalanceService::fromCents($outstanding) . ').', 'amount');
            }
            $byCost = [];
            foreach ($plan['allocations'] as $line) {
                $key = (string) data_get($line, 'cost_source_type') . '|' . (string) data_get($line, 'cost_source_id');
                $byCost[$key] = ($byCost[$key] ?? 0) + TreasuryBalanceService::toCents((string) data_get($line, 'amount'));
            }
            foreach ($byCost as $key => $cents) {
                [$type, $id] = explode('|', $key, 2);
                $remaining = $this->expenses->incurredCents($type, $id) - $this->expenses->netAllocationCents($type, $id, locking: true);
                if ($cents > $remaining) {
                    throw new TreasuryRuleViolation('Số tiền gắn vượt phần chi phí còn phải trả (còn ' . TreasuryBalanceService::fromCents(max(0, $remaining)) . ').', 'allocations');
                }
            }

            $settlement = Settlement::query()->create([
                'tenant_id' => (string) $advance->tenant_id,
                'advance_id' => (string) $advance->id,
                'settlement_type' => Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE,
                'direction' => Settlement::DIRECTION_APPLY,
                'amount' => TreasuryBalanceService::fromCents($amountCents),
            ]);
            foreach ($plan['allocations'] as $line) {
                $type = (string) data_get($line, 'cost_source_type');
                Allocation::query()->create([
                    'tenant_id' => (string) $advance->tenant_id,
                    'advance_settlement_id' => (string) $settlement->id,
                    'cost_source_contract_expense_id' => $type === TreasuryExpenseService::COST_CONTRACT_EXPENSE ? (string) data_get($line, 'cost_source_id') : null,
                    'cost_source_material_receipt_line_id' => $type === TreasuryExpenseService::COST_MATERIAL_LINE ? (string) data_get($line, 'cost_source_id') : null,
                    'direction' => Allocation::DIRECTION_APPLY,
                    'allocated_amount' => (string) data_get($line, 'amount'),
                ]);
            }

            $mode = $selfApproval ? 'self_approval' : 'standard';
            $this->event($actor, $locked, self::EVENT_SETTLED, $note, [
                'request_id' => (string) $request->id,
                'settlement_id' => (string) $settlement->id,
                'approval_mode' => $mode,
            ]);
            $this->posting->audit($actor, $locked, 'treasury.advance.settlement_approved', [
                'status_path' => null,
                'request_id' => (string) $request->id,
                'settlement_id' => (string) $settlement->id,
                'approval_mode' => $mode,
            ]);

            return $settlement;
        });
    }

    /**
     * Undoes an approved-expense settlement: one reverse settlement and one
     * reverse allocation per active allocation (v17 §7.4), locks 2 → 3 → 5.
     */
    public function removeSettlement(Project $project, User $actor, Settlement $settlement, string $reason): Settlement
    {
        /** @var Advance|null $advance */
        $advance = Advance::query()->whereKey($settlement->advance_id)->first();
        if ($advance === null || (string) $advance->project_id !== (string) $project->id || (string) $advance->tenant_id !== (string) $project->tenant_id) {
            throw new TreasuryRuleViolation('Quyết toán không thuộc dự án này.');
        }
        if ($settlement->settlement_type !== Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE || $settlement->direction !== Settlement::DIRECTION_APPLY) {
            throw new TreasuryRuleViolation('Chỉ gỡ được quyết toán bằng chi phí; hoàn tiền gỡ bằng "Đảo".');
        }
        if (trim($reason) === '') {
            throw new TreasuryRuleViolation('Gỡ quyết toán bắt buộc ghi lý do.', 'reason');
        }

        return DB::transaction(function () use ($actor, $advance, $settlement, $reason): Settlement {
            $applies = Allocation::query()
                ->where('advance_settlement_id', (string) $settlement->id)
                ->where('direction', Allocation::DIRECTION_APPLY)
                ->orderBy('id')
                ->get();
            $this->expenses->lockCostSources(['allocations' => $applies->map(static fn ($a): array => [
                'cost_source_type' => data_get($a, 'cost_source_contract_expense_id') !== null ? TreasuryExpenseService::COST_CONTRACT_EXPENSE : TreasuryExpenseService::COST_MATERIAL_LINE,
                'cost_source_id' => (string) (data_get($a, 'cost_source_contract_expense_id') ?? data_get($a, 'cost_source_material_receipt_line_id')),
            ])->all()]);
            $this->lockAdvance($advance);
            $locked = $this->lockDocument($advance);
            if (Settlement::query()->where('reverses_settlement_id', (string) $settlement->id)->lockForUpdate()->exists()) {
                throw new TreasuryRuleViolation('Quyết toán này đã được gỡ.');
            }

            $reverse = Settlement::query()->create([
                'tenant_id' => (string) $advance->tenant_id,
                'advance_id' => (string) $advance->id,
                'settlement_type' => Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE,
                'direction' => Settlement::DIRECTION_REVERSE,
                'amount' => (string) $settlement->amount,
                'reverses_settlement_id' => (string) $settlement->id,
            ]);
            foreach ($applies as $apply) {
                Allocation::query()->create([
                    'tenant_id' => (string) $advance->tenant_id,
                    'advance_settlement_id' => (string) $reverse->id,
                    'cost_source_contract_expense_id' => data_get($apply, 'cost_source_contract_expense_id'),
                    'cost_source_material_receipt_line_id' => data_get($apply, 'cost_source_material_receipt_line_id'),
                    'direction' => Allocation::DIRECTION_REVERSE,
                    'allocated_amount' => data_get($apply, 'allocated_amount'),
                    'reverses_allocation_id' => (string) data_get($apply, 'id'),
                ]);
            }

            $this->event($actor, $locked, self::EVENT_REMOVED, $reason, ['settlement_id' => (string) $settlement->id, 'reverse_settlement_id' => (string) $reverse->id]);
            $this->posting->audit($actor, $locked, 'treasury.advance.settlement_removed', [
                'status_path' => null,
                'settlement_id' => (string) $settlement->id,
                'reason' => $reason,
            ]);

            return $reverse;
        });
    }

    /**
     * Unused cash paid back into a project wallet (Owner answer 3, v17 §7.6
     * direct branch): the advance_return document, its credit and its
     * cash_return/apply in one write; locks 0 → 3 → 5.
     *
     * @param array<string, mixed> $data destination_wallet_id, amount, transaction_date, reference, description
     */
    public function recordReturn(Project $project, User $actor, Document $document, array $data): Document
    {
        $advance = $this->advanceOf($project, $document);
        $wallet = $this->posting->projectWallet($project, (string) ($data['destination_wallet_id'] ?? ''), 'destination_wallet_id');
        $amountCents = TreasuryBalanceService::toCents((string) ($data['amount'] ?? '0'));
        if ($amountCents <= 0) {
            throw new TreasuryRuleViolation('Số tiền nộp lại phải lớn hơn 0.', 'amount');
        }

        return DB::transaction(function () use ($project, $actor, $advance, $wallet, $data, $amountCents): Document {
            $this->posting->lockWallet($wallet);
            $this->lockAdvance($advance);
            $outstanding = $this->outstandingCents($advance, locking: true);
            if ($amountCents > $outstanding) {
                throw new TreasuryRuleViolation('Số tiền nộp lại vượt số còn nợ (còn ' . TreasuryBalanceService::fromCents($outstanding) . ').', 'amount');
            }

            $return = Document::query()->create([
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'document_type' => Document::TYPE_ADVANCE_RETURN,
                'status' => Document::STATUS_POSTED_UNRECONCILED,
                'posting_path' => Document::POSTING_PATH_DIRECT,
                'amount' => TreasuryBalanceService::fromCents($amountCents),
                'source_party_id' => (string) $advance->financial_party_id,
                'destination_wallet_id' => (string) $wallet->id,
                'created_by' => (string) $actor->id,
                'approved_by' => (string) $actor->id,
                'posted_at' => now(),
            ] + $this->commonFields($data));
            $this->posting->postEntries($return, [[(string) $wallet->id, Entry::DIRECTION_CREDIT, 'advance_return']]);
            Settlement::query()->create([
                'tenant_id' => (string) $advance->tenant_id,
                'advance_id' => (string) $advance->id,
                'settlement_type' => Settlement::SETTLEMENT_TYPE_CASH_RETURN,
                'direction' => Settlement::DIRECTION_APPLY,
                'amount' => TreasuryBalanceService::fromCents($amountCents),
                'financial_document_id' => (string) $return->id,
            ]);
            $this->posting->audit($actor, $return, 'treasury.advance_return.posted', ['advance_id' => (string) $advance->id]);

            return $return;
        });
    }

    /**
     * Posted advances of the project still owed, oldest first, with ageing
     * buckets by days since payment (Owner answer 4; lower bound inclusive).
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function ageing(Project $project, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::now())->copy()->startOfDay();
        $rows = [];
        $totals = array_fill_keys(self::BUCKETS, 0);
        /** @var \Illuminate\Database\Eloquent\Collection<int, Document> $documents */
        $documents = Document::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', Document::TYPE_ADVANCE)
            ->whereIn('status', self::POSTED)
            ->orderBy('posted_at')
            ->orderBy('id')
            ->get();
        foreach ($documents as $document) {
            $row = $this->summaryRow($document, $today);
            if ($row === null || TreasuryBalanceService::toCents((string) $row['outstanding']) <= 0) {
                continue;
            }
            $rows[] = $row;
            $totals[(string) $row['bucket']] += TreasuryBalanceService::toCents((string) $row['outstanding']);
        }

        return ['rows' => $rows, 'totals' => array_map([TreasuryBalanceService::class, 'fromCents'], $totals)];
    }

    /**
     * Advance requests not yet paid out: drafts, waiting and rejected ones.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    public function pendingDocuments(Project $project): \Illuminate\Database\Eloquent\Collection
    {
        return Document::query()
            ->with(['sourceWallet', 'destinationParty'])
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', Document::TYPE_ADVANCE)
            ->whereIn('status', [Document::STATUS_DRAFT, Document::STATUS_SUBMITTED, Document::STATUS_REJECTED])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Document $document): array
    {
        $row = $this->projectDocument($document);
        $row['advance'] = $this->summaryRow($document, Carbon::now()->startOfDay());
        $advance = $this->advanceRow($document);
        $settlements = [];
        if ($advance !== null) {
            $reversedIds = Settlement::query()->where('advance_id', (string) $advance->id)->whereNotNull('reverses_settlement_id')->pluck('reverses_settlement_id')->map(static fn ($id): string => (string) $id)->all();
            foreach (Settlement::query()->where('advance_id', (string) $advance->id)->orderBy('created_at')->orderBy('id')->get() as $settlement) {
                $settlements[] = [
                    'id' => (string) $settlement->id,
                    'settlement_type' => $settlement->settlement_type,
                    'direction' => $settlement->direction,
                    'amount' => (string) $settlement->amount,
                    'financial_document_id' => $settlement->financial_document_id,
                    'reverses_settlement_id' => $settlement->reverses_settlement_id,
                    'removed' => in_array((string) $settlement->id, $reversedIds, true),
                    'allocations' => Allocation::query()->where('advance_settlement_id', (string) $settlement->id)->orderBy('id')->get()->map(static fn ($a): array => [
                        'cost_source_type' => data_get($a, 'cost_source_contract_expense_id') !== null ? TreasuryExpenseService::COST_CONTRACT_EXPENSE : TreasuryExpenseService::COST_MATERIAL_LINE,
                        'cost_source_id' => (string) (data_get($a, 'cost_source_contract_expense_id') ?? data_get($a, 'cost_source_material_receipt_line_id')),
                        'direction' => (string) data_get($a, 'direction'),
                        'amount' => (string) data_get($a, 'allocated_amount'),
                    ])->all(),
                    'created_at' => $settlement->created_at->toIso8601String(),
                ];
            }
        }
        $row['settlements'] = $settlements;
        $open = $this->openRequest($document);
        $row['open_request'] = $open === null ? null : [
            'id' => (string) $open->id,
            'actor_id' => (string) $open->actor_id,
            'amount' => (string) data_get($open->context, 'amount'),
            'allocations' => (array) data_get($open->context, 'allocations', []),
            'note' => $open->note,
            'created_at' => $open->created_at->toIso8601String(),
        ];
        $row['events'] = Approval::query()->where('financial_document_id', (string) $document->id)->orderBy('created_at')->orderBy('id')->get()->map(static fn ($a): array => [
            'id' => (string) data_get($a, 'id'),
            'event' => (string) data_get($a, 'event'),
            'actor_id' => (string) data_get($a, 'actor_id'),
            'note' => data_get($a, 'note'),
            'context' => data_get($a, 'context'),
            'created_at' => Carbon::parse((string) data_get($a, 'created_at'))->toIso8601String(),
        ])->all();
        /** @var \Illuminate\Database\Eloquent\Collection<int, Document> $returns */
        $returns = $advance === null ? new \Illuminate\Database\Eloquent\Collection() : Document::query()
            ->whereIn('id', Settlement::query()->where('advance_id', (string) $advance->id)->where('settlement_type', Settlement::SETTLEMENT_TYPE_CASH_RETURN)->where('direction', Settlement::DIRECTION_APPLY)->select('financial_document_id'))
            ->orderBy('created_at')->orderBy('id')->get();
        $row['returns'] = $returns->map(fn (Document $d): array => $this->projectDocument($d))->all();

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function projectDocument(Document $document): array
    {
        return [
            'id' => (string) $document->id,
            'document_type' => $document->document_type,
            'status' => $document->status,
            'amount' => (string) $document->amount,
            'source_wallet_id' => $document->source_wallet_id,
            'destination_wallet_id' => $document->destination_wallet_id,
            'source_party_id' => $document->source_party_id,
            'destination_party_id' => $document->destination_party_id,
            'transaction_date' => $document->transaction_date?->toDateString(),
            'reference' => $document->reference,
            'description' => $document->description,
            'created_by' => (string) $document->created_by,
            'approved_by' => $document->approved_by,
            'posted_at' => $document->posted_at?->toIso8601String(),
        ];
    }

    /**
     * The open settlement request: a `settlement_requested` event no later
     * event closes.
     */
    public function openRequest(Document $document): ?Approval
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Approval> $events */
        $events = Approval::query()
            ->where('financial_document_id', (string) $document->id)
            ->whereIn('event', [self::EVENT_REQUESTED, self::EVENT_WITHDRAWN, self::EVENT_REJECTED, self::EVENT_SETTLED])
            ->orderBy('id')
            ->get();
        $closed = $events->where('event', '!=', self::EVENT_REQUESTED)->map(static fn (Approval $a): string => (string) data_get($a->context, 'request_id'))->all();

        return $events->where('event', self::EVENT_REQUESTED)->first(static fn (Approval $a): bool => !in_array((string) $a->id, $closed, true));
    }

    public function advanceRow(Document $document): ?Advance
    {
        return Advance::query()->where('originating_financial_document_id', (string) $document->id)->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summaryRow(Document $document, Carbon $today): ?array
    {
        $advance = $this->advanceRow($document);
        if ($advance === null) {
            return null;
        }
        $party = TreasuryFinancialParty::query()->whereKey($advance->financial_party_id)->first();
        $wallet = TreasuryWallet::query()->whereKey($document->source_wallet_id)->first();
        $postedOn = ($document->posted_at ?? $document->created_at)->copy()->startOfDay();
        $days = (int) $postedOn->diffInDays($today, false);

        return [
            'id' => (string) $document->id,
            'advance_id' => (string) $advance->id,
            'party_id' => (string) $advance->financial_party_id,
            'party_name' => (string) data_get($party, 'name'),
            'wallet_id' => (string) $document->source_wallet_id,
            'wallet_name' => (string) data_get($wallet, 'name'),
            'status' => $document->status,
            'amount' => self::money((string) $advance->amount),
            'settled' => TreasuryBalanceService::fromCents($this->settledCents($advance, Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE)),
            'returned' => TreasuryBalanceService::fromCents($this->settledCents($advance, Settlement::SETTLEMENT_TYPE_CASH_RETURN)),
            'outstanding' => TreasuryBalanceService::fromCents($this->outstandingCents($advance)),
            'posted_on' => $postedOn->toDateString(),
            'days' => $days,
            'bucket' => match (true) {
                $days < 30 => self::BUCKET_UNDER_30,
                $days < 60 => self::BUCKET_30_60,
                $days < 90 => self::BUCKET_60_90,
                default => self::BUCKET_OVER_90,
            },
            'open_request' => $this->openRequest($document) !== null,
        ];
    }

    private function settledCents(Advance $advance, ?string $type = null, bool $locking = false): int
    {
        $query = Settlement::query()->withoutGlobalScopes()->where('advance_id', (string) $advance->id);
        if ($type !== null) {
            $query->where('settlement_type', $type);
        }
        if ($locking) {
            $query->sharedLock();
        }
        $cents = 0;
        foreach ($query->get(['direction', 'amount']) as $row) {
            $amount = TreasuryBalanceService::toCents((string) data_get($row, 'amount'));
            $cents += data_get($row, 'direction') === Settlement::DIRECTION_APPLY ? $amount : -$amount;
        }

        return $cents;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{cost_source_type: string, cost_source_id: string, amount: string}>
     */
    private function validateAllocations(Project $project, array $data, int $amountCents): array
    {
        $lines = [];
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
            $this->expenses->requireCostSourceOf($project, $type, $id);
            $lines[] = ['cost_source_type' => $type, 'cost_source_id' => $id, 'amount' => TreasuryBalanceService::fromCents($cents)];
            $total += $cents;
        }
        if ($lines === []) {
            throw new TreasuryRuleViolation('Quyết toán phải gắn với ít nhất một chi phí.', 'allocations');
        }
        if ($total !== $amountCents) {
            throw new TreasuryRuleViolation('Tổng phần gắn chi phí phải bằng đúng số tiền quyết toán.', 'allocations');
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: TreasuryWallet, 1: TreasuryFinancialParty}
     */
    private function validateDraft(Project $project, User $actor, array $data): array
    {
        $wallet = $this->posting->projectWallet($project, (string) ($data['source_wallet_id'] ?? ''), 'source_wallet_id');
        if (!Gate::forUser($actor)->allows('treasury.transfer-from-wallet', $wallet)) {
            throw new TreasuryRuleViolation('Bạn chỉ được chi từ ví mình đang giữ.', 'source_wallet_id');
        }
        $party = $this->posting->tenantParty($project, (string) ($data['destination_party_id'] ?? ''), 'destination_party_id');
        if (TreasuryBalanceService::toCents((string) ($data['amount'] ?? '0')) <= 0) {
            throw new TreasuryRuleViolation('Số tiền tạm ứng phải lớn hơn 0.', 'amount');
        }

        return [$wallet, $party];
    }

    private function advanceOf(Project $project, Document $document): Advance
    {
        $this->requireAdvanceOf($project, $document);
        $advance = $this->advanceRow($document);
        if ($advance === null || !in_array($document->status, self::POSTED, true)) {
            throw new TreasuryRuleViolation('Tạm ứng chưa được chi hoặc đã bị đảo.');
        }

        return $advance;
    }

    /** Lock class 3 (v17 §11). */
    private function lockAdvance(Advance $advance): void
    {
        Advance::query()->whereKey($advance->id)->lockForUpdate()->first();
    }

    /** Lock class 5; a reversed advance takes no more settlement events. */
    private function lockDocument(Advance $advance): Document
    {
        /** @var Document $locked */
        $locked = Document::query()->whereKey($advance->originating_financial_document_id)->lockForUpdate()->firstOrFail();
        if (!in_array($locked->status, self::POSTED, true)) {
            throw new TreasuryRuleViolation('Tạm ứng đã bị đảo.');
        }

        return $locked;
    }

    private function requireOpenRequest(Document $document): Approval
    {
        $request = $this->openRequest($document);
        if ($request === null) {
            throw new TreasuryRuleViolation('Không có đề nghị quyết toán nào đang chờ duyệt.');
        }

        return $request;
    }

    /**
     * A settlement event on the advance's approval log; the document status
     * does not move.
     *
     * @param array<string, mixed> $context
     */
    private function event(User $actor, Document $document, string $event, ?string $note, array $context): Approval
    {
        return Approval::query()->create([
            'tenant_id' => (string) $document->tenant_id,
            'financial_document_id' => (string) $document->id,
            'event' => $event,
            'from_status' => $document->status,
            'to_status' => $document->status,
            'actor_id' => (string) $actor->id,
            'note' => $note,
            'context' => $context,
        ]);
    }

    private function requireAdvanceOf(Project $project, Document $document): void
    {
        if ($document->document_type !== Document::TYPE_ADVANCE
            || (string) $document->project_id !== (string) $project->id
            || (string) $document->tenant_id !== (string) $project->tenant_id) {
            throw new TreasuryRuleViolation('Tạm ứng không thuộc dự án này.');
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
            'transaction_date' => (string) ($data['transaction_date'] ?? now()->toDateString()),
            'reference' => $reference === '' ? null : $reference,
            'description' => $description === '' ? null : $description,
        ];
    }

    private static function money(string $amount): string
    {
        return TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents($amount));
    }
}
