<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFundChain as Chain;
use App\Models\Treasury\TreasuryFundChainMember as Member;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GAP-105 F-1 — fund chains: a manual grouping of posted documents and payment routes of one project showing how
 * funding moved and was spent (canonical spec "Fund Chain"). Membership is metadata: it never touches the ledger.
 * A document or route belongs to at most one chain (unique indexes from GAP-037). Every change is audited.
 */
class TreasuryFundChainService
{
    public const MEMBER_DOCUMENT_TYPES = [
        Document::TYPE_FUNDING,
        Document::TYPE_OWNER_CONTRIBUTION,
        Document::TYPE_INTERNAL_TRANSFER,
        Document::TYPE_EXPENSE,
        Document::TYPE_ADVANCE,
        Document::TYPE_ADVANCE_RETURN,
    ];

    private const POSTED = [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED];

    private const LIVE_ROUTE_STATUSES = [Route::STATUS_PLANNED, Route::STATUS_PARTIAL, Route::STATUS_COMPLETED];

    /**
     * @param array<string, mixed> $data chain_reference, description
     */
    public function create(Project $project, User $actor, array $data): Chain
    {
        $reference = trim((string) ($data['chain_reference'] ?? ''));
        if ($reference === '') {
            throw new TreasuryRuleViolation('Cần nhập mã chuỗi quỹ.', 'chain_reference');
        }
        $exists = Chain::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->where('chain_reference', $reference)
            ->exists();
        if ($exists) {
            throw new TreasuryRuleViolation('Mã chuỗi quỹ đã có trong dự án này.', 'chain_reference');
        }

        return DB::transaction(function () use ($project, $actor, $reference, $data): Chain {
            $chain = Chain::query()->create([
                'tenant_id' => (string) $project->tenant_id,
                'project_id' => (string) $project->id,
                'chain_reference' => $reference,
                'description' => ($data['description'] ?? null) === '' ? null : ($data['description'] ?? null),
            ]);
            $this->audit($actor, $chain, 'treasury.fund_chain.created', ['chain_reference' => $reference]);

            return $chain;
        });
    }

