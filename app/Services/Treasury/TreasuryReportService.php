<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Treasury\TreasuryAdvance as Advance;
use App\Models\Treasury\TreasuryAdvanceSettlement as Settlement;
use App\Models\Treasury\TreasuryCostSettlementAllocation as Allocation;
use App\Models\Treasury\TreasuryExpenseApproval as Approval;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryLedgerEntry as Entry;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryPaymentRouteLeg as Leg;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * GAP-097 — Treasury S6 register and reports (approved Gate 2 Option A).
 *
 * Read-only. Owner Gate 1 answers: only the reports whose data exists, CSV
 * export. Every report is a title, headers and rows of plain values, so the
 * page and the CSV show the same figures.
 */
class TreasuryReportService
{
    public const REPORTS = [
        'cashflow' => 'Dòng tiền theo tháng',
        'wallets' => 'Số dư ví',
        'custodians' => 'Tiền theo người giữ',
        'advance_ageing' => 'Tuổi nợ tạm ứng',
        'expense_categories' => 'Chi phí theo loại',
        'expense_parties' => 'Chi phí theo đối tác',
        'self_approved' => 'Khoản tự duyệt',
        'unreconciled' => 'Giao dịch chưa đối soát',
        'routes' => 'Tiền đang qua trung gian',
        'corrections' => 'Đảo và điều chỉnh',
    ];

    public const TYPE_LABELS = [
        Document::TYPE_FUNDING => 'Tiền nhận',
        Document::TYPE_OWNER_CONTRIBUTION => 'Góp vốn chủ',
        Document::TYPE_INTERNAL_TRANSFER => 'Chuyển ví',
        Document::TYPE_EXPENSE => 'Chi phí',
        Document::TYPE_ADVANCE => 'Tạm ứng',
        Document::TYPE_ADVANCE_RETURN => 'Nộp lại tạm ứng',
        Document::TYPE_ADJUSTMENT => 'Điều chỉnh',
        Document::TYPE_REVERSAL => 'Bút toán đảo',
    ];

    public const LEDGER_STATUSES = [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED, Document::STATUS_REVERSED];

    private const SELF_APPROVAL_EVENTS = ['approved', TreasuryAdvanceService::EVENT_APPROVED, TreasuryAdvanceService::EVENT_SETTLED];

    /** Cashflow columns, in display order. */
    private const CASHFLOW_COLUMNS = [
        'funding' => 'Tiền nhận',
        'owner_contribution' => 'Góp vốn chủ',
        'contract_payment' => 'Thanh toán HĐ về ví',
        'expense' => 'Chi phí',
        'advance' => 'Tạm ứng chi ra',
        'advance_return' => 'Tạm ứng nộp lại',
        'adjustment' => 'Điều chỉnh',
    ];

    public function __construct(
        private readonly TreasuryDashboardService $dashboard,
        private readonly TreasuryReconciliationService $reconciliation,
        private readonly TreasuryAdvanceService $advances,
        private readonly TreasuryRouteService $routes,
    ) {
    }

