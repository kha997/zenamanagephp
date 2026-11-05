<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\AuditLog;
use App\Models\Payroll\LabourCostAllocation;
use App\Models\Payroll\PayrollLine;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\StaffMember;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GAP-113 (Gate 2 S-1, H-1, R-1, O-1) — splits each person's monthly company cost (GAP-109) over projects using approved
 * hours (GAP-112).
 *
 * - R-1: project amount = company cost × approved project minutes ÷ max(total approved minutes, standard minutes).
 *   Below standard hours a project gets hours × hourly cost; above, shares scale so the total equals the real cost.
 *   Project amounts are whole đồng, split by largest remainder; the rest (non-project hours, idle time, cents) is the
 *   row without project.
 * - O-1: office staff (is_overhead), staff without a login, or staff without project hours: whole cost outside projects.
 * - Approved hours of a login with no payroll line in the month are returned as unpriced.
 * - S-1: allocate() stores the result at payroll close; clear() removes it on reopen.
 * - H-1: pendingEntries() lists running / submitted entries of the month.
 *
 * Money is in integer cents as in PayrollService. Shares use 1/5-minute units: standard hours are stored in cents of an
 * hour, so standard minutes × 5 = hour-cents × 3.
 */
class LabourCostService
{
    /**
     * Running or waiting entries in the payroll month, per user (H-1).
     *
     * @return array<string, int>
     */
    public function pendingEntries(PayrollPeriod $period): array
    {
        [$from, $to] = self::range($period);
        $counts = DB::table('time_entries')
            ->where('tenant_id', (string) $period->tenant_id)
            ->whereIn('status', [TimeEntry::STATUS_RUNNING, TimeEntry::STATUS_SUBMITTED])
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as entries')
            ->get();
        $out = [];
        foreach ($counts as $row) {
            $out[(string) data_get($row, 'user_id')] = (int) data_get($row, 'entries');
        }
        ksort($out);

        return $out;
    }

    /**
     * Pure computation from the current payroll lines and approved hours; nothing is written. Three queries.
     *
     * @return array{
     *     rows: list<array{staff_member_id: string, project_id: string|null, minutes: int, amount: string}>,
     *     unpriced: list<array{user_id: string, project_id: string|null, minutes: int}>
     * }
     */
    public function compute(PayrollPeriod $period): array
    {
        [$from, $to] = self::range($period);
        /** @var Collection<int, PayrollLine> $lines */
        $lines = PayrollLine::query()->withoutGlobalScopes()
            ->with(['staffMember' => static fn ($q) => $q->withoutGlobalScopes()])
            ->where('tenant_id', (string) $period->tenant_id)
            ->where('payroll_period_id', (string) $period->id)
            ->get()
            ->sortBy(static fn (PayrollLine $line): string => (string) $line->staffMember->code)
            ->values();
        $hours = self::approvedMinutes((string) $period->tenant_id, $from, $to);

        $rows = [];
        $claimed = [];
        foreach ($lines as $line) {
            $staff = $line->staffMember;
            $own = [];
            $userId = $staff->user_id === null ? null : (string) $staff->user_id;
            if ($userId !== null && !isset($claimed[$userId])) {
                $claimed[$userId] = true;
                $own = $hours[$userId] ?? [];
            }
            foreach ($this->split($staff, PayrollService::toCents((string) $line->company_cost), PayrollService::toCents((string) $line->standard_hours), $own) as $row) {
                $rows[] = $row;
            }
        }

        $unpriced = [];
        foreach ($hours as $userId => $byProject) {
            if (isset($claimed[$userId])) {
                continue;
            }
            foreach ($byProject as $projectKey => $minutes) {
                $unpriced[] = ['user_id' => (string) $userId, 'project_id' => $projectKey === '' ? null : (string) $projectKey, 'minutes' => $minutes];
            }
        }

        return ['rows' => $rows, 'unpriced' => $unpriced];
    }

    /**
     * S-1: replaces the stored allocation of the month. Call inside the payroll close transaction, after the lines are
     * recomputed.
     *
     * @return array{project_total: string, outside_total: string}
     */
    public function allocate(User $actor, PayrollPeriod $period): array
    {
        $result = $this->compute($period);
        LabourCostAllocation::query()->withoutGlobalScopes()->where('payroll_period_id', (string) $period->id)->delete();
        $project = 0;
        $outside = 0;
        foreach ($result['rows'] as $row) {
            LabourCostAllocation::query()->create($row + [
                'tenant_id' => (string) $period->tenant_id,
                'payroll_period_id' => (string) $period->id,
            ]);
            $cents = PayrollService::toCents($row['amount']);
            if ($row['project_id'] === null) {
                $outside += $cents;
            } else {
                $project += $cents;
            }
        }
        $totals = ['project_total' => PayrollService::fromCents($project), 'outside_total' => PayrollService::fromCents($outside)];
        $this->audit($actor, $period, 'labour_cost.allocated', $totals + [
            'period' => $period->period, 'rows' => count($result['rows']), 'unpriced_minutes' => array_sum(array_column($result['unpriced'], 'minutes')),
        ]);

        return $totals;
    }

