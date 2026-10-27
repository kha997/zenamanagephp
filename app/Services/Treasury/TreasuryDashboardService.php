<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryWallet;
use Illuminate\Support\Carbon;

/**
 * GAP-097 — Treasury S6 project dashboard (approved Gate 2 Option A).
 *
 * Read-only: every figure restates the ledger through the S2–S5 services.
 * Owner Gate 1 answers: fixed thresholds — funding unreconciled for more than
 * 30 days, an advance overdue once in the S5 over-90-days bucket; per-project
 * dashboard plus an all-projects overview.
 */
class TreasuryDashboardService
{
    public const UNRECONCILED_FUNDING_DAYS = 30;

    public const ALERT_UNRECONCILED_FUNDING = 'unreconciled_funding';
    public const ALERT_PENDING_APPROVALS = 'pending_approvals';
    public const ALERT_PENDING_SETTLEMENTS = 'pending_settlements';
    public const ALERT_OVERDUE_ADVANCES = 'overdue_advances';
    public const ALERT_NEGATIVE_WALLETS = 'negative_wallets';
    public const ALERT_OPEN_ROUTES = 'open_routes';

    private const POSTED = [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED];

    private const FUNDING_TYPES = [Document::TYPE_FUNDING, Document::TYPE_OWNER_CONTRIBUTION];

    public function __construct(
        private readonly TreasuryBalanceService $balances,
        private readonly TreasuryReconciliationService $reconciliation,
        private readonly TreasuryAdvanceService $advances,
    ) {
    }

    /**
     * @return array{cards: array<string, string|int>, wallets: list<array<string, mixed>>, alerts: list<array{code: string, count: int, amount: string}>}
     */
    public function dashboard(Project $project, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::now())->copy()->startOfDay();
        $summary = $this->balances->projectSummary($project);
        $ageing = $this->advances->ageing($project, $today);
        $pending = $this->documents($project)->whereIn('document_type', [Document::TYPE_EXPENSE, Document::TYPE_ADVANCE])
            ->where('status', Document::STATUS_SUBMITTED)->get(['amount']);
        $unreconciledFunding = $this->documents($project)->whereIn('document_type', self::FUNDING_TYPES)
            ->where('status', Document::STATUS_POSTED_UNRECONCILED)->get(['amount', 'transaction_date']);
        $staleFunding = $unreconciledFunding->filter(fn ($doc): bool => $this->daysSince(data_get($doc, 'transaction_date'), $today) > self::UNRECONCILED_FUNDING_DAYS);

        $cards = [
            'investor_funding' => $summary['investor_funding'],
            'via_route_funding' => self::sum($this->documents($project)->whereIn('document_type', self::FUNDING_TYPES)
                ->whereIn('status', self::POSTED)->where('posting_path', Document::POSTING_PATH_VIA_ROUTE)->get(['amount'])),
            'owner_contribution' => $summary['owner_contribution'],
            'contract_payments_arrived' => $summary['contract_payments_arrived'],
            'expenses' => $summary['expenses'],
            'self_approved_expenses' => $summary['self_approved_expenses'],
            'held_total' => $summary['held_total'],
            'unreconciled_funding' => self::sum($unreconciledFunding),
            'pending_approvals_count' => $pending->count(),
            'pending_approvals_amount' => self::sum($pending),
            'outstanding_advances' => TreasuryBalanceService::fromCents((int) array_sum(array_map([TreasuryBalanceService::class, 'toCents'], $ageing['totals']))),
        ];

