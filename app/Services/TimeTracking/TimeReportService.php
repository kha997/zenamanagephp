<?php

declare(strict_types=1);

namespace App\Services\TimeTracking;

use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GAP-112 (Gate 2 P-1) — hours only, no money. Scope: payroll.manage sees the whole company (non-project time
 * included); project.update sees every project; a project PM sees own projects. One grouped query per report.
 */
class TimeReportService
{
    public function canView(User $user): bool
    {
        return $user->hasPermission('payroll.manage')
            || $user->hasPermission('project.update')
            || DB::table('projects')->where('tenant_id', (string) $user->tenant_id)->where('pm_id', (string) $user->id)->exists();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total_minutes: int}
     */
    public function byProject(User $user, Carbon $from, Carbon $to): array
    {
        $rows = $this->base($user, $from, $to)
            ->groupBy('time_entries.project_id', 'projects.name', 'time_entries.category', 'time_entries.user_id', 'users.name')
            ->orderBy('projects.name')
            ->orderBy('users.name')
            ->get([
                'projects.name as project', 'time_entries.category', 'users.name as person',
                DB::raw("SUM(CASE WHEN time_entries.status = 'approved' THEN time_entries.minutes ELSE 0 END) AS approved_minutes"),
                DB::raw("SUM(CASE WHEN time_entries.status <> 'approved' THEN time_entries.minutes ELSE 0 END) AS pending_minutes"),
            ]);

        return $this->shape($rows);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total_minutes: int}
     */
    public function byPerson(User $user, Carbon $from, Carbon $to): array
    {
        $rows = $this->base($user, $from, $to)
            ->groupBy('time_entries.user_id', 'users.name', 'time_entries.project_id', 'projects.name', 'time_entries.category')
            ->orderBy('users.name')
            ->orderBy('projects.name')
            ->get([
                'users.name as person', 'projects.name as project', 'time_entries.category',
                DB::raw("SUM(CASE WHEN time_entries.status = 'approved' THEN time_entries.minutes ELSE 0 END) AS approved_minutes"),
                DB::raw("SUM(CASE WHEN time_entries.status <> 'approved' THEN time_entries.minutes ELSE 0 END) AS pending_minutes"),
            ]);

        return $this->shape($rows);
    }

    public function csv(User $user, Carbon $from, Carbon $to): string
    {
        $out = fopen('php://temp', 'r+');
        assert($out !== false);
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Công trình / loại việc', 'Người', 'Giờ đã duyệt', 'Giờ chờ duyệt']);
        foreach ($this->byProject($user, $from, $to)['rows'] as $row) {
            fputcsv($out, array_map([self::class, 'safe'], [
                $row['project'] ?? (TimeEntry::CATEGORIES[(string) $row['category']] ?? 'Không thuộc công trình'),
                $row['person'],
                number_format($row['approved_minutes'] / 60, 2, '.', ''),
                number_format($row['pending_minutes'] / 60, 2, '.', ''),
            ]));
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private function base(User $user, Carbon $from, Carbon $to): Builder
    {
        $all = $user->hasPermission('payroll.manage');
        $allProjects = $all || $user->hasPermission('project.update');
        $userId = (string) $user->id;

        return DB::table('time_entries')
            ->leftJoin('projects', 'projects.id', '=', 'time_entries.project_id')
            ->join('users', 'users.id', '=', 'time_entries.user_id')
            ->where('time_entries.tenant_id', (string) $user->tenant_id)
            ->whereIn('time_entries.status', [TimeEntry::STATUS_SUBMITTED, TimeEntry::STATUS_APPROVED])
            ->whereDate('time_entries.work_date', '>=', $from->toDateString())
            ->whereDate('time_entries.work_date', '<=', $to->toDateString())
            ->when(!$all, static fn ($q) => $q->whereNotNull('time_entries.project_id'))
            ->when(!$allProjects, static fn ($q) => $q->where('projects.pm_id', $userId));
    }

    /**
     * @param \Illuminate\Support\Collection<int, \stdClass> $rows
     * @return array{rows: list<array<string, mixed>>, total_minutes: int}
     */
    private function shape(\Illuminate\Support\Collection $rows): array
    {
        $out = [];
        $total = 0;
        foreach ($rows as $row) {
            $approved = (int) data_get($row, 'approved_minutes', 0);
            $pending = (int) data_get($row, 'pending_minutes', 0);
            $total += $approved + $pending;
            $out[] = [
                'project' => data_get($row, 'project'),
                'category' => data_get($row, 'category'),
                'person' => (string) data_get($row, 'person'),
                'approved_minutes' => $approved,
                'pending_minutes' => $pending,
            ];
        }

        return ['rows' => $out, 'total_minutes' => $total];
    }

    private static function safe(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