    /**
     * S-1: removes the stored allocation when the month is reopened.
     */
    public function clear(User $actor, PayrollPeriod $period): void
    {
        $deleted = LabourCostAllocation::query()->withoutGlobalScopes()->where('payroll_period_id', (string) $period->id)->delete();
        $this->audit($actor, $period, 'labour_cost.cleared', ['period' => $period->period, 'rows' => $deleted]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function range(PayrollPeriod $period): array
    {
        $first = $period->period . '-01';

        return [$first, date('Y-m-t', (int) strtotime($first))];
    }

    /**
     * Approved minutes of the month per user and project ('' = not project work). One query.
     *
     * @return array<string, array<string, int>>
     */
    public static function approvedMinutes(string $tenantId, string $from, string $to): array
    {
        $rows = DB::table('time_entries')
            ->where('tenant_id', $tenantId)
            ->where('status', TimeEntry::STATUS_APPROVED)
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->groupBy('user_id', 'project_id')
            ->selectRaw('user_id, project_id, SUM(minutes) as total_minutes')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $minutes = (int) data_get($row, 'total_minutes');
            if ($minutes <= 0) {
                continue;
            }
            $project = data_get($row, 'project_id');
            $key = $project === null ? '' : (string) $project;
            $user = (string) data_get($row, 'user_id');
            $out[$user][$key] = ($out[$user][$key] ?? 0) + $minutes;
        }
        ksort($out);

        return $out;
    }

    /**
     * @param array<string, int> $own approved minutes per project ('' = not project work)
     * @return list<array{staff_member_id: string, project_id: string|null, minutes: int, amount: string}>
     */
    private function split(StaffMember $staff, int $costCents, int $hourCents, array $own): array
    {
        $staffId = (string) $staff->id;
        $total = array_sum($own);
        $projects = $own;
        unset($projects['']);
        ksort($projects);
        $outsideMinutes = $own[''] ?? 0;

        if ($staff->is_overhead || $projects === []) {
            return $costCents === 0 && $total === 0 ? [] : [
                ['staff_member_id' => $staffId, 'project_id' => null, 'minutes' => $total, 'amount' => PayrollService::fromCents($costCents)],
            ];
        }

        $projectMinutes = array_sum($projects);
        $denominator = max($total * 5, $hourCents * 3);
        // Exact project total in cents = cost × projectMinutes × 5 ÷ denominator, rounded half up to whole đồng and never
        // above the cost floored to whole đồng.
        $dong = intdiv(2 * $costCents * $projectMinutes * 5 + $denominator * 100, 2 * $denominator * 100);
        $dong = min($dong, intdiv(max($costCents, 0), 100));

        $shares = [];
        $remainders = [];
        $given = 0;
        foreach ($projects as $projectId => $minutes) {
            $shares[$projectId] = intdiv($dong * $minutes, $projectMinutes);
            $remainders[$projectId] = ($dong * $minutes) % $projectMinutes;
            $given += $shares[$projectId];
        }
        $order = array_keys($remainders);
        usort($order, static fn ($a, $b): int => [$remainders[$b], (string) $a] <=> [$remainders[$a], (string) $b]);
        for ($i = 0, $left = $dong - $given; $i < $left; $i++) {
            $shares[$order[$i]]++;
        }

        $rows = [];
        foreach ($projects as $projectId => $minutes) {
            $rows[] = ['staff_member_id' => $staffId, 'project_id' => (string) $projectId, 'minutes' => $minutes, 'amount' => PayrollService::fromCents($shares[$projectId] * 100)];
        }
        $outside = $costCents - $dong * 100;
        if ($outside !== 0 || $outsideMinutes > 0) {
            $rows[] = ['staff_member_id' => $staffId, 'project_id' => null, 'minutes' => $outsideMinutes, 'amount' => PayrollService::fromCents($outside)];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function audit(User $actor, PayrollPeriod $period, string $action, array $data): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $actor->id,
            'tenant_id' => (string) $period->tenant_id,
            'action' => $action,
            'entity_type' => 'payroll_period',
            'entity_id' => (string) $period->id,
            'old_data' => null,
            'new_data' => $data,
        ]);
    }
}
