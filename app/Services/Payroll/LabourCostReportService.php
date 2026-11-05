<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollPeriod;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * GAP-113 (Gate 2 S-1, D-1, V-1) — labour cost reads.
 *
 * - month(): a closed month comes from the stored allocation; a draft month is computed on the fly and marked as
 *   preview.
 * - cumulative() and projectTotal(): closed months only.
 * - Every read runs a fixed number of queries. Callers check access: the report needs payroll.manage; the project line
 *   is for payroll.manage or the project's PM.
 */
class LabourCostReportService
{
    public function __construct(private readonly LabourCostService $labour)
    {
    }

    /**
     * @return array{
     *     closed: bool,
     *     rows: list<array{project_id: string|null, project: string|null, code: string, name: string, minutes: int, amount: string}>,
     *     projects: list<array{project_id: string, project: string, minutes: int, amount: string}>,
     *     project_total: string,
     *     outside_total: string,
     *     unpriced: list<array{person: string, project: string|null, minutes: int}>
     * }
     */
    public function month(PayrollPeriod $period): array
    {
        $tenantId = (string) $period->tenant_id;
        if ($period->isClosed()) {
            $raw = DB::table('labour_cost_allocations')
                ->join('staff_members', 'staff_members.id', '=', 'labour_cost_allocations.staff_member_id')
                ->where('labour_cost_allocations.tenant_id', $tenantId)
                ->where('labour_cost_allocations.payroll_period_id', (string) $period->id)
                ->get(['labour_cost_allocations.project_id', 'labour_cost_allocations.minutes', 'labour_cost_allocations.amount',
                    'staff_members.code', 'staff_members.name', 'staff_members.user_id']);
            $rows = [];
            $claimed = [];
            foreach ($raw as $row) {
                $rows[] = [
                    'project_id' => data_get($row, 'project_id') === null ? null : (string) data_get($row, 'project_id'),
                    'code' => (string) data_get($row, 'code'),
                    'name' => (string) data_get($row, 'name'),
                    'minutes' => (int) data_get($row, 'minutes'),
                    'amount' => PayrollService::fromCents(PayrollService::toCents((string) data_get($row, 'amount'))),
                ];
                if ((int) data_get($row, 'minutes') > 0 && data_get($row, 'user_id') !== null) {
                    $claimed[(string) data_get($row, 'user_id')] = true;
                }
            }
            [$from, $to] = LabourCostService::range($period);
            $unpriced = [];
            foreach (LabourCostService::approvedMinutes($tenantId, $from, $to) as $userId => $byProject) {
                if (isset($claimed[$userId])) {
                    continue;
                }
                foreach ($byProject as $projectKey => $minutes) {
                    $unpriced[] = ['user_id' => (string) $userId, 'project_id' => $projectKey === '' ? null : (string) $projectKey, 'minutes' => $minutes];
                }
            }
        } else {
            $result = $this->labour->compute($period);
            $staff = [];
            $rows = [];
            foreach ($result['rows'] as $row) {
                $staff[$row['staff_member_id']] = true;
            }
            $people = DB::table('staff_members')->where('tenant_id', $tenantId)->whereIn('id', array_keys($staff))->get(['id', 'code', 'name'])->keyBy('id');
            foreach ($result['rows'] as $row) {
                $person = $people->get($row['staff_member_id']);
                $rows[] = [
                    'project_id' => $row['project_id'],
                    'code' => (string) data_get($person, 'code'),
                    'name' => (string) data_get($person, 'name'),
                    'minutes' => $row['minutes'],
                    'amount' => $row['amount'],
                ];
            }
            $unpriced = $result['unpriced'];
        }

        $projectIds = array_values(array_unique(array_filter([
            ...array_column($rows, 'project_id'), ...array_column($unpriced, 'project_id'),
        ], static fn ($id): bool => $id !== null)));
        $names = $projectIds === [] ? [] : DB::table('projects')->where('tenant_id', $tenantId)->whereIn('id', $projectIds)->pluck('name', 'id')->all();
        $userIds = array_values(array_unique(array_column($unpriced, 'user_id')));
        $users = $userIds === [] ? [] : DB::table('users')->where('tenant_id', $tenantId)->whereIn('id', $userIds)->pluck('name', 'id')->all();

        $projects = [];
        $projectTotal = 0;
        $outsideTotal = 0;
        foreach ($rows as $i => $row) {
            $rows[$i]['project'] = $row['project_id'] === null ? null : (string) ($names[$row['project_id']] ?? $row['project_id']);
            $cents = PayrollService::toCents($row['amount']);
            if ($row['project_id'] === null) {
                $outsideTotal += $cents;
                continue;
            }
            $projectTotal += $cents;
            $projects[$row['project_id']] ??= ['project_id' => $row['project_id'], 'project' => (string) $rows[$i]['project'], 'minutes' => 0, 'cents' => 0];
            $projects[$row['project_id']]['minutes'] += $row['minutes'];
            $projects[$row['project_id']]['cents'] += $cents;
        }
        usort($rows, static fn (array $a, array $b): int => [$a['project_id'] === null, $a['project'], $a['code']] <=> [$b['project_id'] === null, $b['project'], $b['code']]);
        $projects = array_values($projects);
        usort($projects, static fn (array $a, array $b): int => [$b['cents'], $a['project']] <=> [$a['cents'], $b['project']]);

        return [
            'closed' => $period->isClosed(),
            'rows' => $rows,
            'projects' => array_map(static fn (array $p): array => [
                'project_id' => $p['project_id'], 'project' => $p['project'], 'minutes' => $p['minutes'], 'amount' => PayrollService::fromCents($p['cents']),
            ], $projects),
            'project_total' => PayrollService::fromCents($projectTotal),
            'outside_total' => PayrollService::fromCents($outsideTotal),
            'unpriced' => array_map(static fn (array $u): array => [
                'person' => (string) ($users[$u['user_id']] ?? $u['user_id']),
                'project' => $u['project_id'] === null ? null : (string) ($names[$u['project_id']] ?? $u['project_id']),
                'minutes' => $u['minutes'],
            ], $unpriced),
        ];
    }

