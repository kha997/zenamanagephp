<?php

declare(strict_types=1);

namespace App\Services\ProjectOppm;

use App\Models\Project;
use App\Models\Task;
use App\Services\ProjectHealth\ProjectHealthReadService;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GAP-104 — read model for the one-page Project OPPM (issue #248 Phase 1, Gate 2 V-1, W-A, T-1, R-1).
 * KPIs come from the shared ProjectHealthReadService (SSOT OWN-2026-009 §4/§5) without the Treasury alert count (T-1);
 * work groups are root components (W-A); attention lists are bounded. Read-only, tenant- and project-scoped, and a fixed
 * number of queries whatever the project size.
 */
class ProjectOppmReadService
{
    public const MAX_WORKSTREAMS = 12;
    public const MAX_LIST_ROWS = 10;

    private const CLOSED_TASK_STATUSES = [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED];

    private const SERVICE_LINE_LABELS = [
        ServiceLine::DESIGN => 'Thiết kế',
        ServiceLine::CONSTRUCTION => 'Thi công',
        ServiceLine::INSPECTION => 'Kiểm định',
    ];

    public function __construct(private readonly ProjectHealthReadService $health)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forProject(Project $project): array
    {
        $today = Carbon::now()->startOfDay();
        $health = $this->health->forProject($project, includeTreasuryAlerts: false);
        $workstreams = $this->workstreams($project, $today);

        return [
            'health' => $health,
            'service_lines' => $this->serviceLines($project),
            'workstreams' => array_slice($workstreams, 0, self::MAX_WORKSTREAMS),
            'workstream_total' => count($workstreams),
            'task_summary' => $this->taskSummary($project),
            'blocked_tasks' => [
                'rows' => $this->blockedTasks($project),
                'total' => (int) $health['attention']['blocked_tasks'],
            ],
            'overdue_tasks' => [
                'rows' => $this->overdueTasks($project, $today),
                'total' => (int) $health['attention']['overdue_tasks'],
            ],
            'blocked_design_items' => [
                'rows' => $this->blockedDesignItems($project),
                'total' => (int) $health['attention']['blocked_design_items'],
            ],
            'generated_at' => Carbon::now(),
        ];
    }

    /**
     * Missing classification stays "unclassified" (SSOT §2): no Service Line is ever assumed.
     *
     * @return list<array{code: string, label: string, confirmed: bool}>
     */
    private function serviceLines(Project $project): array
    {
        $rows = DB::table('project_service_lines')
            ->where('tenant_id', $this->tenantId($project))
            ->where('project_id', $this->projectId($project))
            ->orderBy('service_line')
            ->get(['service_line', 'provenance']);

        $lines = [];
        foreach ($rows as $row) {
            $code = (string) data_get($row, 'service_line');
            $confirmed = data_get($row, 'provenance') === ServiceLineProvenance::CONFIRMED;
            $label = self::SERVICE_LINE_LABELS[$code] ?? $code;
            $lines[] = ['code' => $code, 'label' => $confirmed ? $label : $label . ' (chưa xác nhận)', 'confirmed' => $confirmed];
        }

        return $lines;
    }

    /**
     * Gate 2 W-A: root components, ordered by start date; tasks on descendant components roll up to their root.
     *
     * @return list<array<string, mixed>>
     */
    private function workstreams(Project $project, Carbon $today): array
    {
        $components = DB::table('components')
            ->where('tenant_id', $this->tenantId($project))
            ->where('project_id', $this->projectId($project))
            ->get(['id', 'parent_component_id', 'name', 'status', 'progress_percent', 'planned_cost', 'actual_cost', 'start_date', 'end_date']);

        if ($components->isEmpty()) {
            return [];
        }

        $parents = [];
        foreach ($components as $component) {
            $parents[(string) data_get($component, 'id')] = data_get($component, 'parent_component_id');
        }

        $counts = $this->tasks($project)
            ->whereNotNull('component_id')
            ->whereNotIn('status', self::CLOSED_TASK_STATUSES)
            ->groupBy('component_id')
            ->selectRaw(
                'component_id, '
                . 'SUM(CASE WHEN blocked_at IS NOT NULL THEN 1 ELSE 0 END) AS blocked, '
                . 'SUM(CASE WHEN end_date IS NOT NULL AND end_date < ? THEN 1 ELSE 0 END) AS overdue',
                [$today->toDateTimeString()]
            )
            ->get();

        $rolled = [];
        foreach ($counts as $row) {
            $root = $this->rootOf((string) data_get($row, 'component_id'), $parents);
            if ($root === null) {
                continue;
            }
            $rolled[$root]['blocked'] = ($rolled[$root]['blocked'] ?? 0) + (int) data_get($row, 'blocked', 0);
            $rolled[$root]['overdue'] = ($rolled[$root]['overdue'] ?? 0) + (int) data_get($row, 'overdue', 0);
        }

        $roots = $components->filter(static fn ($component): bool => data_get($component, 'parent_component_id') === null)
            ->sortBy([
                static fn ($a, $b): int => (data_get($a, 'start_date') === null) <=> (data_get($b, 'start_date') === null),
                static fn ($a, $b): int => strcmp((string) data_get($a, 'start_date'), (string) data_get($b, 'start_date')),
                static fn ($a, $b): int => strcmp((string) data_get($a, 'name'), (string) data_get($b, 'name')),
            ]);

        $workstreams = [];
        foreach ($roots as $component) {
            $id = (string) data_get($component, 'id');
            $plannedCost = (float) data_get($component, 'planned_cost', 0);
            $actualCost = (float) data_get($component, 'actual_cost', 0);
            $workstreams[] = [
                'id' => $id,
                'name' => (string) data_get($component, 'name'),
                'status' => (string) data_get($component, 'status'),
                'progress_percent' => round((float) data_get($component, 'progress_percent', 0), 1),
                'planned_cost' => $plannedCost > 0 ? number_format($plannedCost, 2, '.', '') : null,
                'actual_cost' => $actualCost > 0 ? number_format($actualCost, 2, '.', '') : null,
                'start_date' => $this->date(data_get($component, 'start_date')),
                'end_date' => $this->date(data_get($component, 'end_date')),
                'blocked_tasks' => $rolled[$id]['blocked'] ?? 0,
                'overdue_tasks' => $rolled[$id]['overdue'] ?? 0,
                'timeline' => $this->timeline($project, data_get($component, 'start_date'), data_get($component, 'end_date')),
            ];
        }

        return $workstreams;
    }

    /**
     * @param array<string, mixed> $parents
     */
    private function rootOf(string $id, array $parents): ?string
    {
        $seen = [];
        while (array_key_exists($id, $parents) && $parents[$id] !== null) {
            if (isset($seen[$id])) {
                return null;
            }
            $seen[$id] = true;
            $id = (string) $parents[$id];
        }

        return array_key_exists($id, $parents) ? $id : null;
    }

    /**
     * Bar position as a percentage of the project's own date range; null when either range is incomplete.
     *
     * @return array{offset: float, width: float}|null
     */
    private function timeline(Project $project, mixed $start, mixed $end): ?array
    {
        $projectStart = $this->day($project->getAttribute('start_date'));
        $projectEnd = $this->day($project->getAttribute('end_date'));
        $from = $this->day($start);
        $to = $this->day($end);

        if ($projectStart === null || $projectEnd === null || $from === null || $to === null || $projectEnd->lessThan($projectStart) || $to->lessThan($from)) {
            return null;
        }

        $span = (int) $projectStart->diffInDays($projectEnd) + 1;
        $offset = max(0, min($span, (int) $projectStart->diffInDays($from, false)));
        $length = max(0, min($span - $offset, (int) $from->diffInDays($to) + 1));

        return [
            'offset' => round($offset / $span * 100, 1),
            'width' => round($length / $span * 100, 1),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function taskSummary(Project $project): array
    {
        $summary = [];
        foreach ($this->tasks($project)->groupBy('status')->selectRaw('status, COUNT(*) AS task_count')->get() as $row) {
            $summary[(string) data_get($row, 'status')] = (int) data_get($row, 'task_count', 0);
        }

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blockedTasks(Project $project): array
    {
        $rows = $this->tasks($project)
            ->whereNotIn('tasks.status', self::CLOSED_TASK_STATUSES)
            ->whereNotNull('tasks.blocked_at')
            ->leftJoin('users', 'users.id', '=', 'tasks.blocked_by')
            ->orderBy('tasks.blocked_at')
            ->limit(self::MAX_LIST_ROWS)
            ->get(['tasks.id', 'tasks.name', 'tasks.blocker_note', 'tasks.blocked_at', 'users.name as blocked_by_name']);

        return $rows->map(fn ($row): array => [
            'id' => (string) data_get($row, 'id'),
            'name' => (string) data_get($row, 'name'),
            'note' => data_get($row, 'blocker_note'),
            'by' => data_get($row, 'blocked_by_name'),
            'since' => $this->date(data_get($row, 'blocked_at')),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function overdueTasks(Project $project, Carbon $today): array
    {
        $rows = $this->tasks($project)
            ->whereNotIn('tasks.status', self::CLOSED_TASK_STATUSES)
            ->whereNotNull('tasks.end_date')
            ->where('tasks.end_date', '<', $today->toDateTimeString())
            ->leftJoin('users', 'users.id', '=', 'tasks.assigned_to')
            ->orderBy('tasks.end_date')
            ->limit(self::MAX_LIST_ROWS)
            ->get(['tasks.id', 'tasks.name', 'tasks.end_date', 'users.name as assignee_name']);

        return $rows->map(fn ($row): array => [
            'id' => (string) data_get($row, 'id'),
            'name' => (string) data_get($row, 'name'),
            'assignee' => data_get($row, 'assignee_name'),
            'due' => $this->date(data_get($row, 'end_date')),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blockedDesignItems(Project $project): array
    {
        $rows = DB::table('design_items')
            ->where('design_items.tenant_id', $this->tenantId($project))
            ->where('design_items.project_id', $this->projectId($project))
            ->whereNotNull('design_items.blocked_at')
            ->leftJoin('users', 'users.id', '=', 'design_items.blocked_by')
            ->orderBy('design_items.blocked_at')
            ->limit(self::MAX_LIST_ROWS)
            ->get(['design_items.id', 'design_items.name', 'design_items.blocker_note', 'design_items.blocked_at', 'users.name as blocked_by_name']);

        return $rows->map(fn ($row): array => [
            'id' => (string) data_get($row, 'id'),
            'name' => (string) data_get($row, 'name'),
            'note' => data_get($row, 'blocker_note'),
            'by' => data_get($row, 'blocked_by_name'),
            'since' => $this->date(data_get($row, 'blocked_at')),
        ])->values()->all();
    }

    private function tasks(Project $project): Builder
    {
        return DB::table('tasks')
            ->whereNull('tasks.deleted_at')
            ->where('tasks.tenant_id', $this->tenantId($project))
            ->where('tasks.project_id', $this->projectId($project));
    }

    private function day(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse(substr((string) $value, 0, 10))->startOfDay();
    }

    private function date(mixed $value): ?string
    {
        return $this->day($value)?->format('d/m/Y');
    }

    private function tenantId(Project $project): string
    {
        return (string) $project->getAttribute('tenant_id');
    }

    private function projectId(Project $project): string
    {
        return (string) $project->getKey();
    }
}
