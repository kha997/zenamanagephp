<?php declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryReconciliation as Reconciliation;
use App\Models\Treasury\TreasuryReconciliationEntry as RecEntry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GAP-067 — Treasury S4a reconciliation (GAP-037 v17 §12, approved Gate 2 Option A).
 *
 * Reconciliation is per ledger entry, per wallet. "Active apply" = an `apply`
 * row no `reverse` row points to; at most one per ledger entry. There is no
 * database constraint for that, so every write locks the ledger-entry rows
 * (v17 §11 class 4, id ascending) and reads the reconciliation rows with a
 * locking read before deciding, then locks the documents (class 5, id
 * ascending) to move their status (§12.1 promotion, §12.2 regression). The
 * class-4 set covers every entry of each affected document, so the two sides
 * of an internal transfer reconciled at the same time serialise instead of
 * each missing the other's apply. Balances never change: both posted statuses
 * already count.
 */
class TreasuryReconciliationService
{
    public const TYPE_BANK_STATEMENT = 'bank_statement';
    public const TYPE_CASH_COUNT = 'cash_count';
    public const TYPE_VOUCHER = 'voucher';

    public const TYPES = [self::TYPE_BANK_STATEMENT, self::TYPE_CASH_COUNT, self::TYPE_VOUCHER];

    /**
     * Reconcile (apply) the given ledger entries of one wallet. All-or-nothing.
     *
     * @param array{reconciliation_type: string, external_reference?: ?string, reconciled_at: string, ledger_entry_ids: array<int, string>} $data
     */
    public function reconcile(Project $project, User $actor, TreasuryWallet $wallet, array $data): Reconciliation
    {
        $this->requireProjectWallet($project, $wallet);

        $type = (string) $data['reconciliation_type'];
        if (!in_array($type, self::TYPES, true)) {
            throw new TreasuryRuleViolation('Loại đối soát không hợp lệ.', 'reconciliation_type');
        }
        $reference = trim((string) ($data['external_reference'] ?? ''));
        if ($reference === '' && $type !== self::TYPE_CASH_COUNT) {
            throw new TreasuryRuleViolation('Sao kê ngân hàng và chứng từ bắt buộc có số tham chiếu.', 'external_reference');
        }
        $date = $this->parseDate((string) $data['reconciled_at']);
        if ($date === null) {
            throw new TreasuryRuleViolation('Ngày đối soát không hợp lệ.', 'reconciled_at');
        }
        if ($date->greaterThan(Carbon::today())) {
            throw new TreasuryRuleViolation('Ngày đối soát không được ở tương lai.', 'reconciled_at');
        }
        $ids = array_values(array_unique(array_map('strval', $data['ledger_entry_ids'])));
        if ($ids === []) {
            throw new TreasuryRuleViolation('Chọn ít nhất một giao dịch để đối soát.', 'ledger_entry_ids');
        }

        return DB::transaction(function () use ($project, $actor, $wallet, $type, $reference, $date, $ids): Reconciliation {
            $locked = $this->lockEntries($ids);
            foreach ($ids as $id) {
                $entry = $locked->get($id);
                if ($entry === null
                    || (string) data_get($entry, 'tenant_id') !== (string) $wallet->tenant_id
                    || (string) data_get($entry, 'wallet_id') !== (string) $wallet->id) {
                    throw new TreasuryRuleViolation('Có giao dịch không thuộc ví này.', 'ledger_entry_ids');
                }
                if (data_get($entry, 'source_financial_document_id') === null) {
                    throw new TreasuryRuleViolation('Bút toán của tuyến chuyển tiền chưa đối soát được ở bước hiện tại.', 'ledger_entry_ids');
                }
            }
            if ($this->activeApplies($ids)->isNotEmpty()) {
                throw new TreasuryRuleViolation('Có giao dịch đã được đối soát.', 'ledger_entry_ids');
            }

            $reconciliation = Reconciliation::query()->create([
                'tenant_id' => (string) $wallet->tenant_id,
                'wallet_id' => (string) $wallet->id,
                'reconciliation_type' => $type,
                'external_reference' => $reference === '' ? null : $reference,
                'reconciled_at' => $date,
                'reconciled_by' => (string) $actor->id,
            ]);
            foreach ($ids as $id) {
                RecEntry::query()->create([
                    'tenant_id' => (string) $wallet->tenant_id,
                    'reconciliation_id' => (string) $reconciliation->id,
                    'ledger_entry_id' => $id,
                    'direction' => RecEntry::DIRECTION_APPLY,
                    'actor_id' => (string) $actor->id,
                ]);
            }

            $moved = $this->settleDocuments($this->documentIdsOf($locked, $ids));
            $this->audit($actor, $project, $reconciliation, 'treasury.reconciliation.applied', [
                'wallet_id' => (string) $wallet->id,
                'reconciliation_type' => $type,
                'external_reference' => $reconciliation->external_reference,
                'reconciled_at' => $date->toDateString(),
                'ledger_entry_ids' => $ids,
                'documents_moved' => $moved,
            ]);

            return $reconciliation;
        });
    }

