<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Payroll\OverheadPeriod;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * GAP-114 (Gate 2 R-1, D-1, A-1, C-1, V-1) — project profit and loss.
 *
 * - Revenue (R-1): contract payments marked paid, by paid date.
 * - Direct cost (D-1): posted Treasury expenses (reversed ones carry status reversed and are left out), by transaction
 *   date, or the posting date when none.
 * - Contract expenses: shown for reference only.
 * - Labour: GAP-113. Overhead: A-1.
 * - Profit = revenue − direct cost − labour − overhead.
 * - month(): stored figures once the overhead month is closed, a preview otherwise.
 * - cumulative() and projectSummary(): closed overhead months only, plus projected profit on live contract value.
 * - Every read runs a fixed number of queries. Callers check access: the page needs payroll.manage; the OPPM line is for
 *   payroll.manage or the project's PM (V-1).
 */
class ProjectPnlService
{
    public function __construct(private readonly OverheadService $overhead)
    {
    }

    /**
     * @return array{
     *     closed: bool,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, string>
     * }
     */
    public function month(string $tenantId, string $month): array
    {
        OverheadService::requireMonth($month);
        $period = $this->overhead->periodFor($tenantId, $month);
        $closed = $period !== null && $period->isClosed();
        $revenue = $this->grouped($this->revenueQuery($tenantId), [$month]);
        $direct = $this->grouped($this->treasuryQuery($tenantId), [$month]);
        $reference = $this->grouped($this->contractExpenseQuery($tenantId), [$month]);

        if ($closed) {
            $labour = $this->grouped($this->labourQuery($tenantId), [$month]);
            $overhead = $this->grouped($this->overheadQuery($tenantId), [$month]);
            $unallocated = $overhead[''] ?? 0;
            unset($overhead['']);
        } else {
            [, , $labour] = $this->overhead->labourFor($tenantId, $month);
            $preview = $this->overhead->compute($period ?? new OverheadPeriod(['tenant_id' => $tenantId, 'period' => $month]));
            $overhead = [];
            $unallocated = 0;
            foreach ($preview['rows'] as $row) {
                if ($row['project_id'] === null) {
                    $unallocated += PayrollService::toCents($row['amount']);
                } else {
                    $overhead[$row['project_id']] = PayrollService::toCents($row['amount']);
                }
            }
        }

        $rows = $this->rows($tenantId, $revenue, $direct, $reference, $labour, $overhead, []);

        return ['closed' => $closed, 'rows' => $rows, 'totals' => self::totals($rows, $unallocated)];
    }

    /**
     * @return array{months: list<string>, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function cumulative(string $tenantId): array
    {
        /** @var list<string> $months */
        $months = OverheadPeriod::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('status', OverheadPeriod::STATUS_CLOSED)->orderBy('period')->pluck('period')->map(static fn ($p): string => (string) $p)->all();
        if ($months === []) {
            return ['months' => [], 'rows' => [], 'totals' => self::totals([], 0)];
        }
        $overhead = $this->grouped($this->overheadQuery($tenantId), $months);
        $unallocated = $overhead[''] ?? 0;
        unset($overhead['']);
        $contractValue = [];
        foreach (DB::table('contracts')->where('tenant_id', $tenantId)->where('status', '!=', Contract::STATUS_CANCELLED)
            ->groupBy('project_id')->selectRaw('project_id, SUM(total_value) as total')->get() as $row) {
            $contractValue[(string) data_get($row, 'project_id')] = (int) round((float) data_get($row, 'total', 0) * 100);
        }
        $rows = $this->rows(
            $tenantId,
            $this->grouped($this->revenueQuery($tenantId), $months),
            $this->grouped($this->treasuryQuery($tenantId), $months),
            $this->grouped($this->contractExpenseQuery($tenantId), $months),
            $this->grouped($this->labourQuery($tenantId), $months),
            $overhead,
            $contractValue,
        );