    /**
     * Adds a posted document or a live payment route of the same project (exactly one of the two ids).
     */
    public function addMember(Project $project, User $actor, Chain $chain, ?string $documentId, ?string $routeId): Member
    {
        $this->requireChainOf($project, $chain);
        $documentId = $documentId === '' ? null : $documentId;
        $routeId = $routeId === '' ? null : $routeId;
        if (($documentId === null) === ($routeId === null)) {
            throw new TreasuryRuleViolation('Chọn đúng một chứng từ hoặc một tuyến tiền.', 'member');
        }

        if ($documentId !== null) {
            $document = Document::query()->withoutGlobalScopes()
                ->where('tenant_id', (string) $project->tenant_id)
                ->where('project_id', (string) $project->id)
                ->whereKey($documentId)
                ->first();
            if ($document === null) {
                throw new TreasuryRuleViolation('Chứng từ không thuộc dự án này.', 'member');
            }
            if (!in_array($document->status, self::POSTED, true) || !in_array($document->document_type, self::MEMBER_DOCUMENT_TYPES, true)) {
                throw new TreasuryRuleViolation('Chỉ thêm được chứng từ tiền đã ghi sổ (chưa đảo).', 'member');
            }
        } else {
            $route = Route::query()->withoutGlobalScopes()
                ->where('tenant_id', (string) $project->tenant_id)
                ->where('project_id', (string) $project->id)
                ->whereKey((string) $routeId)
                ->first();
            if ($route === null) {
                throw new TreasuryRuleViolation('Tuyến tiền không thuộc dự án này.', 'member');
            }
            if (!in_array($route->status, self::LIVE_ROUTE_STATUSES, true)) {
                throw new TreasuryRuleViolation('Không thêm được tuyến tiền đã huỷ.', 'member');
            }
        }

        $taken = Member::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->when($documentId !== null, static fn ($q) => $q->where('member_financial_document_id', $documentId))
            ->when($routeId !== null, static fn ($q) => $q->where('member_payment_route_id', $routeId))
            ->exists();
        if ($taken) {
            throw new TreasuryRuleViolation('Chứng từ / tuyến này đã thuộc một chuỗi quỹ.', 'member');
        }

        try {
            return DB::transaction(function () use ($project, $actor, $chain, $documentId, $routeId): Member {
                $member = Member::query()->create([
                    'tenant_id' => (string) $project->tenant_id,
                    'fund_chain_id' => (string) $chain->id,
                    'member_financial_document_id' => $documentId,
                    'member_payment_route_id' => $routeId,
                ]);
                $this->audit($actor, $chain, 'treasury.fund_chain.member_added', [
                    'member_financial_document_id' => $documentId,
                    'member_payment_route_id' => $routeId,
                ]);

                return $member;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request linked the same document or route first.
            throw new TreasuryRuleViolation('Chứng từ / tuyến này đã thuộc một chuỗi quỹ.', 'member');
        }
    }

    public function removeMember(Project $project, User $actor, Chain $chain, string $memberId): void
    {
        $this->requireChainOf($project, $chain);
        $member = Member::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('fund_chain_id', (string) $chain->id)
            ->whereKey($memberId)
            ->first();
        if ($member === null) {
            throw new TreasuryRuleViolation('Thành viên không thuộc chuỗi này.', 'member');
        }

        DB::transaction(function () use ($actor, $chain, $member): void {
            $member->delete();
            $this->audit($actor, $chain, 'treasury.fund_chain.member_removed', [
                'member_financial_document_id' => $member->member_financial_document_id,
                'member_payment_route_id' => $member->member_payment_route_id,
            ]);
        });
    }

    public function projectChain(Project $project, string $id): ?Chain
    {
        /** @var Chain|null $chain */
        $chain = Chain::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->first();

        return $chain;
    }

    /**
     * @return list<array{id: string, chain_reference: string, description: string|null, member_count: int, money_in: string, money_out: string}>
     */
    public function chains(Project $project): array
    {
        $chains = Chain::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->orderBy('chain_reference')
            ->get();
        $members = $this->membersOf($project, $chains->pluck('id')->map(static fn ($id): string => (string) $id)->all());

        $rows = [];
        foreach ($chains as $chain) {
            $own = $members->where('fund_chain_id', (string) $chain->id);
            $rows[] = [
                'id' => (string) $chain->id,
                'chain_reference' => $chain->chain_reference,
                'description' => $chain->description,
                'member_count' => $own->count(),
                'money_in' => TreasuryBalanceService::fromCents((int) $own->where('flow', 'in')->sum('cents')),
                'money_out' => TreasuryBalanceService::fromCents((int) $own->where('flow', 'out')->sum('cents')),
            ];
        }

        return $rows;
    }

    /**
     * Members of one chain in date order.
     *
     * @return list<array<string, mixed>>
     */
    public function trace(Project $project, Chain $chain): array
    {
        $this->requireChainOf($project, $chain);

        return $this->membersOf($project, [(string) $chain->id])->values()->all();
    }

    /**
     * Posted documents and live routes of the project that are not in any chain (candidates to add).
     *
     * @return array{documents: list<array<string, mixed>>, routes: list<array<string, mixed>>}
     */
    public function candidates(Project $project): array
    {
        $tenantId = (string) $project->tenant_id;
        $documents = Document::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $project->id)
            ->whereIn('status', self::POSTED)
            ->whereIn('document_type', self::MEMBER_DOCUMENT_TYPES)
            ->whereNotIn('id', Member::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotNull('member_financial_document_id')->select('member_financial_document_id'))
            ->orderByDesc('transaction_date')
            ->limit(200)
            ->get(['id', 'document_type', 'amount', 'transaction_date', 'reference', 'description']);
        $routes = Route::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $project->id)
            ->whereIn('status', self::LIVE_ROUTE_STATUSES)
            ->whereNotIn('id', Member::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotNull('member_payment_route_id')->select('member_payment_route_id'))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'total_allocated_amount', 'status', 'created_at']);

        return [
            'documents' => $documents->map(static fn ($doc): array => [
                'id' => (string) data_get($doc, 'id'),
                'label' => (TreasuryReportService::TYPE_LABELS[(string) data_get($doc, 'document_type')] ?? (string) data_get($doc, 'document_type'))
                    . ' ' . self::day(data_get($doc, 'transaction_date')) . ' — ' . number_format((float) data_get($doc, 'amount'), 0, ',', '.')
                    . (filled(data_get($doc, 'reference')) ? ' (' . data_get($doc, 'reference') . ')' : ''),
            ])->values()->all(),
            'routes' => $routes->map(static fn ($route): array => [
                'id' => (string) data_get($route, 'id'),
                'label' => 'Tuyến ' . self::day(data_get($route, 'created_at')) . ' — ' . number_format((float) data_get($route, 'total_allocated_amount'), 0, ',', '.'),
            ])->values()->all(),
        ];
    }

    /**
     * Members of the given chains with what they point to, in date order.
     *
     * @param list<string> $chainIds
     * @return Collection<int, array<string, mixed>>
     */
    private function membersOf(Project $project, array $chainIds): Collection
    {
        if ($chainIds === []) {
            return collect();
        }
        $tenantId = (string) $project->tenant_id;
        $members = Member::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('fund_chain_id', $chainIds)
            ->get();
        $documents = Document::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $members->pluck('member_financial_document_id')->filter()->all())
            ->get(['id', 'document_type', 'status', 'amount', 'transaction_date', 'reference', 'description'])
            ->keyBy('id');
        $routes = Route::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $members->pluck('member_payment_route_id')->filter()->all())
            ->get(['id', 'status', 'total_allocated_amount', 'created_at'])
            ->keyBy('id');

        return $members->map(static function ($member) use ($documents, $routes): array {
            $documentId = data_get($member, 'member_financial_document_id');
            if ($documentId !== null) {
                $doc = $documents[(string) $documentId] ?? null;
                $type = (string) data_get($doc, 'document_type');

                return [
                    'member_id' => (string) data_get($member, 'id'),
                    'fund_chain_id' => (string) data_get($member, 'fund_chain_id'),
                    'kind' => 'document',
                    'target_id' => (string) $documentId,
                    'date' => self::isoDay(data_get($doc, 'transaction_date')),
                    'label' => TreasuryReportService::TYPE_LABELS[$type] ?? $type,
                    'status' => (string) data_get($doc, 'status'),
                    'amount' => (string) data_get($doc, 'amount'),
                    'reference' => data_get($doc, 'reference'),
                    'flow' => in_array($type, [Document::TYPE_FUNDING, Document::TYPE_OWNER_CONTRIBUTION, Document::TYPE_ADVANCE_RETURN], true) ? 'in'
                        : (in_array($type, [Document::TYPE_EXPENSE, Document::TYPE_ADVANCE], true) ? 'out' : 'move'),
                    'cents' => TreasuryBalanceService::toCents((string) data_get($doc, 'amount', '0')),
                ];
            }
            $routeId = (string) data_get($member, 'member_payment_route_id');
            $route = $routes[$routeId] ?? null;

            return [
                'member_id' => (string) data_get($member, 'id'),
                'fund_chain_id' => (string) data_get($member, 'fund_chain_id'),
                'kind' => 'route',
                'target_id' => $routeId,
                'date' => self::isoDay(data_get($route, 'created_at')),
                'label' => 'Tuyến tiền',
                'status' => (string) data_get($route, 'status'),
                'amount' => (string) data_get($route, 'total_allocated_amount'),
                'reference' => null,
                'flow' => 'move',
                'cents' => TreasuryBalanceService::toCents((string) data_get($route, 'total_allocated_amount', '0')),
            ];
        })->sortBy(static fn (array $row): string => (string) $row['date'] . '|' . $row['member_id'])->values();
    }

    private static function isoDay(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : \Illuminate\Support\Carbon::parse((string) $value)->toDateString();
    }

    private static function day(mixed $value): string
    {
        return $value === null || $value === '' ? '' : \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y');
    }

    private function requireChainOf(Project $project, Chain $chain): void
    {
        if ((string) $chain->tenant_id !== (string) $project->tenant_id || (string) $chain->project_id !== (string) $project->id) {
            throw new TreasuryRuleViolation('Chuỗi quỹ không thuộc dự án này.', 'chain');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function audit(User $actor, Chain $chain, string $action, array $data): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $chain->tenant_id,
            'project_id' => (string) $chain->project_id,
            'action' => $action,
            'entity_type' => 'treasury_fund_chain',
            'entity_id' => (string) $chain->id,
            'new_data' => $data + ['chain_reference' => $chain->chain_reference],
        ]);
    }
}
