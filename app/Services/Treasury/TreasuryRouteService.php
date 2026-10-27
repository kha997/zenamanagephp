<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryPaymentRouteLeg as Leg;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * GAP-094 — Treasury S4b-1 via-route payments (GAP-037 v17 §4, §5a, §11;
 * approved Gate 2 Option A).
 *
 * A funding, owner contribution or internal transfer can travel through
 * transit wallets of the project. Creating the route creates its document
 * (`via_route`, `approved`, amount frozen). Each leg is recorded once, as
 * `settled`, and posts its own ledger entries; the document posts
 * (`posted_unreconciled`) only when the route completes: the destination
 * holds the full amount, a transfer's source has given it, every other wallet
 * of the route is back to zero. A wrong leg is reversed, never edited.
 *
 * Lock order (v17 §11 with S2's class 0): leg 0 (from-wallet) → 4 (shared
 * balance read) → 5 (document) → 6 (route); leg reversal 0 (to-wallet) → 4
 * (the route's entries, id ascending, then the shared balance read) → 5 → 6;
 * cancel 5 → 6.
 */
class TreasuryRouteService
{
    public const ROUTE_TYPES = [
        Document::TYPE_FUNDING,
        Document::TYPE_OWNER_CONTRIBUTION,
        Document::TYPE_INTERNAL_TRANSFER,
    ];

    /** The closed graph a via-route document walks at creation (§2.1a). */
    private const CREATION_PATH = [
        Document::STATUS_DRAFT,
        Document::STATUS_SUBMITTED,
        Document::STATUS_APPROVED,
    ];

    public function __construct(private readonly TreasuryPostingService $posting)
    {
    }

    /**
     * @param array{document_type: string, source_party_id?: ?string, source_wallet_id?: ?string, destination_wallet_id: string, amount: string|float|int, transaction_date: string, reference?: ?string, description?: ?string} $data
     */
    public function createRoute(Project $project, User $actor, array $data, bool $confirmDuplicate = false): Route
    {
        $type = (string) $data['document_type'];
        if (!in_array($type, self::ROUTE_TYPES, true)) {
            throw new TreasuryRuleViolation('Chỉ tiền nhận, góp vốn và chuyển nội bộ mới đi qua trung gian được.', 'document_type');
        }
        $destination = $this->posting->projectWallet($project, (string) $data['destination_wallet_id'], 'destination_wallet_id');

        $endpoints = ['destination_wallet_id' => (string) $destination->id];
        if ($type === Document::TYPE_INTERNAL_TRANSFER) {
            $source = $this->posting->projectWallet($project, (string) ($data['source_wallet_id'] ?? ''), 'source_wallet_id');
            if ((string) $source->id === (string) $destination->id) {
                throw new TreasuryRuleViolation('Ví nguồn và ví đích phải khác nhau.', 'destination_wallet_id');
            }
            $endpoints['source_wallet_id'] = (string) $source->id;
        } else {
            $party = $this->posting->tenantParty($project, (string) ($data['source_party_id'] ?? ''), 'source_party_id');
            if ($type === Document::TYPE_OWNER_CONTRIBUTION && $party->party_type !== 'owner') {
                throw new TreasuryRuleViolation('Góp vốn chủ phải đến từ đối tác loại "Chủ doanh nghiệp".', 'source_party_id');
            }
            $endpoints['source_party_id'] = (string) $party->id;
            if (!$confirmDuplicate) {
                $this->posting->guardDuplicate($project, $type, $data, sourcePartyId: (string) $party->id, destinationWalletId: (string) $destination->id);
            }
        }

        return DB::transaction(function () use ($project, $actor, $data, $type, $endpoints): Route {
            $reference = trim((string) ($data['reference'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));

            /** @var Document $document */
            $document = Document::query()->create($endpoints + [
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'document_type' => $type,
                'amount' => $data['amount'],
                'transaction_date' => $data['transaction_date'],
                'reference' => $reference === '' ? null : $reference,
                'description' => $description === '' ? null : $description,
                // v17 §2.1: attaching the route sets via_route and freezes the amount.
                'status' => Document::STATUS_APPROVED,
                'posting_path' => Document::POSTING_PATH_VIA_ROUTE,
                'created_by' => (string) $actor->id,
                'approved_by' => (string) $actor->id,
            ]);

            /** @var Route $route */
            $route = Route::query()->create([
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                // v17 §4.1 Case B: route amount equals the document amount.
                'total_allocated_amount' => $document->amount,
                'status' => Route::STATUS_PLANNED,
                'linked_financial_document_id' => (string) $document->id,
            ]);

            $this->audit($actor, $document, $route, 'treasury.route.created', ['status_path' => self::CREATION_PATH]);

            return $route;
        });
    }

    /**
     * Record one leg, settled at once. `from_wallet_id` empty = money entering
     * from outside (funding / owner contribution routes only).
     *
     * @param array{from_wallet_id?: ?string, to_wallet_id: string, amount: string|float|int, occurred_at: string} $data
     */
    public function recordLeg(Project $project, User $actor, Route $route, array $data): Leg
    {
        $this->requireProjectRoute($project, $route);
        $document = $this->documentOf($route);

        $fromId = trim((string) ($data['from_wallet_id'] ?? ''));
        $from = $fromId === '' ? null : $this->posting->projectWallet($project, $fromId, 'from_wallet_id');
        $to = $this->posting->projectWallet($project, (string) $data['to_wallet_id'], 'to_wallet_id');
        if ($from !== null && (string) $from->id === (string) $to->id) {
            throw new TreasuryRuleViolation('Ví đi và ví đến phải khác nhau.', 'to_wallet_id');
        }
        if ($from === null && $document->document_type === Document::TYPE_INTERNAL_TRANSFER) {
            throw new TreasuryRuleViolation('Chuyển nội bộ không có chặng "từ bên ngoài".', 'from_wallet_id');
        }
        if (!Gate::forUser($actor)->allows('treasury.record-route-leg', [$project, $from, $to])) {
            throw new TreasuryRuleViolation('Bạn chỉ được ghi chặng ra hoặc vào ví mình đang giữ.', 'from_wallet_id');
        }
        $occurredAt = $this->parseDate((string) $data['occurred_at']);
        if ($occurredAt === null) {
            throw new TreasuryRuleViolation('Ngày của chặng không hợp lệ.', 'occurred_at');
        }
        if ($occurredAt->greaterThan(Carbon::today())) {
            throw new TreasuryRuleViolation('Ngày của chặng không được ở tương lai.', 'occurred_at');
        }
        $amount = TreasuryBalanceService::toCents((string) $data['amount']);
        if ($amount <= 0) {
            throw new TreasuryRuleViolation('Số tiền phải lớn hơn 0.', 'amount');
        }

        return DB::transaction(function () use ($actor, $route, $document, $from, $to, $amount, $occurredAt): Leg {
            if ($from !== null) {
                $this->posting->lockWallet($from);
                $this->posting->requireBalance($from, TreasuryBalanceService::fromCents($amount), 'amount');
            }
            $lockedDocument = $this->lockDocument($document);
            $lockedRoute = $this->lockRoute($route);
            if (in_array($lockedRoute->status, [Route::STATUS_COMPLETED, Route::STATUS_CANCELLED], true)) {
                throw new TreasuryRuleViolation('Tuyến đã hoàn tất hoặc đã huỷ, không ghi thêm chặng.', 'route');
            }

            $legs = $this->settledLegs($lockedRoute);
            $total = TreasuryBalanceService::toCents((string) $lockedRoute->total_allocated_amount);
            if ($from === null) {
                $external = $legs->filter(static fn (Leg $leg): bool => $leg->from_wallet_id === null)
                    ->sum(static fn (Leg $leg): int => TreasuryBalanceService::toCents((string) $leg->amount));
                if ($external + $amount > $total) {
                    throw new TreasuryRuleViolation('Tổng tiền "từ bên ngoài" vượt số tiền của tuyến.', 'amount');
                }
            }
            if ($from !== null && (string) $from->id === (string) $lockedDocument->source_wallet_id) {
                $net = $this->netByWallet($legs)[(string) $from->id] ?? 0;
                if ($net - $amount < -$total) {
                    throw new TreasuryRuleViolation('Tiền ra khỏi ví nguồn vượt số tiền của tuyến.', 'amount');
                }
            }

            /** @var Leg $leg */
            $leg = Leg::query()->create([
                'tenant_id' => (string) $lockedRoute->tenant_id,
                'payment_route_id' => (string) $lockedRoute->id,
                'sequence_no' => ((int) Leg::query()->withoutGlobalScopes()->where('payment_route_id', (string) $lockedRoute->id)->lockForUpdate()->max('sequence_no')) + 1,
                'from_wallet_id' => $from === null ? null : (string) $from->id,
                'to_wallet_id' => (string) $to->id,
                'amount' => TreasuryBalanceService::fromCents($amount),
                'status' => Leg::STATUS_SETTLED,
                'occurred_at' => $occurredAt,
            ]);

            // v17 §5a: a hop posts a debit/credit pair, an entry from outside a lone credit.
            $now = Carbon::now();
            if ($from !== null) {
                $this->postLegEntry($leg, (string) $from->id, Entry::DIRECTION_DEBIT, 'route_leg_out', $now);
            }
            $this->postLegEntry($leg, (string) $to->id, Entry::DIRECTION_CREDIT, 'route_leg_in', $now);

            $moved = $this->refreshStatuses($lockedRoute, $lockedDocument, $legs->push($leg));
            $this->audit($actor, $lockedDocument, $lockedRoute, 'treasury.route.leg_recorded', [
                'leg_id' => (string) $leg->id,
                'from_wallet_id' => $leg->from_wallet_id,
                'to_wallet_id' => (string) $leg->to_wallet_id,
                'leg_amount' => (string) $leg->amount,
                'occurred_at' => $occurredAt->toDateString(),
            ] + $moved);

            return $leg;
        });
    }

    /** v17 §4.2b: leg → reversed with one compensating entry per original entry. */
    public function reverseLeg(Project $project, User $actor, Leg $leg, string $reason): Leg
    {
        $route = $this->routeOfLeg($leg);
        $this->requireProjectRoute($project, $route);
        $reason = $this->requireReason($reason, 'Đảo chặng bắt buộc ghi lý do.');
        $document = $this->documentOf($route);
        $to = $this->posting->projectWallet($project, (string) $leg->to_wallet_id, 'leg');

        return DB::transaction(function () use ($actor, $leg, $route, $document, $to, $reason): Leg {
            $this->posting->lockWallet($to);
            // Class 4 before the shared balance read: the same rows, in the same
            // id order, that reconciliation locks for this document, so the two
            // cannot each hold one entry of the leg and wait for the other.
            $this->lockRouteEntries($route);
            $this->posting->requireBalance($to, (string) $leg->amount, 'leg');
            $lockedDocument = $this->lockDocument($document);
            $lockedRoute = $this->lockRoute($route);
            /** @var Leg $lockedLeg */
            $lockedLeg = Leg::query()->withoutGlobalScopes()->whereKey($leg->id)->lockForUpdate()->firstOrFail();
            if ($lockedLeg->status !== Leg::STATUS_SETTLED) {
                throw new TreasuryRuleViolation('Chặng này đã được đảo.', 'leg');
            }
            if (in_array($lockedDocument->status, [Document::STATUS_REVERSED, Document::STATUS_REJECTED], true)) {
                throw new TreasuryRuleViolation('Chứng từ của tuyến đã đảo hoặc đã huỷ.', 'leg');
            }

            $lockedLeg->status = Leg::STATUS_REVERSED;
            $lockedLeg->save();
            $now = Carbon::now();
            $originals = Entry::query()
                ->withoutGlobalScopes()
                ->where('source_payment_route_leg_id', (string) $lockedLeg->id)
                ->whereNull('reversal_of_entry_id')
                ->orderBy('id')
                ->get();
            foreach ($originals as $original) {
                Entry::query()->create([
                    'tenant_id' => (string) $lockedLeg->tenant_id,
                    'source_payment_route_leg_id' => (string) $lockedLeg->id,
                    'wallet_id' => (string) data_get($original, 'wallet_id'),
                    'direction' => data_get($original, 'direction') === Entry::DIRECTION_CREDIT ? Entry::DIRECTION_DEBIT : Entry::DIRECTION_CREDIT,
                    'amount' => (string) data_get($original, 'amount'),
                    'entry_type' => 'route_leg_reversal',
                    'posted_at' => $now,
                    'reversal_of_entry_id' => (string) data_get($original, 'id'),
                    'original_posting_key' => 'reversal:' . data_get($original, 'id'),
                ]);
            }

            $moved = $this->refreshStatuses($lockedRoute, $lockedDocument, $this->settledLegs($lockedRoute));
            $this->audit($actor, $lockedDocument, $lockedRoute, 'treasury.route.leg_reversed', [
                'leg_id' => (string) $lockedLeg->id,
                'reason' => $reason,
            ] + $moved);

            return $lockedLeg;
        });
    }

    /** Abandon a route that never completed and has no leg in force; its document is rejected. */
    public function cancelRoute(Project $project, User $actor, Route $route, string $reason): Route
    {
        $this->requireProjectRoute($project, $route);
        $reason = $this->requireReason($reason, 'Huỷ tuyến bắt buộc ghi lý do.');
        $document = $this->documentOf($route);

        return DB::transaction(function () use ($actor, $route, $document, $reason): Route {
            $lockedDocument = $this->lockDocument($document);
            $lockedRoute = $this->lockRoute($route);
            if ($lockedRoute->status === Route::STATUS_CANCELLED) {
                throw new TreasuryRuleViolation('Tuyến đã huỷ.', 'route');
            }
            if ($lockedDocument->status !== Document::STATUS_APPROVED) {
                throw new TreasuryRuleViolation('Tuyến đã hoàn tất ít nhất một lần, không huỷ được; hãy đảo chứng từ.', 'route');
            }
            if ($this->settledLegs($lockedRoute)->isNotEmpty()) {
                throw new TreasuryRuleViolation('Còn chặng đang có hiệu lực; hãy đảo các chặng trước khi huỷ.', 'route');
            }

            $lockedRoute->status = Route::STATUS_CANCELLED;
            $lockedRoute->save();
            $lockedDocument->status = Document::STATUS_REJECTED;
            $lockedDocument->save();
            $this->audit($actor, $lockedDocument, $lockedRoute, 'treasury.route.cancelled', ['reason' => $reason]);

            return $lockedRoute;
        });
    }

    /**
     * Routes of the project, open ones first.
     *
     * @return list<array<string, mixed>>
     */
    public function routes(Project $project, ?string $status = null): array
    {
        $routes = Route::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->when($status !== null && $status !== '', static fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status IN ('planned', 'partial') THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
        $documents = Document::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $routes->pluck('linked_financial_document_id')->filter()->all())
            ->get()
            ->keyBy(static fn ($doc): string => (string) data_get($doc, 'id'));

        $rows = [];
        foreach ($routes as $route) {
            /** @var Route $route */
            $rows[] = $this->summaryRow($route, $documents->get((string) $route->linked_financial_document_id), $this->settledLegs($route));
        }

        return $rows;
    }

    /**
     * One route with its legs and the route's net per wallet.
     *
     * @return array<string, mixed>
     */
    public function detail(Route $route): array
    {
        $legs = Leg::query()
            ->withoutGlobalScopes()
            ->where('payment_route_id', (string) $route->id)
            ->orderBy('sequence_no')
            ->get();
        $settled = $this->settledLegs($route);
        $document = Document::query()->withoutGlobalScopes()->whereKey((string) $route->linked_financial_document_id)->first();
        $walletIds = $legs->pluck('from_wallet_id')->merge($legs->pluck('to_wallet_id'))
            ->merge([data_get($document, 'source_wallet_id'), data_get($document, 'destination_wallet_id')])
            ->filter()->map(static fn ($id): string => (string) $id)->unique()->values()->all();
        $names = TreasuryWallet::query()->withoutGlobalScopes()->whereIn('id', $walletIds)->pluck('name', 'id');

        $net = $this->netByWallet($settled);
        $netRows = [];
        foreach ($walletIds as $walletId) {
            $netRows[] = [
                'wallet_id' => $walletId,
                'wallet_name' => $names[$walletId] ?? null,
                'net' => TreasuryBalanceService::fromCents($net[$walletId] ?? 0),
            ];
        }

        return $this->summaryRow($route, $document, $settled) + [
            'legs' => $legs->map(static fn ($leg): array => [
                'id' => (string) data_get($leg, 'id'),
                'sequence_no' => (int) data_get($leg, 'sequence_no'),
                'from_wallet_id' => data_get($leg, 'from_wallet_id'),
                'from_wallet_name' => data_get($leg, 'from_wallet_id') === null ? null : ($names[(string) data_get($leg, 'from_wallet_id')] ?? null),
                'to_wallet_id' => (string) data_get($leg, 'to_wallet_id'),
                'to_wallet_name' => $names[(string) data_get($leg, 'to_wallet_id')] ?? null,
                'amount' => TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents((string) data_get($leg, 'amount'))),
                'status' => (string) data_get($leg, 'status'),
                'occurred_at' => data_get($leg, 'occurred_at')?->toDateString(),
            ])->all(),
            'net_by_wallet' => $netRows,
        ];
    }

    /** A route of the project, or null. */
    public function projectRoute(Project $project, string $id): ?Route
    {
        /** @var Route|null $route */
        $route = Route::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->first();

        return $route;
    }

    /** A leg of one of the project's routes, or null. */
    public function projectLeg(Project $project, string $id): ?Leg
    {
        /** @var Leg|null $leg */
        $leg = Leg::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->whereKey($id)
            ->whereIn('payment_route_id', Route::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $project->tenant_id)
                ->where('project_id', (string) $project->id)
                ->select('id'))
            ->first();

        return $leg;
    }

    /**
     * Route and document status after a leg change (v17 §4.3). Completion
     * posts the document; a new leg or its reversal always adds unreconciled
     * entries, so a reconciled document regresses (§12.1) and a completed one
     * posts as `posted_unreconciled`.
     *
     * @param Collection<int, Leg> $settled
     * @return array{route_status: array{from: string, to: string}, document_status: array{from: string, to: string}}
     */
    private function refreshStatuses(Route $route, Document $document, Collection $settled): array
    {
        $routeFrom = $route->status;
        $documentFrom = $document->status;
        if ($this->isComplete($document, $route, $settled)) {
            $route->status = Route::STATUS_COMPLETED;
        } else {
            $route->status = $settled->isEmpty() ? Route::STATUS_PLANNED : Route::STATUS_PARTIAL;
        }
        $route->save();

        if ($document->status === Document::STATUS_APPROVED && $route->status === Route::STATUS_COMPLETED) {
            $document->status = Document::STATUS_POSTED_UNRECONCILED;
            $document->posted_at = Carbon::now();
            $document->save();
        } elseif ($document->status === Document::STATUS_POSTED_RECONCILED) {
            $document->status = Document::STATUS_POSTED_UNRECONCILED;
            $document->save();
        }

        return [
            'route_status' => ['from' => $routeFrom, 'to' => $route->status],
            'document_status' => ['from' => $documentFrom, 'to' => $document->status],
        ];
    }

    /**
     * v17 §4.3 full-target, endpoint-aware completion.
     *
     * @param Collection<int, Leg> $settled
     */
    private function isComplete(Document $document, Route $route, Collection $settled): bool
    {
        if ($settled->isEmpty()) {
            return false;
        }
        $total = TreasuryBalanceService::toCents((string) $route->total_allocated_amount);
        $destination = (string) $document->destination_wallet_id;
        $source = $document->source_wallet_id === null ? null : (string) $document->source_wallet_id;

        $net = $this->netByWallet($settled);
        foreach ($net as $walletId => $custody) {
            $expected = match ((string) $walletId) {
                $destination => $total,
                $source => -$total,
                default => 0,
            };
            if ($custody !== $expected) {
                return false;
            }
        }

        // Destination (and a transfer's source) must actually be on the route.
        return ($net[$destination] ?? 0) === $total && ($source === null || ($net[$source] ?? 0) === -$total);
    }

    /**
     * Route-scoped net custody in cents per wallet, over settled legs
     * (a reversed leg and its compensating entries net to zero).
     *
     * @param Collection<int, Leg> $legs
     * @return array<string, int>
     */
    private function netByWallet(Collection $legs): array
    {
        $net = [];
        foreach ($legs as $leg) {
            $amount = TreasuryBalanceService::toCents((string) $leg->amount);
            if ($leg->from_wallet_id !== null) {
                $net[(string) $leg->from_wallet_id] = ($net[(string) $leg->from_wallet_id] ?? 0) - $amount;
            }
            $net[(string) $leg->to_wallet_id] = ($net[(string) $leg->to_wallet_id] ?? 0) + $amount;
        }

        return $net;
    }

    /**
     * Settled legs of the route. Inside a write this is a locking read (the
     * route row is already held), so a leg committed by a transaction that
     * held the route before us is seen whatever our snapshot.
     *
     * @return Collection<int, Leg>
     */
    private function settledLegs(Route $route): Collection
    {
        /** @var Collection<int, Leg> $legs */
        $legs = Leg::query()
            ->withoutGlobalScopes()
            ->where('payment_route_id', (string) $route->id)
            ->where('status', Leg::STATUS_SETTLED)
            ->orderBy('sequence_no')
            ->when(DB::transactionLevel() > 0, static fn ($query) => $query->lockForUpdate())
            ->get();

        return $legs->values();
    }

    /**
     * @param Collection<int, Leg> $settled
     * @return array<string, mixed>
     */
    private function summaryRow(Route $route, mixed $document, Collection $settled): array
    {
        $total = TreasuryBalanceService::toCents((string) $route->total_allocated_amount);
        $destination = (string) data_get($document, 'destination_wallet_id');
        $arrived = $this->netByWallet($settled)[$destination] ?? 0;

        return [
            'id' => (string) $route->id,
            'status' => $route->status,
            'total_amount' => TreasuryBalanceService::fromCents($total),
            'arrived_amount' => TreasuryBalanceService::fromCents($arrived),
            'remaining_amount' => TreasuryBalanceService::fromCents($total - $arrived),
            'document_id' => (string) $route->linked_financial_document_id,
            'document_type' => (string) data_get($document, 'document_type'),
            'document_status' => (string) data_get($document, 'status'),
            'transaction_date' => data_get($document, 'transaction_date')?->toDateString(),
            'reference' => data_get($document, 'reference'),
            'description' => data_get($document, 'description'),
            'source_wallet_id' => data_get($document, 'source_wallet_id'),
            'source_party_id' => data_get($document, 'source_party_id'),
            'destination_wallet_id' => $destination,
        ];
    }

    private function postLegEntry(Leg $leg, string $walletId, string $direction, string $entryType, Carbon $postedAt): void
    {
        Entry::query()->create([
            'tenant_id' => (string) $leg->tenant_id,
            'source_payment_route_leg_id' => (string) $leg->id,
            'wallet_id' => $walletId,
            'direction' => $direction,
            'amount' => (string) $leg->amount,
            'entry_type' => $entryType,
            'posted_at' => $postedAt,
            'original_posting_key' => 'leg:' . $leg->id . ':' . $walletId . ':' . $direction,
        ]);
    }

    /** Class 4: every ledger entry of the route's legs, id ascending. */
    private function lockRouteEntries(Route $route): void
    {
        $legIds = Leg::query()->withoutGlobalScopes()->where('payment_route_id', (string) $route->id)->pluck('id')->all();
        if ($legIds === []) {
            return;
        }
        Entry::query()
            ->withoutGlobalScopes()
            ->whereIn('source_payment_route_leg_id', $legIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /** Class 5. */
    private function lockDocument(Document $document): Document
    {
        /** @var Document $locked */
        $locked = Document::query()->withoutGlobalScopes()->whereKey($document->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    /** Class 6. */
    private function lockRoute(Route $route): Route
    {
        /** @var Route $locked */
        $locked = Route::query()->withoutGlobalScopes()->whereKey($route->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    private function documentOf(Route $route): Document
    {
        if ($route->linked_financial_document_id === null) {
            throw new TreasuryRuleViolation('Tuyến này không gắn với chứng từ ngân quỹ.', 'route');
        }

        /** @var Document $document */
        $document = Document::query()->withoutGlobalScopes()->whereKey((string) $route->linked_financial_document_id)->firstOrFail();

        return $document;
    }

    private function routeOfLeg(Leg $leg): Route
    {
        /** @var Route $route */
        $route = Route::query()->withoutGlobalScopes()->whereKey((string) $leg->payment_route_id)->firstOrFail();

        return $route;
    }

    private function requireProjectRoute(Project $project, Route $route): void
    {
        if ((string) $route->tenant_id !== (string) $project->tenant_id || (string) $route->project_id !== (string) $project->id) {
            throw new TreasuryRuleViolation('Tuyến không thuộc dự án này.', 'route');
        }
    }

    private function requireReason(string $reason, string $message): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new TreasuryRuleViolation($message, 'reason');
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
     * @param array<string, mixed> $extra
     */
    private function audit(User $actor, Document $document, Route $route, string $action, array $extra): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $document->tenant_id,
            'project_id' => (string) $document->project_id,
            'action' => $action,
            'entity_type' => 'treasury_payment_route',
            'entity_id' => (string) $route->id,
            'new_data' => array_merge([
                'document_id' => (string) $document->id,
                'document_type' => $document->document_type,
                'amount' => (string) $document->amount,
                'transaction_date' => $document->transaction_date?->toDateString(),
                'reference' => $document->reference,
            ], $extra),
        ]);
    }
}
