<?php declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-064 — Treasury S2 ledger engine (direct posting path only).
 *
 * Restates GAP-037 v17: closed status graph (§2.1a), direct posting matrix
 * (§5a), immutable ledger entries with a unique original_posting_key (§5),
 * document reversal (§2.2/§2.2a), replacement link (§2.2c), same-project
 * integrity (§15). Approved Gate 2 additions: immediate posting for funding,
 * owner contribution, transfers and adjustments; a negative-balance guard on
 * transfers and decrease adjustments that locks the source wallet first (lock
 * class 0, before v17's 1→6 order); reversals are exempt from that guard.
 */
class TreasuryPostingService
{
    /** Document types S2 may create and reverse. */
    public const REVERSIBLE_TYPES = [
        Document::TYPE_FUNDING,
        Document::TYPE_OWNER_CONTRIBUTION,
        Document::TYPE_INTERNAL_TRANSFER,
        Document::TYPE_ADJUSTMENT,
    ];

    /** The closed graph a directly posted document walks in one transaction (§2.1a). */
    private const IMMEDIATE_POSTING_PATH = [
        Document::STATUS_DRAFT,
        Document::STATUS_SUBMITTED,
        Document::STATUS_APPROVED,
        Document::STATUS_POSTED_UNRECONCILED,
    ];

    public function __construct(private readonly TreasuryBalanceService $balances)
    {
    }

    /**
     * Funding or owner contribution: party → project wallet, posted at once.
     *
     * @param array{document_type: string, source_party_id: string, destination_wallet_id: string, amount: string|float|int, transaction_date: string, reference?: ?string, description?: ?string} $data
     */
    public function declareFunding(Project $project, User $actor, array $data, bool $confirmDuplicate = false): Document
    {
        $type = $data['document_type'];
        if (!in_array($type, [Document::TYPE_FUNDING, Document::TYPE_OWNER_CONTRIBUTION], true)) {
            throw new TreasuryRuleViolation('Loại khoản nhận không hợp lệ.', 'document_type');
        }

        $party = $this->tenantParty($project, $data['source_party_id'], 'source_party_id');
        if ($type === Document::TYPE_OWNER_CONTRIBUTION && $party->party_type !== 'owner') {
            throw new TreasuryRuleViolation('Góp vốn chủ phải đến từ đối tác loại "Chủ doanh nghiệp".', 'source_party_id');
        }
        $wallet = $this->projectWallet($project, $data['destination_wallet_id'], 'destination_wallet_id');

        if (!$confirmDuplicate) {
            $this->guardDuplicate($project, $type, $data, sourcePartyId: (string) $party->id, destinationWalletId: (string) $wallet->id);
        }

        return DB::transaction(function () use ($project, $actor, $data, $type, $party, $wallet): Document {
            $document = $this->createDocument($project, $actor, [
                'document_type' => $type,
                'amount' => $data['amount'],
                'source_party_id' => (string) $party->id,
                'destination_wallet_id' => (string) $wallet->id,
            ] + $this->commonFields($data));

            $this->postEntries($document, [[(string) $wallet->id, Entry::DIRECTION_CREDIT, $type]]);
            $this->audit($actor, $document, 'treasury.' . $type . '.posted');

            return $document;
        });
    }

    /**
     * Internal transfer between two different wallets of the same project.
     *
     * @param array{source_wallet_id: string, destination_wallet_id: string, amount: string|float|int, transaction_date: string, reference?: ?string, description?: ?string} $data
     */
    public function transfer(Project $project, User $actor, array $data): Document
    {
        $source = $this->projectWallet($project, $data['source_wallet_id'], 'source_wallet_id');
        $destination = $this->projectWallet($project, $data['destination_wallet_id'], 'destination_wallet_id');
        if ((string) $source->id === (string) $destination->id) {
            throw new TreasuryRuleViolation('Ví nguồn và ví đích phải khác nhau.', 'destination_wallet_id');
        }
        if (!Gate::forUser($actor)->allows('treasury.transfer-from-wallet', $source)) {
            throw new TreasuryRuleViolation('Bạn chỉ được chuyển tiền từ ví mình đang giữ.', 'source_wallet_id');
        }

        return DB::transaction(function () use ($project, $actor, $data, $source, $destination): Document {
            $this->lockAndRequireBalance($source, (string) $data['amount'], 'source_wallet_id');

            $document = $this->createDocument($project, $actor, [
                'document_type' => Document::TYPE_INTERNAL_TRANSFER,
                'amount' => $data['amount'],
                'source_wallet_id' => (string) $source->id,
                'destination_wallet_id' => (string) $destination->id,
            ] + $this->commonFields($data));

            $this->postEntries($document, [
                [(string) $source->id, Entry::DIRECTION_DEBIT, 'transfer_out'],
                [(string) $destination->id, Entry::DIRECTION_CREDIT, 'transfer_in'],
            ]);
            $this->audit($actor, $document, 'treasury.internal_transfer.posted');

            return $document;
        });
    }

    /**
     * Adjustment of one wallet; the reason (description) is mandatory.
     *
     * @param array{wallet_id: string, direction: string, amount: string|float|int, transaction_date: string, description: string, reference?: ?string} $data
     */
    public function adjust(Project $project, User $actor, array $data): Document
    {
        $wallet = $this->projectWallet($project, $data['wallet_id'], 'wallet_id');
        $increase = $data['direction'] === 'increase';
        if (!$increase && $data['direction'] !== 'decrease') {
            throw new TreasuryRuleViolation('Hướng điều chỉnh không hợp lệ.', 'direction');
        }
        if (trim((string) ($data['description'] ?? '')) === '') {
            throw new TreasuryRuleViolation('Điều chỉnh bắt buộc ghi lý do.', 'description');
        }

        return DB::transaction(function () use ($project, $actor, $data, $wallet, $increase): Document {
            if (!$increase) {
                $this->lockAndRequireBalance($wallet, (string) $data['amount'], 'amount');
            }

            $document = $this->createDocument($project, $actor, [
                'document_type' => Document::TYPE_ADJUSTMENT,
                'amount' => $data['amount'],
                $increase ? 'destination_wallet_id' : 'source_wallet_id' => (string) $wallet->id,
            ] + $this->commonFields($data));

            $this->postEntries($document, [[
                (string) $wallet->id,
                $increase ? Entry::DIRECTION_CREDIT : Entry::DIRECTION_DEBIT,
                $increase ? 'adjustment_increase' : 'adjustment_decrease',
            ]]);
            $this->audit($actor, $document, 'treasury.adjustment.posted');

            return $document;
        });
    }

    /**
     * Document-level reversal (v17 §2.2/§2.2a), posted directly; the original
     * flips to `reversed` in the same transaction. Exempt from the
     * negative-balance guard (approved Gate 2 Option A).
     *
     * @param array{transaction_date: string, description: string, reference?: ?string} $data
     */
    public function reverse(Project $project, User $actor, Document $original, array $data): Document
    {
        if ((string) $original->project_id !== (string) $project->id || (string) $original->tenant_id !== (string) $project->tenant_id) {
            throw new TreasuryRuleViolation('Chứng từ không thuộc dự án này.');
        }
        if (trim((string) ($data['description'] ?? '')) === '') {
            throw new TreasuryRuleViolation('Đảo bút toán bắt buộc ghi lý do.', 'description');
        }

        return DB::transaction(function () use ($project, $actor, $original, $data): Document {
            /** @var Document $locked */
            $locked = Document::query()->whereKey($original->id)->lockForUpdate()->firstOrFail();

            if ($locked->document_type === Document::TYPE_REVERSAL) {
                throw new TreasuryRuleViolation('Không thể đảo một bút toán đảo.');
            }
            if (!in_array($locked->document_type, self::REVERSIBLE_TYPES, true)) {
                throw new TreasuryRuleViolation('Loại chứng từ này chưa đảo được ở bước hiện tại.');
            }
            if (!in_array($locked->status, [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED], true)) {
                throw new TreasuryRuleViolation('Chỉ đảo được chứng từ đã ghi sổ và chưa bị đảo.');
            }
            if ($locked->posting_path !== Document::POSTING_PATH_DIRECT) {
                throw new TreasuryRuleViolation('Chứng từ đi qua trung gian chưa đảo được ở bước hiện tại.');
            }
            if (Document::query()->where('reversed_document_id', (string) $locked->id)->exists()) {
                throw new TreasuryRuleViolation('Chứng từ này đã có bút toán đảo.');
            }

            $reversal = $this->createDocument($project, $actor, [
                'document_type' => Document::TYPE_REVERSAL,
                'amount' => $locked->amount,
                'source_wallet_id' => $locked->destination_wallet_id,
                'destination_wallet_id' => $locked->source_wallet_id,
                'source_party_id' => $locked->destination_party_id,
                'destination_party_id' => $locked->source_party_id,
                'reversed_document_id' => (string) $locked->id,
            ] + $this->commonFields($data));

            // Lower id first when both document rows are held (v17 §2.2a / §11 class 5).
            Document::query()
                ->whereIn('id', [(string) $locked->id, (string) $reversal->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $entries = Entry::query()
                ->where('source_financial_document_id', (string) $locked->id)
                ->orderBy('id')
                ->get();
            $this->postEntries($reversal, $entries->map(static fn (Entry $entry): array => [
                (string) $entry->wallet_id,
                $entry->direction === Entry::DIRECTION_CREDIT ? Entry::DIRECTION_DEBIT : Entry::DIRECTION_CREDIT,
                'reversal',
            ])->all());

            $before = $locked->status;
            $locked->status = Document::STATUS_REVERSED;
            $locked->save();

            $this->audit($actor, $reversal, 'treasury.reversal.posted', ['reversed_document_id' => (string) $locked->id]);
            $this->audit($actor, $locked, 'treasury.document.reversed', [
                'from_status' => $before,
                'reversal_document_id' => (string) $reversal->id,
            ]);

            return $reversal;
        });
    }

    /**
     * v17 §2.2c — audit-only link from an erroneous reversal to the corrective
     * document; set once, same project, no economic effect.
     */
    public function linkReplacement(Project $project, User $actor, Document $reversal, Document $replacement): Document
    {
        if ($reversal->document_type !== Document::TYPE_REVERSAL) {
            throw new TreasuryRuleViolation('Chỉ bút toán đảo mới gắn được chứng từ thay thế.');
        }
        if ($reversal->replacement_document_id !== null) {
            throw new TreasuryRuleViolation('Bút toán đảo này đã có chứng từ thay thế.');
        }
        foreach ([$reversal, $replacement] as $document) {
            if ((string) $document->project_id !== (string) $project->id || (string) $document->tenant_id !== (string) $project->tenant_id) {
                throw new TreasuryRuleViolation('Chứng từ không thuộc dự án này.', 'replacement_document_id');
            }
        }
        if ($replacement->document_type === Document::TYPE_REVERSAL
            || (string) $replacement->id === (string) $reversal->reversed_document_id) {
            throw new TreasuryRuleViolation('Chứng từ thay thế phải là một chứng từ mới, không phải bút toán đảo hay chứng từ gốc.', 'replacement_document_id');
        }

        return DB::transaction(function () use ($actor, $reversal, $replacement): Document {
            $reversal->replacement_document_id = (string) $replacement->id;
            $reversal->save();
            $this->audit($actor, $reversal, 'treasury.reversal.replacement_linked', [
                'replacement_document_id' => (string) $replacement->id,
            ]);

            return $reversal;
        });
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
            'transaction_date' => $data['transaction_date'],
            'reference' => $reference === '' ? null : $reference,
            'description' => $description === '' ? null : $description,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createDocument(Project $project, User $actor, array $attributes): Document
    {
        $now = now();

        return Document::query()->create($attributes + [
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            // Immediate posting walks draft → submitted → approved →
            // posted_unreconciled inside this transaction (v17 §2.1a); only the
            // final state is persisted, the walk is recorded in the audit row.
            'status' => Document::STATUS_POSTED_UNRECONCILED,
            'posting_path' => Document::POSTING_PATH_DIRECT,
            'created_by' => (string) $actor->id,
            'approved_by' => (string) $actor->id,
            'posted_at' => $now,
        ]);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $legs wallet id, direction, entry type
     */
    private function postEntries(Document $document, array $legs): void
    {
        foreach ($legs as [$walletId, $direction, $entryType]) {
            Entry::query()->create([
                'tenant_id' => (string) $document->tenant_id,
                'source_financial_document_id' => (string) $document->id,
                'wallet_id' => $walletId,
                'direction' => $direction,
                'amount' => $document->amount,
                'entry_type' => $entryType,
                'posted_at' => $document->posted_at,
                'original_posting_key' => $document->id . ':' . $walletId . ':' . $direction,
            ]);
        }
    }

    /**
     * Lock class 0 (approved Gate 2 Option A): the source wallet row is locked
     * before any v17 class 1–6 lock; the balance is then read with a shared
     * lock on that wallet's ledger rows (class 4, still before the class-5
     * document insert) and must cover the amount.
     */
    private function lockAndRequireBalance(TreasuryWallet $wallet, string $amount, string $field): void
    {
        TreasuryWallet::query()->whereKey($wallet->id)->lockForUpdate()->first();

        if (TreasuryBalanceService::toCents($this->balances->lockedWalletBalance($wallet)) < TreasuryBalanceService::toCents($amount)) {
            throw new TreasuryRuleViolation('Số dư ví "' . $wallet->name . '" không đủ.', $field);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function guardDuplicate(Project $project, string $type, array $data, string $sourcePartyId, string $destinationWalletId): void
    {
        $reference = trim((string) ($data['reference'] ?? ''));

        $existing = Document::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('document_type', $type)
            ->whereIn('status', [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED])
            ->where('amount', $this->money((string) $data['amount']))
            ->whereDate('transaction_date', (string) $data['transaction_date'])
            ->where('source_party_id', $sourcePartyId)
            ->where('destination_wallet_id', $destinationWalletId)
            ->when(
                $reference === '',
                static fn ($query) => $query->whereNull('reference'),
                static fn ($query) => $query->where('reference', $reference)
            )
            ->first();

        if ($existing !== null) {
            throw new TreasuryDuplicateSuspected($existing);
        }
    }

    private function projectWallet(Project $project, string $walletId, string $field): TreasuryWallet
    {
        /** @var TreasuryWallet|null $wallet */
        $wallet = TreasuryWallet::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($walletId)
            ->first();

        if ($wallet === null) {
            throw new TreasuryRuleViolation('Ví không thuộc dự án này.', $field);
        }

        return $wallet;
    }

    private function tenantParty(Project $project, string $partyId, string $field): TreasuryFinancialParty
    {
        /** @var TreasuryFinancialParty|null $party */
        $party = TreasuryFinancialParty::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->whereKey($partyId)
            ->first();

        if ($party === null) {
            throw new TreasuryRuleViolation('Đối tác không hợp lệ.', $field);
        }

        return $party;
    }

    private function money(string $amount): string
    {
        return TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents($amount));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function audit(User $actor, Document $document, string $action, array $extra = []): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $document->tenant_id,
            'project_id' => (string) $document->project_id,
            'action' => $action,
            'entity_type' => 'treasury_financial_document',
            'entity_id' => (string) $document->id,
            'new_data' => [
                'document_type' => $document->document_type,
                'status' => $document->status,
                'amount' => (string) $document->amount,
                'transaction_date' => $document->transaction_date?->toDateString(),
                'reference' => $document->reference,
                'description' => $document->description,
                'status_path' => $action === 'treasury.document.reversed' ? null : self::IMMEDIATE_POSTING_PATH,
            ] + $extra,
        ]);
    }
}