    /**
     * Closed months only, per project, largest first.
     *
     * @return list<array{project_id: string, project: string, minutes: int, amount: string, months: int}>
     */
    public function cumulative(string $tenantId): array
    {
        $rows = $this->closedTotals($tenantId)
            ->join('projects', 'projects.id', '=', 'labour_cost_allocations.project_id')
            ->groupBy('labour_cost_allocations.project_id', 'projects.name')
            ->selectRaw('labour_cost_allocations.project_id, projects.name as project_name, SUM(labour_cost_allocations.minutes) as total_minutes, SUM(labour_cost_allocations.amount) as total_amount, COUNT(DISTINCT labour_cost_allocations.payroll_period_id) as months')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'project_id' => (string) data_get($row, 'project_id'),
                'project' => (string) data_get($row, 'project_name'),
                'minutes' => (int) data_get($row, 'total_minutes'),
                'amount' => self::sum(data_get($row, 'total_amount')),
                'months' => (int) data_get($row, 'months'),
            ];
        }
        usort($out, static fn (array $a, array $b): int => [PayrollService::toCents($b['amount']), $a['project']] <=> [PayrollService::toCents($a['amount']), $b['project']]);

        return $out;
    }

    /**
     * Closed-month total for one project (V-1: total only, no per-person detail). One query.
     *
     * @return array{amount: string, minutes: int, months: int}
     */
    public function projectTotal(Project $project): array
    {
        $row = $this->closedTotals((string) $project->tenant_id)
            ->where('labour_cost_allocations.project_id', (string) $project->id)
            ->selectRaw('SUM(labour_cost_allocations.minutes) as total_minutes, SUM(labour_cost_allocations.amount) as total_amount, COUNT(DISTINCT labour_cost_allocations.payroll_period_id) as months')
            ->first();

        return [
            'amount' => self::sum(data_get($row, 'total_amount')),
            'minutes' => (int) data_get($row, 'total_minutes'),
            'months' => (int) data_get($row, 'months'),
        ];
    }

    /**
     * H-1 list for the payroll page: person name => running / waiting entries in the month. Two queries.
     *
     * @return array<string, int>
     */
    public function pendingByPerson(PayrollPeriod $period): array
    {
        $pending = $this->labour->pendingEntries($period);
        if ($pending === []) {
            return [];
        }
        $names = DB::table('users')->where('tenant_id', (string) $period->tenant_id)->whereIn('id', array_keys($pending))->pluck('name', 'id')->all();
        $out = [];
        foreach ($pending as $userId => $count) {
            $out[(string) ($names[$userId] ?? $userId)] = $count;
        }
        ksort($out);

        return $out;
    }

    public function csv(PayrollPeriod $period): string
    {
        $month = $this->month($period);
        $status = $month['closed'] ? 'Đã chốt' : 'Tạm tính';
        $out = fopen('php://temp', 'r+');
        assert($out !== false);
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Tháng', 'Trạng thái', 'Công trình', 'Mã', 'Họ tên', 'Giờ', 'Chi phí nhân công']);
        foreach ($month['rows'] as $row) {
            fputcsv($out, array_map([self::class, 'safeCell'], [
                $period->period, $status, $row['project'] ?? 'Chưa vào công trình', $row['code'], $row['name'],
                number_format($row['minutes'] / 60, 2, '.', ''), $row['amount'],
            ]));
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private function closedTotals(string $tenantId): \Illuminate\Database\Query\Builder
    {
        return DB::table('labour_cost_allocations')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'labour_cost_allocations.payroll_period_id')
            ->where('labour_cost_allocations.tenant_id', $tenantId)
            ->where('payroll_periods.status', PayrollPeriod::STATUS_CLOSED)
            ->whereNotNull('labour_cost_allocations.project_id');
    }

    private static function sum(mixed $value): string
    {
        return PayrollService::fromCents((int) round((float) ($value ?? 0) * 100));
    }

    private static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '@', "\t", "\r", '-'], true) && !is_numeric($value) ? "'" . $value : $value;
    }
}