    /**
     * The register filters a request may set, cleaned (status is handled apart).
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function registerFilters(array $input): array
    {
        $filters = [];
        foreach (['date_from', 'date_to', 'type', 'wallet_id', 'party_id', 'created_by', 'approved_by', 'self_approved', 'reconciled'] as $key) {
            $value = is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';
            if ($key === 'date_from' || $key === 'date_to') {
                $value = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
            }
            $filters[$key] = $value;
        }
        $filters['type'] = array_key_exists($filters['type'], self::TYPE_LABELS) ? $filters['type'] : '';

        return $filters;
    }

    /**
     * Ledger documents of the project, filtered (spec §10.2, data that exists).
     *
     * @param array<string, mixed> $filters date_from, date_to, type, status, wallet_id, party_id, created_by, approved_by, self_approved, reconciled
     * @return Builder<Document>
     */
    public function registerQuery(Project $project, array $filters): Builder
    {
        /** @var Builder<Document> $query */
        $query = Document::query()
            ->with(['sourceWallet', 'destinationWallet', 'sourceParty', 'destinationParty'])
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereIn('status', self::LEDGER_STATUSES);
        $value = static fn (string $key): string => trim((string) ($filters[$key] ?? ''));
        if ($value('date_from') !== '') {
            $query->whereDate('transaction_date', '>=', $value('date_from'));
        }
        if ($value('date_to') !== '') {
            $query->whereDate('transaction_date', '<=', $value('date_to'));
        }
        if ($value('type') !== '') {
            $query->where('document_type', $value('type'));
        }
        if ($value('status') !== '') {
            $query->where('status', $value('status'));
        }
        if ($value('wallet_id') !== '') {
            $wallet = $value('wallet_id');
            $query->where(static fn ($q) => $q->where('source_wallet_id', $wallet)->orWhere('destination_wallet_id', $wallet));
        }
        if ($value('party_id') !== '') {
            $party = $value('party_id');
            $query->where(static fn ($q) => $q->where('source_party_id', $party)->orWhere('destination_party_id', $party));
        }
        if ($value('created_by') !== '') {
            $query->where('created_by', $value('created_by'));
        }
        if ($value('approved_by') !== '') {
            $query->where('approved_by', $value('approved_by'));
        }
        if (filter_var($filters['self_approved'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('id', Approval::query()->whereIn('event', self::SELF_APPROVAL_EVENTS)->where('context->approval_mode', 'self_approval')->select('financial_document_id'));
        }
        if ($value('reconciled') === 'yes') {
            $query->where('status', Document::STATUS_POSTED_RECONCILED);
        } elseif ($value('reconciled') === 'no') {
            $query->where('status', Document::STATUS_POSTED_UNRECONCILED);
        }

        $query->orderByDesc('transaction_date')->orderByDesc('created_at')->orderByDesc('id');

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Document>
     */
    public function register(Project $project, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Document> $page */
        $page = $this->registerQuery($project, $filters)->paginate($perPage)->withQueryString();

        return $page;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title: string, headers: list<string>, rows: list<list<string|int>>}
     */
    public function registerReport(Project $project, array $filters): array
    {
        $users = $this->userNames($project);
        $rows = [];
        foreach ($this->registerQuery($project, $filters)->get() as $doc) {
            /** @var Document $doc */
            $rows[] = [
                $doc->transaction_date?->toDateString() ?? '',
                self::TYPE_LABELS[$doc->document_type] ?? $doc->document_type,
                (string) (data_get($doc, 'sourceWallet.name') ?? data_get($doc, 'sourceParty.name') ?? ''),
                (string) (data_get($doc, 'destinationWallet.name') ?? data_get($doc, 'destinationParty.name') ?? ''),
                (string) $doc->amount,
                $doc->status,
                (string) ($users[(string) $doc->created_by] ?? ''),
                (string) ($users[(string) $doc->approved_by] ?? ''),
                (string) ($doc->reference ?? ''),
                (string) ($doc->description ?? ''),
            ];
        }

        return [
            'title' => 'Sổ giao dịch',
            'headers' => ['Ngày', 'Loại', 'Từ', 'Đến', 'Số tiền', 'Trạng thái', 'Người lập', 'Người duyệt', 'Tham chiếu', 'Diễn giải'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, headers: list<string>, rows: list<list<string|int>>}
     */
    public function report(Project $project, string $key, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::now())->copy()->startOfDay();
        [$headers, $rows] = match ($key) {
            'cashflow' => $this->cashflow($project),
            'wallets' => $this->wallets($project, $today),
            'custodians' => $this->custodians($project, $today),
            'advance_ageing' => $this->advanceAgeing($project, $today),
            'expense_categories' => $this->expenseCategories($project),
            'expense_parties' => $this->expenseParties($project),
            'self_approved' => $this->selfApproved($project),
            'unreconciled' => $this->unreconciled($project, $today),
            'routes' => $this->openRoutes($project),
            'corrections' => $this->corrections($project),
            default => throw new \InvalidArgumentException('Unknown treasury report ' . $key),
        };

        return ['title' => self::REPORTS[$key], 'headers' => $headers, 'rows' => $rows];
    }

    /** Neutralises spreadsheet formula injection, as the existing report export. */
    public static function csvValue(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Money that entered or left the project's wallets, per month of the
     * transaction date (a leg's date for route legs). Internal transfers and
     * moves between the project's wallets are excluded; a reversal nets against
     * its original's type and month.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function cashflow(Project $project): array
    {
        $walletIds = $this->walletIds($project);
        $entries = Entry::query()->withoutGlobalScopes()->where('tenant_id', (string) $project->tenant_id)->whereIn('wallet_id', $walletIds)
            ->get(['source_financial_document_id', 'source_payment_route_leg_id', 'direction', 'amount']);
        $documents = Document::query()->withoutGlobalScopes()->whereIn('id', $entries->pluck('source_financial_document_id')->filter()->unique()->all())
            ->get(['id', 'document_type', 'reversed_document_id', 'transaction_date'])->keyBy('id');
        $originals = Document::query()->withoutGlobalScopes()->whereIn('id', $documents->pluck('reversed_document_id')->filter()->unique()->all())
            ->get(['id', 'document_type', 'transaction_date'])->keyBy('id');
        $legs = Leg::query()->withoutGlobalScopes()->whereIn('id', $entries->pluck('source_payment_route_leg_id')->filter()->unique()->all())
            ->get(['id', 'payment_route_id', 'from_wallet_id', 'occurred_at'])->keyBy('id');
        $routes = Route::query()->withoutGlobalScopes()->whereIn('id', $legs->pluck('payment_route_id')->unique()->all())
            ->get(['id', 'linked_financial_document_id', 'linked_contract_payment_id'])->keyBy('id');
        $routeTypes = Document::query()->withoutGlobalScopes()->whereIn('id', $routes->pluck('linked_financial_document_id')->filter()->all())->pluck('document_type', 'id');

        $months = [];
        foreach ($entries as $entry) {
            $column = null;
            $date = null;
            $docId = data_get($entry, 'source_financial_document_id');
            if ($docId !== null) {
                $doc = $documents[(string) $docId] ?? null;
                if (data_get($doc, 'document_type') === Document::TYPE_REVERSAL) {
                    $doc = $originals[(string) data_get($doc, 'reversed_document_id')] ?? null;
                }
                $column = (string) data_get($doc, 'document_type');
                $date = data_get($doc, 'transaction_date');
            } else {
                $leg = $legs[(string) data_get($entry, 'source_payment_route_leg_id')] ?? null;
                // Only the leg that brings money in from outside is a cash flow;
                // a move between the project's wallets is not.
                if ($leg !== null && data_get($leg, 'from_wallet_id') === null) {
                    $route = $routes[(string) data_get($leg, 'payment_route_id')] ?? null;
                    $date = data_get($leg, 'occurred_at');
                    $column = data_get($route, 'linked_contract_payment_id') !== null
                        ? 'contract_payment'
                        : (string) ($routeTypes[(string) data_get($route, 'linked_financial_document_id')] ?? '');
                }
            }
            if ($column === null || $date === null || !isset(self::CASHFLOW_COLUMNS[$column])) {
                continue;
            }
            $month = Carbon::parse((string) $date)->format('Y-m');
            $cents = TreasuryBalanceService::toCents((string) data_get($entry, 'amount'));
            $months[$month][$column] = ($months[$month][$column] ?? 0) + (data_get($entry, 'direction') === Entry::DIRECTION_CREDIT ? $cents : -$cents);
        }
        ksort($months);

        $rows = [];
        foreach ($months as $month => $columns) {
            $row = [$month];
            $net = 0;
            foreach (array_keys(self::CASHFLOW_COLUMNS) as $column) {
                $row[] = TreasuryBalanceService::fromCents($columns[$column] ?? 0);
                $net += $columns[$column] ?? 0;
            }
            $row[] = TreasuryBalanceService::fromCents($net);
            $rows[] = $row;
        }

        return [['Tháng', ...array_values(self::CASHFLOW_COLUMNS), 'Tăng/giảm tiền giữ'], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function wallets(Project $project, Carbon $today): array
    {
        $rows = [];
        foreach ($this->dashboard->dashboard($project, $today)['wallets'] as $wallet) {
            $rows[] = [
                $wallet['name'], TreasurySetupService::WALLET_TYPES[$wallet['wallet_type']] ?? $wallet['wallet_type'], (string) ($wallet['custodian_name'] ?? ''),
                $wallet['balance'], $wallet['reconciled'], $wallet['unreconciled'], $wallet['outstanding_advances'],
                $wallet['last_activity_at'] === null ? '' : Carbon::parse($wallet['last_activity_at'])->toDateString(),
            ];
        }

        return [['Ví', 'Loại', 'Người giữ', 'Số dư', 'Đã đối soát', 'Chưa đối soát', 'Tạm ứng còn nợ (chi từ ví)', 'Phát sinh cuối'], $rows];
    }

    /**
     * Money held per person: wallet balances they keep plus advances they owe.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function custodians(Project $project, Carbon $today): array
    {
        $held = [];
        foreach ($this->dashboard->dashboard($project, $today)['wallets'] as $wallet) {
            if ($wallet['custodian_party_id'] === null) {
                continue;
            }
            $id = (string) $wallet['custodian_party_id'];
            $held[$id]['wallets'] = ($held[$id]['wallets'] ?? 0) + TreasuryBalanceService::toCents((string) $wallet['balance']);
        }
        foreach ($this->advances->ageing($project, $today)['rows'] as $row) {
            $id = (string) $row['party_id'];
            $held[$id]['advances'] = ($held[$id]['advances'] ?? 0) + TreasuryBalanceService::toCents((string) $row['outstanding']);
        }
        $parties = TreasuryFinancialParty::query()->whereIn('id', array_keys($held))->get(['id', 'name', 'party_type'])->keyBy('id');
        $rows = [];
        foreach ($held as $id => $amounts) {
            $wallets = $amounts['wallets'] ?? 0;
            $advances = $amounts['advances'] ?? 0;
            $party = $parties[$id] ?? null;
            $rows[] = [
                (string) data_get($party, 'name'), TreasurySetupService::PARTY_TYPES[(string) data_get($party, 'party_type')] ?? '',
                TreasuryBalanceService::fromCents($wallets), TreasuryBalanceService::fromCents($advances), TreasuryBalanceService::fromCents($wallets + $advances),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a[0], (string) $b[0]));

        return [['Người giữ', 'Loại', 'Số dư ví đang giữ', 'Tạm ứng còn nợ', 'Tổng'], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function advanceAgeing(Project $project, Carbon $today): array
    {
        $labels = [
            TreasuryAdvanceService::BUCKET_UNDER_30 => 'Dưới 30 ngày', TreasuryAdvanceService::BUCKET_30_60 => '30–60 ngày',
            TreasuryAdvanceService::BUCKET_60_90 => '60–90 ngày', TreasuryAdvanceService::BUCKET_OVER_90 => 'Trên 90 ngày',
        ];
        $rows = [];
        foreach ($this->advances->ageing($project, $today)['rows'] as $row) {
            $rows[] = [
                $row['posted_on'], $row['party_name'], $row['wallet_name'], $row['amount'], $row['settled'], $row['returned'], $row['outstanding'],
                $row['days'], $labels[$row['bucket']],
            ];
        }

        return [['Ngày chi', 'Người nhận', 'Chi từ ví', 'Tạm ứng', 'Đã quyết toán', 'Đã nộp lại', 'Còn nợ', 'Số ngày', 'Tuổi nợ'], $rows];
    }

    /**
     * Net cost allocations of expenses and advance settlements, by cost type.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function expenseCategories(Project $project): array
    {
        $totals = [];
        foreach ($this->projectAllocations($project) as $allocation) {
            $contractExpenseId = data_get($allocation, 'cost_source_contract_expense_id');
            $label = $contractExpenseId === null
                ? 'Vật tư (phiếu nhập)'
                : (new ContractExpense(['category' => (string) ContractExpense::query()->withoutGlobalScopes()->whereKey((string) $contractExpenseId)->value('category')]))->categoryLabel();
            $cents = TreasuryBalanceService::toCents((string) data_get($allocation, 'allocated_amount'));
            $totals[$label] = ($totals[$label] ?? 0) + (data_get($allocation, 'direction') === Allocation::DIRECTION_APPLY ? $cents : -$cents);
        }
        ksort($totals);
        $rows = [];
        foreach ($totals as $label => $cents) {
            $rows[] = [$label, TreasuryBalanceService::fromCents($cents)];
        }

        return [['Loại chi phí', 'Đã trả (thuần)'], $rows];
    }

    /**
     * Posted expenses by payee; advance settlements by advance holder.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function expenseParties(Project $project): array
    {
        $totals = [];
        $expenses = Document::query()->withoutGlobalScopes()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)
            ->where('document_type', Document::TYPE_EXPENSE)->whereIn('status', [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED])
            ->get(['destination_party_id', 'amount']);
        foreach ($expenses as $expense) {
            $key = (string) data_get($expense, 'destination_party_id') . '|direct';
            $totals[$key] = ($totals[$key] ?? 0) + TreasuryBalanceService::toCents((string) data_get($expense, 'amount'));
        }
        $advances = Advance::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)->get(['id', 'financial_party_id'])->keyBy('id');
        $settlements = Settlement::query()->whereIn('advance_id', $advances->keys()->all())
            ->where('settlement_type', Settlement::SETTLEMENT_TYPE_APPROVED_EXPENSE)->get(['advance_id', 'direction', 'amount']);
        foreach ($settlements as $settlement) {
            $key = (string) data_get($advances[(string) data_get($settlement, 'advance_id')] ?? null, 'financial_party_id') . '|advance';
            $cents = TreasuryBalanceService::toCents((string) data_get($settlement, 'amount'));
            $totals[$key] = ($totals[$key] ?? 0) + (data_get($settlement, 'direction') === Settlement::DIRECTION_APPLY ? $cents : -$cents);
        }
        $parties = TreasuryFinancialParty::query()->whereIn('id', array_map(static fn (string $k): string => explode('|', $k)[0], array_keys($totals)))
            ->get(['id', 'name', 'party_type'])->keyBy('id');
        $rows = [];
        foreach ($totals as $key => $cents) {
            [$partyId, $via] = explode('|', $key);
            $party = $parties[$partyId] ?? null;
            $rows[] = [
                (string) data_get($party, 'name'), TreasurySetupService::PARTY_TYPES[(string) data_get($party, 'party_type')] ?? '',
                $via === 'advance' ? 'Qua tạm ứng' : 'Chi trực tiếp', TreasuryBalanceService::fromCents($cents),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$a[0], $a[2]] <=> [$b[0], $b[2]]);

        return [['Đối tác', 'Loại', 'Hình thức', 'Số tiền'], $rows];
    }

    /**
     * Self-approved expenses, advances and settlements (spec §5.3).
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function selfApproved(Project $project): array
    {
        $events = Approval::query()->whereIn('event', self::SELF_APPROVAL_EVENTS)->where('context->approval_mode', 'self_approval')
            ->whereIn('financial_document_id', Document::query()->where('project_id', (string) $project->id)->select('id'))
            ->orderBy('created_at')->orderBy('id')->get();
        $documents = Document::query()->whereIn('id', $events->pluck('financial_document_id')->all())->get()->keyBy('id');
        $users = $this->userNames($project);
        $rows = [];
        foreach ($events as $event) {
            $doc = $documents[(string) data_get($event, 'financial_document_id')] ?? null;
            $settlementId = (string) data_get($event, 'context.settlement_id');
            $amount = $settlementId !== '' ? (string) Settlement::query()->whereKey($settlementId)->value('amount') : (string) data_get($doc, 'amount');
            $rows[] = [
                Carbon::parse((string) data_get($event, 'created_at'))->toDateString(),
                match ((string) data_get($event, 'event')) {
                    TreasuryAdvanceService::EVENT_APPROVED => 'Tạm ứng',
                    TreasuryAdvanceService::EVENT_SETTLED => 'Quyết toán tạm ứng',
                    default => 'Chi phí',
                },
                (string) ($users[(string) data_get($event, 'actor_id')] ?? ''),
                TreasuryBalanceService::fromCents(TreasuryBalanceService::toCents($amount)),
                (string) data_get($doc, 'status'),
                (string) (data_get($doc, 'description') ?? ''),
            ];
        }

        return [['Ngày duyệt', 'Loại', 'Người lập và duyệt', 'Số tiền', 'Trạng thái chứng từ', 'Diễn giải'], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function unreconciled(Project $project, Carbon $today): array
    {
        $rows = [];
        /** @var \Illuminate\Database\Eloquent\Collection<int, TreasuryWallet> $wallets */
        $wallets = TreasuryWallet::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)->orderBy('name')->get();
        foreach ($wallets as $wallet) {
            foreach ($this->reconciliation->unreconciledEntries($wallet) as $entry) {
                $date = $entry['transaction_date'];
                $rows[] = [
                    (string) $wallet->name, (string) ($date ?? ''), self::TYPE_LABELS[$entry['document_type']] ?? $entry['document_type'],
                    $entry['direction'] === Entry::DIRECTION_CREDIT ? $entry['amount'] : '-' . $entry['amount'],
                    $date === null ? '' : (int) Carbon::parse($date)->startOfDay()->diffInDays($today, false),
                    (string) ($entry['reference'] ?? ''), (string) ($entry['description'] ?? ''),
                ];
            }
        }

        return [['Ví', 'Ngày', 'Loại', 'Số tiền (+ vào / − ra)', 'Số ngày chưa đối soát', 'Tham chiếu', 'Diễn giải'], $rows];
    }

    /**
     * Routes still on their way (planned or partial), with money held on the way.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function openRoutes(Project $project): array
    {
        $rows = [];
        foreach (Route::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)
            ->whereIn('status', [Route::STATUS_PLANNED, Route::STATUS_PARTIAL])->orderBy('created_at')->orderBy('id')->get() as $route) {
            /** @var Route $route */
            $detail = $this->routes->detail($route);
            $holding = [];
            foreach ((array) $detail['net_by_wallet'] as $w) {
                if (data_get($w, 'wallet_id') !== $detail['destination_wallet_id'] && TreasuryBalanceService::toCents((string) data_get($w, 'net')) !== 0) {
                    $holding[] = (data_get($w, 'wallet_name') ?? '?') . ': ' . data_get($w, 'net');
                }
            }
            $holding = implode('; ', $holding);
            $rows[] = [
                (string) ($detail['transaction_date'] ?? ''),
                $detail['contract_payment_id'] !== null ? 'Thanh toán HĐ: ' . $detail['contract_payment_name'] : (self::TYPE_LABELS[$detail['document_type']] ?? $detail['document_type']),
                $detail['status'], $detail['total_amount'], $detail['arrived_amount'], $detail['remaining_amount'], $holding,
            ];
        }

        return [['Ngày', 'Khoản', 'Trạng thái tuyến', 'Tổng', 'Đã về ví đích', 'Còn lại', 'Đang giữ ở ví trung gian'], $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    private function corrections(Project $project): array
    {
        $documents = Document::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)
            ->whereIn('document_type', [Document::TYPE_REVERSAL, Document::TYPE_ADJUSTMENT])
            ->orderBy('transaction_date')->orderBy('created_at')->get();
        $originals = Document::query()->whereIn('id', $documents->pluck('reversed_document_id')->filter()->all())->get()->keyBy('id');
        $users = $this->userNames($project);
        $rows = [];
        foreach ($documents as $doc) {
            /** @var Document $doc */
            $original = $originals[(string) $doc->reversed_document_id] ?? null;
            $rows[] = [
                $doc->transaction_date?->toDateString() ?? '',
                $doc->document_type === Document::TYPE_REVERSAL
                    ? 'Đảo ' . mb_strtolower(self::TYPE_LABELS[(string) data_get($original, 'document_type')] ?? '')
                    : ($doc->destination_wallet_id !== null ? 'Điều chỉnh tăng' : 'Điều chỉnh giảm'),
                (string) $doc->amount,
                $original === null ? '' : (string) data_get($original, 'transaction_date')?->toDateString() . ' ' . (string) data_get($original, 'amount'),
                (string) ($doc->description ?? ''),
                (string) ($users[(string) $doc->created_by] ?? ''),
            ];
        }

        return [['Ngày', 'Loại', 'Số tiền', 'Chứng từ gốc', 'Lý do', 'Người thực hiện'], $rows];
    }

    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function projectAllocations(Project $project): \Illuminate\Support\Collection
    {
        $documentIds = Document::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)->select('id');
        $settlementIds = Settlement::query()->whereIn('advance_id', Advance::query()->where('project_id', (string) $project->id)->select('id'))->select('id');

        return Allocation::query()
            ->where(static fn ($q) => $q->whereIn('financial_document_id', $documentIds)->orWhereIn('advance_settlement_id', $settlementIds))
            ->get(['cost_source_contract_expense_id', 'direction', 'allocated_amount'])
            ->toBase();
    }

    /**
     * @return list<string>
     */
    private function walletIds(Project $project): array
    {
        return TreasuryWallet::query()->where('tenant_id', (string) $project->tenant_id)->where('project_id', (string) $project->id)
            ->pluck('id')->map(static fn ($id): string => (string) $id)->all();
    }

    /**
     * @return array<string, string>
     */
    private function userNames(Project $project): array
    {
        return User::query()->where('tenant_id', (string) $project->tenant_id)->pluck('name', 'id')->map(static fn ($n): string => (string) $n)->all();
    }
}