        return ['months' => $months, 'rows' => $rows, 'totals' => self::totals($rows, $unallocated)];
    }

    /**
     * V-1: closed-month totals for one project.
     *
     * @return array{months: int, revenue: string, total_cost: string, profit: string, contract_value: string, projected_profit: string}
     */
    public function projectSummary(Project $project): array
    {
        $cumulative = $this->cumulative((string) $project->tenant_id);
        $row = null;
        foreach ($cumulative['rows'] as $candidate) {
            if ($candidate['project_id'] === (string) $project->id) {
                $row = $candidate;
            }
        }

        return [
            'months' => $row === null ? 0 : count($cumulative['months']),
            'revenue' => $row === null ? '0.00' : (string) $row['revenue'],
            'total_cost' => $row === null ? '0.00' : (string) $row['total_cost'],
            'profit' => $row === null ? '0.00' : (string) $row['profit'],
            'contract_value' => $row === null ? '0.00' : (string) $row['contract_value'],
            'projected_profit' => $row === null ? '0.00' : (string) $row['projected_profit'],
        ];
    }

    public function csv(string $tenantId, string $month): string
    {
        $data = $this->month($tenantId, $month);
        $status = $data['closed'] ? 'Đã chốt' : 'Tạm tính';
        $out = fopen('php://temp', 'r+');
        assert($out !== false);
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Tháng', 'Trạng thái', 'Công trình', 'Doanh thu đã thu', 'Chi Ngân quỹ', 'Nhân công', 'Chi phí chung', 'Tổng chi phí', 'Lời / lỗ', 'Tỷ suất (%)', 'Chi phí ghi nhận HĐ (đối chiếu)']);
        foreach ($data['rows'] as $row) {
            fputcsv($out, array_map([self::class, 'safeCell'], [
                $month, $status, $row['project'], $row['revenue'], $row['direct_cost'], $row['labour'], $row['overhead'], $row['total_cost'],
                $row['profit'], $row['margin'] === null ? '' : (string) $row['margin'], $row['contract_expenses'],
            ]));
        }
        $t = $data['totals'];
        fputcsv($out, [$month, $status, 'Toàn công ty', $t['revenue'], $t['direct_cost'], $t['labour'], $t['overhead'], $t['total_cost'], $t['profit'], '', '']);
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * @param array<string, int> $revenue
     * @param array<string, int> $direct
     * @param array<string, int> $reference
     * @param array<string, int> $labour
     * @param array<string, int> $overhead
     * @param array<string, int> $contractValue cumulative only
     * @return list<array<string, mixed>>
     */
    private function rows(string $tenantId, array $revenue, array $direct, array $reference, array $labour, array $overhead, array $contractValue): array
    {
        $ids = array_values(array_unique(array_map('strval', array_filter(
            [...array_keys($revenue), ...array_keys($direct), ...array_keys($reference), ...array_keys($labour), ...array_keys($overhead), ...array_keys($contractValue)],
            static fn ($id): bool => $id !== '',
        ))));
        $names = $ids === [] ? [] : DB::table('projects')->where('tenant_id', $tenantId)->whereIn('id', $ids)->pluck('name', 'id')->all();
        $rows = [];
        foreach ($ids as $id) {
            $cost = ($direct[$id] ?? 0) + ($labour[$id] ?? 0) + ($overhead[$id] ?? 0);
            $profit = ($revenue[$id] ?? 0) - $cost;
            $rows[] = [
                'project_id' => $id,
                'project' => (string) ($names[$id] ?? $id),
                'revenue' => PayrollService::fromCents($revenue[$id] ?? 0),
                'direct_cost' => PayrollService::fromCents($direct[$id] ?? 0),
                'contract_expenses' => PayrollService::fromCents($reference[$id] ?? 0),
                'labour' => PayrollService::fromCents($labour[$id] ?? 0),
                'overhead' => PayrollService::fromCents($overhead[$id] ?? 0),
                'total_cost' => PayrollService::fromCents($cost),
                'profit' => PayrollService::fromCents($profit),
                'margin' => ($revenue[$id] ?? 0) > 0 ? round($profit * 100 / $revenue[$id], 1) : null,
                'contract_value' => PayrollService::fromCents($contractValue[$id] ?? 0),
                'projected_profit' => PayrollService::fromCents(($contractValue[$id] ?? 0) - $cost),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['project'], (string) $b['project']));

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, string>
     */
    private static function totals(array $rows, int $unallocated): array
    {
        $sum = static function (string $key) use ($rows): int {
            $total = 0;
            foreach ($rows as $row) {
                $total += PayrollService::toCents((string) $row[$key]);
            }

            return $total;
        };
        $cost = $sum('total_cost') + $unallocated;

        return [
            'revenue' => PayrollService::fromCents($sum('revenue')),
            'direct_cost' => PayrollService::fromCents($sum('direct_cost')),
            'labour' => PayrollService::fromCents($sum('labour')),
            'overhead' => PayrollService::fromCents($sum('overhead')),
            'unallocated_overhead' => PayrollService::fromCents($unallocated),
            'total_cost' => PayrollService::fromCents($cost),
            'profit' => PayrollService::fromCents($sum('revenue') - $cost),
        ];
    }

    /**
     * Sums a query selecting project_id, ym and total over the given months, per project ('' = no project). One query.
     *
     * @param list<string> $months
     * @return array<string, int> cents
     */
    private function grouped(Builder $query, array $months): array
    {
        $allowed = array_flip($months);
        $out = [];
        foreach ($query->get() as $row) {
            if (!isset($allowed[(string) data_get($row, 'ym')])) {
                continue;
            }
            $key = data_get($row, 'project_id') === null ? '' : (string) data_get($row, 'project_id');
            $out[$key] = ($out[$key] ?? 0) + (int) round((float) data_get($row, 'total', 0) * 100);
        }

        return $out;
    }

    private function revenueQuery(string $tenantId): Builder
    {
        return DB::table('contract_payments')
            ->join('contracts', 'contracts.id', '=', 'contract_payments.contract_id')
            ->where('contract_payments.tenant_id', $tenantId)
            ->where('contracts.tenant_id', $tenantId)
            ->where('contract_payments.status', ContractPayment::STATUS_PAID)
            ->whereNotNull('contract_payments.paid_at')
            ->groupByRaw('contracts.project_id, SUBSTR(contract_payments.paid_at, 1, 7)')
            ->selectRaw('contracts.project_id as project_id, SUBSTR(contract_payments.paid_at, 1, 7) as ym, SUM(contract_payments.amount) as total');
    }

    private function treasuryQuery(string $tenantId): Builder
    {
        return DB::table('treasury_financial_documents')
            ->where('tenant_id', $tenantId)
            ->where('document_type', Document::TYPE_EXPENSE)
            ->whereIn('status', [Document::STATUS_POSTED_UNRECONCILED, Document::STATUS_POSTED_RECONCILED])
            ->groupByRaw('project_id, SUBSTR(COALESCE(transaction_date, posted_at), 1, 7)')
            ->selectRaw('project_id, SUBSTR(COALESCE(transaction_date, posted_at), 1, 7) as ym, SUM(amount) as total');
    }

    private function contractExpenseQuery(string $tenantId): Builder
    {
        return DB::table('contract_expenses')
            ->join('contracts', 'contracts.id', '=', 'contract_expenses.contract_id')
            ->where('contract_expenses.tenant_id', $tenantId)
            ->where('contracts.tenant_id', $tenantId)
            ->groupByRaw('contracts.project_id, SUBSTR(contract_expenses.expense_date, 1, 7)')
            ->selectRaw('contracts.project_id as project_id, SUBSTR(contract_expenses.expense_date, 1, 7) as ym, SUM(contract_expenses.amount) as total');
    }

    private function labourQuery(string $tenantId): Builder
    {
        return DB::table('labour_cost_allocations')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'labour_cost_allocations.payroll_period_id')
            ->where('labour_cost_allocations.tenant_id', $tenantId)
            ->where('payroll_periods.status', PayrollPeriod::STATUS_CLOSED)
            ->whereNotNull('labour_cost_allocations.project_id')
            ->groupBy('labour_cost_allocations.project_id', 'payroll_periods.period')
            ->selectRaw('labour_cost_allocations.project_id as project_id, payroll_periods.period as ym, SUM(labour_cost_allocations.amount) as total');
    }

    private function overheadQuery(string $tenantId): Builder
    {
        return DB::table('overhead_allocations')
            ->join('overhead_periods', 'overhead_periods.id', '=', 'overhead_allocations.overhead_period_id')
            ->where('overhead_allocations.tenant_id', $tenantId)
            ->where('overhead_periods.status', OverheadPeriod::STATUS_CLOSED)
            ->groupBy('overhead_allocations.project_id', 'overhead_periods.period')
            ->selectRaw('overhead_allocations.project_id as project_id, overhead_periods.period as ym, SUM(overhead_allocations.amount) as total');
    }

    private static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '@', "\t", "\r", '-'], true) && !is_numeric($value) ? "'" . $value : $value;
    }
}