        $wallets = $this->walletRows($project, $summary['wallets'], $ageing['rows']);
        $overdue = array_values(array_filter($ageing['rows'], static fn (array $row): bool => $row['bucket'] === TreasuryAdvanceService::BUCKET_OVER_90));
        $openRoutes = Route::query()->withoutGlobalScopes()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)
            ->whereIn('status', [Route::STATUS_PLANNED, Route::STATUS_PARTIAL])->get(['total_allocated_amount']);
        $negative = array_values(array_filter($wallets, static fn (array $row): bool => TreasuryBalanceService::toCents((string) $row['balance']) < 0));

        $alerts = [];
        $add = static function (string $code, int $count, int $cents) use (&$alerts): void {
            if ($count > 0) {
                $alerts[] = ['code' => $code, 'count' => $count, 'amount' => TreasuryBalanceService::fromCents($cents)];
            }
        };
        $add(self::ALERT_UNRECONCILED_FUNDING, $staleFunding->count(), TreasuryBalanceService::toCents(self::sum($staleFunding)));
        $add(self::ALERT_PENDING_APPROVALS, $pending->count(), TreasuryBalanceService::toCents(self::sum($pending)));
        $settlementRequests = array_values(array_filter($ageing['rows'], static fn (array $row): bool => (bool) $row['open_request']));
        $add(self::ALERT_PENDING_SETTLEMENTS, count($settlementRequests), 0);
        $add(self::ALERT_OVERDUE_ADVANCES, count($overdue), self::rowsCents($overdue, 'outstanding'));
        $add(self::ALERT_NEGATIVE_WALLETS, count($negative), self::rowsCents($negative, 'balance'));
        $add(self::ALERT_OPEN_ROUTES, $openRoutes->count(), TreasuryBalanceService::toCents(self::sum($openRoutes, 'total_allocated_amount')));

        return ['cards' => $cards, 'wallets' => $wallets, 'alerts' => $alerts];
    }

    /**
     * Owner answer 4: one row per visible project.
     *
     * @param iterable<Project> $projects
     * @return list<array<string, mixed>>
     */
    public function overview(iterable $projects, ?Carbon $today = null): array
    {
        $rows = [];
        foreach ($projects as $project) {
            $dashboard = $this->dashboard($project, $today);
            $rows[] = [
                'project_id' => (string) $project->id,
                'project_name' => (string) $project->name,
                'held_total' => $dashboard['cards']['held_total'],
                'expenses' => $dashboard['cards']['expenses'],
                'unreconciled_funding' => $dashboard['cards']['unreconciled_funding'],
                'outstanding_advances' => $dashboard['cards']['outstanding_advances'],
                'alert_count' => count($dashboard['alerts']),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, string> $balances
     * @param list<array<string, mixed>> $ageingRows
     * @return list<array<string, mixed>>
     */
    private function walletRows(Project $project, array $balances, array $ageingRows): array
    {
        $wallets = TreasuryWallet::query()
            ->with('custodianParty')
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->orderBy('name')
            ->get();
        $ids = $wallets->map(static fn ($w): string => (string) data_get($w, 'id'))->all();
        $reconciliation = $this->reconciliation->walletSummaries($ids, (string) $project->tenant_id);
        $lastActivity = Entry::query()->withoutGlobalScopes()->where('tenant_id', (string) $project->tenant_id)->whereIn('wallet_id', $ids)
            ->groupBy('wallet_id')->selectRaw('wallet_id, MAX(posted_at) as last_posted_at')->pluck('last_posted_at', 'wallet_id')->all();
        $advanceCents = [];
        foreach ($ageingRows as $row) {
            $advanceCents[(string) $row['wallet_id']] = ($advanceCents[(string) $row['wallet_id']] ?? 0) + TreasuryBalanceService::toCents((string) $row['outstanding']);
        }

        $rows = [];
        foreach ($wallets as $wallet) {
            $id = (string) data_get($wallet, 'id');
            $last = $lastActivity[$id] ?? null;
            $rows[] = [
                'id' => $id,
                'name' => (string) data_get($wallet, 'name'),
                'wallet_type' => (string) data_get($wallet, 'wallet_type'),
                'custodian_party_id' => data_get($wallet, 'custodian_party_id'),
                'custodian_name' => data_get($wallet, 'custodianParty.name'),
                'balance' => $balances[$id] ?? '0.00',
                'reconciled' => $reconciliation[$id]['reconciled'] ?? '0.00',
                'unreconciled' => $reconciliation[$id]['unreconciled'] ?? '0.00',
                'outstanding_advances' => TreasuryBalanceService::fromCents($advanceCents[$id] ?? 0),
                'last_activity_at' => $last === null ? null : Carbon::parse((string) $last)->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Document>
     */
    private function documents(Project $project): \Illuminate\Database\Eloquent\Builder
    {
        return Document::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id);
    }

    private function daysSince(mixed $date, Carbon $today): int
    {
        if ($date === null) {
            return 0;
        }

        return (int) Carbon::parse((string) $date)->startOfDay()->diffInDays($today, false);
    }

    /**
     * @param iterable<mixed> $rows
     */
    private static function sum(iterable $rows, string $column = 'amount'): string
    {
        $cents = 0;
        foreach ($rows as $row) {
            $cents += TreasuryBalanceService::toCents((string) data_get($row, $column));
        }

        return TreasuryBalanceService::fromCents($cents);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function rowsCents(array $rows, string $column): int
    {
        return TreasuryBalanceService::toCents(self::sum($rows, $column));
    }
}