    /** Undo one active apply line; the reason goes to audit_logs. */
    public function undoEntry(Project $project, User $actor, RecEntry $line, string $reason): RecEntry
    {
        $reconciliation = $this->projectReconciliation($project, (string) $line->reconciliation_id);
        if ($reconciliation === null || (string) $line->tenant_id !== (string) $project->tenant_id) {
            throw new TreasuryRuleViolation('Dòng đối soát không thuộc dự án này.', 'reconciliation_entry');
        }
        if ($line->direction !== RecEntry::DIRECTION_APPLY) {
            throw new TreasuryRuleViolation('Chỉ gỡ được dòng đối soát còn hiệu lực.', 'reconciliation_entry');
        }
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($project, $actor, $reconciliation, $line, $reason): RecEntry {
            $ledgerEntryId = (string) $line->ledger_entry_id;
            $locked = $this->lockEntries([$ledgerEntryId]);
            $active = $this->activeApplies([$ledgerEntryId])->first();
            if ($active === null || (string) data_get($active, 'id') !== (string) $line->id) {
                throw new TreasuryRuleViolation('Chỉ gỡ được dòng đối soát còn hiệu lực.', 'reconciliation_entry');
            }

            $reverses = $this->writeReverses($actor, collect([$active]));
            $moved = $this->settleDocuments($this->documentIdsOf($locked, [$ledgerEntryId]));
            $this->audit($actor, $project, $reconciliation, 'treasury.reconciliation.undone', [
                'wallet_id' => (string) $reconciliation->wallet_id,
                'scope' => 'line',
                'ledger_entry_ids' => [$ledgerEntryId],
                'reverse_entry_ids' => $reverses->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
                'documents_moved' => $moved,
                'reason' => $reason,
            ]);

            /** @var RecEntry $reverse */
            $reverse = $reverses->first();

            return $reverse;
        });
    }

    /**
     * Undo every still-active apply of a reconciliation in one transaction.
     *
     * @return Collection<int, RecEntry> the reverse rows written
     */
    public function undoReconciliation(Project $project, User $actor, Reconciliation $reconciliation, string $reason): Collection
    {
        if ($this->projectReconciliation($project, (string) $reconciliation->id) === null) {
            throw new TreasuryRuleViolation('Lần đối soát không thuộc dự án này.', 'reconciliation');
        }
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($project, $actor, $reconciliation, $reason): Collection {
            $ledgerEntryIds = RecEntry::query()
                ->withoutGlobalScopes()
                ->where('reconciliation_id', (string) $reconciliation->id)
                ->where('direction', RecEntry::DIRECTION_APPLY)
                ->pluck('ledger_entry_id')
                ->map(static fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all();
            $locked = $this->lockEntries($ledgerEntryIds);
            $active = $this->activeApplies($ledgerEntryIds)
                ->filter(static fn ($row): bool => (string) data_get($row, 'reconciliation_id') === (string) $reconciliation->id)
                ->values();
            if ($active->isEmpty()) {
                throw new TreasuryRuleViolation('Lần đối soát này không còn dòng nào để gỡ.', 'reconciliation');
            }

            $reverses = $this->writeReverses($actor, $active);
            $undoneEntryIds = $active->pluck('ledger_entry_id')->map(static fn ($id): string => (string) $id)->all();
            $moved = $this->settleDocuments($this->documentIdsOf($locked, $undoneEntryIds));
            $this->audit($actor, $project, $reconciliation, 'treasury.reconciliation.undone', [
                'wallet_id' => (string) $reconciliation->wallet_id,
                'scope' => 'reconciliation',
                'ledger_entry_ids' => $undoneEntryIds,
                'reverse_entry_ids' => $reverses->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
                'documents_moved' => $moved,
                'reason' => $reason,
            ]);

            return $reverses;
        });
    }

    /**
     * Wallet balance split into reconciled (entries with an active apply) and unreconciled.
     *
     * @return array{balance: string, reconciled: string, unreconciled: string}
     */
    public function walletSummary(TreasuryWallet $wallet): array
    {
        return $this->walletSummaries([(string) $wallet->id], (string) $wallet->tenant_id)[(string) $wallet->id];
    }

    /**
     * @param list<string> $walletIds
     * @return array<string, array{balance: string, reconciled: string, unreconciled: string}>
     */
    public function walletSummaries(array $walletIds, string $tenantId): array
    {
        $result = [];
        if ($walletIds === []) {
            return $result;
        }
        $entries = Entry::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('wallet_id', $walletIds)
            ->get(['id', 'wallet_id', 'direction', 'amount']);
        $reconciledIds = array_flip($this->activeApplyEntryIds($entries->pluck('id')->map(static fn ($id): string => (string) $id)->all()));

        $totals = array_fill_keys($walletIds, ['balance' => 0, 'reconciled' => 0]);
        foreach ($entries as $entry) {
            $walletId = (string) data_get($entry, 'wallet_id');
            $cents = TreasuryBalanceService::toCents((string) data_get($entry, 'amount'));
            $signed = data_get($entry, 'direction') === Entry::DIRECTION_CREDIT ? $cents : -$cents;
            $totals[$walletId]['balance'] += $signed;
            if (isset($reconciledIds[(string) data_get($entry, 'id')])) {
                $totals[$walletId]['reconciled'] += $signed;
            }
        }
        foreach ($totals as $walletId => $total) {
            $result[$walletId] = [
                'balance' => TreasuryBalanceService::fromCents($total['balance']),
                'reconciled' => TreasuryBalanceService::fromCents($total['reconciled']),
                'unreconciled' => TreasuryBalanceService::fromCents($total['balance'] - $total['reconciled']),
            ];
        }

        return $result;
    }

    /**
     * Document-sourced ledger entries of the wallet with no active apply.
     *
     * @return list<array{ledger_entry_id: string, document_id: string, transaction_date: ?string, document_type: string, document_status: string, reference: ?string, description: ?string, direction: string, amount: string}>
     */
    public function unreconciledEntries(TreasuryWallet $wallet): array
    {
        $entries = Entry::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $wallet->tenant_id)
            ->where('wallet_id', (string) $wallet->id)
            ->whereNotNull('source_financial_document_id')
            ->orderBy('posted_at')
            ->orderBy('id')
            ->get();
        $reconciledIds = array_flip($this->activeApplyEntryIds($entries->pluck('id')->map(static fn ($id): string => (string) $id)->all()));
        $documents = Document::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $entries->pluck('source_financial_document_id')->unique()->values()->all())
            ->get()
            ->keyBy(static fn ($doc): string => (string) $doc->getKey());

        $rows = [];
        foreach ($entries as $entry) {
            if (isset($reconciledIds[(string) data_get($entry, 'id')])) {
                continue;
            }
            $doc = $documents->get((string) data_get($entry, 'source_financial_document_id'));
            $rows[] = [
                'ledger_entry_id' => (string) data_get($entry, 'id'),
                'document_id' => (string) data_get($entry, 'source_financial_document_id'),
                'transaction_date' => data_get($doc, 'transaction_date')?->toDateString(),
                'document_type' => (string) data_get($doc, 'document_type'),
                'document_status' => (string) data_get($doc, 'status'),
                'reference' => data_get($doc, 'reference'),
                'description' => data_get($doc, 'description'),
                'direction' => (string) data_get($entry, 'direction'),
                'amount' => TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents((string) data_get($entry, 'amount'))),
            ];
        }

        return $rows;
    }

    /**
     * Reconciliation history of the project's wallets (optionally one wallet), newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function history(Project $project, ?TreasuryWallet $wallet = null): array
    {
        $walletIds = TreasuryWallet::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->when($wallet !== null, static fn ($query) => $query->whereKey((string) data_get($wallet, 'id')))
            ->pluck('name', 'id');

        $reconciliations = Reconciliation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->whereIn('wallet_id', $walletIds->keys()->all())
            ->orderByDesc('reconciled_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        if ($reconciliations->isEmpty()) {
            return [];
        }
        $recIds = $reconciliations->map(static fn ($rec): string => (string) $rec->getKey())->all();

        $lines = RecEntry::query()
            ->withoutGlobalScopes()
            ->whereIn('reconciliation_id', $recIds)
            ->orderBy('id')
            ->get();
        $entries = Entry::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $lines->pluck('ledger_entry_id')->unique()->values()->all())
            ->get()
            ->keyBy(static fn ($entry): string => (string) $entry->getKey());
        $documents = Document::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $entries->pluck('source_financial_document_id')->filter()->unique()->values()->all())
            ->get()
            ->keyBy(static fn ($doc): string => (string) $doc->getKey());
        $userIds = $lines->pluck('actor_id')->merge($reconciliations->pluck('reconciled_by'))->unique()->values()->all();
        $userNames = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        $reasons = [];
        $undoLogs = AuditLog::query()
            ->where('entity_type', 'treasury_reconciliation')
            ->whereIn('entity_id', $recIds)
            ->where('action', 'treasury.reconciliation.undone')
            ->get();
        foreach ($undoLogs as $log) {
            foreach ((array) data_get($log, 'new_data.reverse_entry_ids', []) as $reverseId) {
                $reasons[(string) $reverseId] = (string) data_get($log, 'new_data.reason');
            }
        }

        $reverseOf = [];
        foreach ($lines as $line) {
            if (data_get($line, 'direction') === RecEntry::DIRECTION_REVERSE) {
                $reverseOf[(string) data_get($line, 'reverses_reconciliation_entry_id')] = $line;
            }
        }

        $history = [];
        foreach ($reconciliations as $rec) {
            $recLines = [];
            $active = false;
            foreach ($lines->where('reconciliation_id', (string) $rec->getKey())->where('direction', RecEntry::DIRECTION_APPLY) as $line) {
                $entry = $entries->get((string) data_get($line, 'ledger_entry_id'));
                $doc = $documents->get((string) data_get($entry, 'source_financial_document_id'));
                $undo = $reverseOf[(string) data_get($line, 'id')] ?? null;
                $active = $active || $undo === null;
                $recLines[] = [
                    'id' => (string) data_get($line, 'id'),
                    'ledger_entry_id' => (string) data_get($line, 'ledger_entry_id'),
                    'document_id' => data_get($entry, 'source_financial_document_id'),
                    'transaction_date' => data_get($doc, 'transaction_date')?->toDateString(),
                    'document_type' => data_get($doc, 'document_type'),
                    'reference' => data_get($doc, 'reference'),
                    'direction' => data_get($entry, 'direction'),
                    'amount' => TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents((string) data_get($entry, 'amount'))),
                    'active' => $undo === null,
                    'undone_at' => data_get($undo, 'created_at')?->toIso8601String(),
                    'undone_by' => $undo === null ? null : ($userNames[(string) data_get($undo, 'actor_id')] ?? null),
                    'undo_reason' => $undo === null ? null : ($reasons[(string) data_get($undo, 'id')] ?? null),
                ];
            }
            $history[] = [
                'id' => (string) $rec->getKey(),
                'wallet_id' => (string) data_get($rec, 'wallet_id'),
                'wallet_name' => $walletIds[(string) data_get($rec, 'wallet_id')] ?? null,
                'reconciliation_type' => (string) data_get($rec, 'reconciliation_type'),
                'external_reference' => data_get($rec, 'external_reference'),
                'reconciled_at' => data_get($rec, 'reconciled_at')?->toDateString(),
                'reconciled_by' => $userNames[(string) data_get($rec, 'reconciled_by')] ?? null,
                'active' => $active,
                'lines' => $recLines,
            ];
        }

        return $history;
    }

    /** A reconciliation of one of the project's wallets, or null. */
    public function projectReconciliation(Project $project, string $id): ?Reconciliation
    {
        /** @var Reconciliation|null $found */
        $found = Reconciliation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->whereKey($id)
            ->whereIn('wallet_id', TreasuryWallet::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $project->tenant_id)
                ->where('project_id', (string) $project->id)
                ->select('id'))
            ->first();

        return $found;
    }

    /**
     * Class 4 (v17 §11): lock the given ledger entries together with every
     * other entry of the same documents, in one statement, id ascending.
     *
     * @param list<string> $ids
     * @return Collection<string, Entry> keyed by id
     */
    private function lockEntries(array $ids): Collection
    {
        $documentIds = Entry::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->whereNotNull('source_financial_document_id')
            ->pluck('source_financial_document_id')
            ->unique()
            ->values()
            ->all();
        $allIds = $documentIds === [] ? $ids : array_values(array_unique(array_merge($ids, Entry::query()
            ->withoutGlobalScopes()
            ->whereIn('source_financial_document_id', $documentIds)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all())));
        sort($allIds);

        return Entry::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $allIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn ($entry): string => (string) $entry->getKey());
    }

    /**
     * Active apply rows of the given ledger entries, read with a lock so the
     * latest committed rows are seen whatever the transaction's snapshot.
     *
     * @param list<string> $ledgerEntryIds
     * @return Collection<int, RecEntry>
     */
    private function activeApplies(array $ledgerEntryIds): Collection
    {
        $rows = RecEntry::query()
            ->withoutGlobalScopes()
            ->whereIn('ledger_entry_id', $ledgerEntryIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $reversed = array_flip($rows->pluck('reverses_reconciliation_entry_id')->filter()->map(static fn ($id): string => (string) $id)->all());

        return $rows
            ->filter(static fn ($row): bool => data_get($row, 'direction') === RecEntry::DIRECTION_APPLY && !isset($reversed[(string) data_get($row, 'id')]))
            ->values();
    }

    /**
     * Non-locking variant for read-only views.
     *
     * @param list<string> $ledgerEntryIds
     * @return list<string>
     */
    private function activeApplyEntryIds(array $ledgerEntryIds): array
    {
        if ($ledgerEntryIds === []) {
            return [];
        }
        $active = [];
        foreach (array_chunk($ledgerEntryIds, 500) as $chunk) {
            $rows = RecEntry::query()->withoutGlobalScopes()->whereIn('ledger_entry_id', $chunk)->get(['id', 'ledger_entry_id', 'direction', 'reverses_reconciliation_entry_id']);
            $reversed = array_flip($rows->pluck('reverses_reconciliation_entry_id')->filter()->map(static fn ($id): string => (string) $id)->all());
            foreach ($rows as $row) {
                if (data_get($row, 'direction') === RecEntry::DIRECTION_APPLY && !isset($reversed[(string) data_get($row, 'id')])) {
                    $active[] = (string) data_get($row, 'ledger_entry_id');
                }
            }
        }

        return $active;
    }

    /**
     * @param Collection<int, RecEntry> $applies
     * @return Collection<int, RecEntry>
     */
    private function writeReverses(User $actor, Collection $applies): Collection
    {
        return $applies->map(static fn ($apply) => RecEntry::query()->create([
            'tenant_id' => (string) data_get($apply, 'tenant_id'),
            'reconciliation_id' => (string) data_get($apply, 'reconciliation_id'),
            'ledger_entry_id' => (string) data_get($apply, 'ledger_entry_id'),
            'direction' => RecEntry::DIRECTION_REVERSE,
            'reverses_reconciliation_entry_id' => (string) data_get($apply, 'id'),
            'actor_id' => (string) $actor->id,
        ]))->values();
    }

    /**
     * Class 5: lock the documents (id ascending) and apply §12.1 / §12.2 to
     * each `direct` one. `reversed` (terminal) never moves.
     *
     * @param list<string> $documentIds
     * @return list<array{document_id: string, from: string, to: string}>
     */
    private function settleDocuments(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }
        sort($documentIds);
        $documents = Document::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $documentIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $moved = [];
        foreach ($documents as $doc) {
            if (data_get($doc, 'posting_path') !== Document::POSTING_PATH_DIRECT
                || !in_array(data_get($doc, 'status'), [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED], true)) {
                continue;
            }
            $entryIds = Entry::query()
                ->withoutGlobalScopes()
                ->where('source_financial_document_id', (string) $doc->getKey())
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->all();
            $covered = $entryIds !== []
                && $this->activeApplies($entryIds)->pluck('ledger_entry_id')->map(static fn ($id): string => (string) $id)->unique()->count() === count($entryIds);
            $target = $covered ? Document::STATUS_POSTED_RECONCILED : Document::STATUS_POSTED_UNRECONCILED;
            $from = (string) data_get($doc, 'status');
            if ($from !== $target) {
                $doc->status = $target;
                $doc->save();
                $moved[] = ['document_id' => (string) $doc->getKey(), 'from' => $from, 'to' => $target];
            }
        }

        return $moved;
    }

    /**
     * @param Collection<string, Entry> $locked
     * @param list<string> $ledgerEntryIds
     * @return list<string>
     */
    private function documentIdsOf(Collection $locked, array $ledgerEntryIds): array
    {
        $ids = [];
        foreach ($ledgerEntryIds as $id) {
            $documentId = data_get($locked->get($id), 'source_financial_document_id');
            if ($documentId !== null) {
                $ids[(string) $documentId] = true;
            }
        }

        return array_keys($ids);
    }

    private function requireProjectWallet(Project $project, TreasuryWallet $wallet): void
    {
        if ((string) $wallet->tenant_id !== (string) $project->tenant_id || (string) $wallet->project_id !== (string) $project->id) {
            throw new TreasuryRuleViolation('Ví không thuộc dự án này.', 'wallet');
        }
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new TreasuryRuleViolation('Gỡ đối soát bắt buộc ghi lý do.', 'reason');
        }

        return $reason;
    }

    private function parseDate(string $value): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $date = Carbon::createFromFormat('Y-m-d', $value);
        if ($date === null || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date->startOfDay();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function audit(User $actor, Project $project, Reconciliation $reconciliation, string $action, array $data): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'action' => $action,
            'entity_type' => 'treasury_reconciliation',
            'entity_id' => (string) $reconciliation->id,
            'new_data' => $data,
        ]);
    }
}
