<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Models\Notification;
use App\Models\Task;
use App\Services\Notifications\SlackChannelService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GAP-124 (Gate 2 O-1) — daily overdue-task digest. An overdue task is open (pending, in progress or on hold — the same set
 * "Hôm nay" uses) with an end date before today. Each project's PM gets one in-app notification per project and day
 * (event_key "tasks.overdue_digest:<project>:<date>"); the company's Slack channel, when set up, gets one summary per day
 * with the count per project and the longest-overdue tasks. Running twice a day writes and posts nothing new.
 */
class OverdueTaskDigestService
{
    private const OPEN = [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_ON_HOLD];

    private const LIST_LIMIT = 5;

    public function __construct(private readonly SlackChannelService $slack)
    {
    }

    /**
     * @return list<array{tenant_id: string, project_id: string, project_name: string, pm_id: ?string, count: int, oldest: list<array{name: string, end_date: string, days: int}>}>
     */
    public function plan(Carbon $today): array
    {
        $rows = DB::table('tasks')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->whereIn('tasks.status', self::OPEN)
            ->whereNull('tasks.deleted_at')
            ->whereNull('projects.deleted_at')
            ->whereNotNull('tasks.end_date')
            ->whereDate('tasks.end_date', '<', $today->toDateString())
            ->whereColumn('projects.tenant_id', 'tasks.tenant_id')
            ->orderBy('tasks.tenant_id')
            ->orderBy('projects.name')
            ->orderBy('tasks.end_date')
            ->get(['tasks.tenant_id', 'tasks.project_id', 'projects.name as project_name', 'projects.pm_id', 'tasks.name', 'tasks.title', 'tasks.end_date']);

        $projects = [];
        foreach ($rows as $row) {
            $key = $row->tenant_id . '|' . $row->project_id;
            $projects[$key] ??= [
                'tenant_id' => (string) $row->tenant_id,
                'project_id' => (string) $row->project_id,
                'project_name' => (string) $row->project_name,
                'pm_id' => $row->pm_id !== null ? (string) $row->pm_id : null,
                'count' => 0,
                'oldest' => [],
            ];
            $projects[$key]['count']++;
            if (count($projects[$key]['oldest']) < self::LIST_LIMIT) {
                $end = Carbon::parse(substr((string) $row->end_date, 0, 10));
                $projects[$key]['oldest'][] = [
                    'name' => (string) ($row->name ?? $row->title ?? ''),
                    'end_date' => $end->toDateString(),
                    'days' => (int) $end->diffInDays($today),
                ];
            }
        }

        return array_values($projects);
    }

    /**
     * @return array{notifications: int, slack_messages: int}
     */
    public function send(Carbon $today): array
    {
        $date = $today->toDateString();
        $written = 0;
        $posted = 0;
        $byTenant = [];
        foreach ($this->plan($today) as $project) {
            $byTenant[$project['tenant_id']][] = $project;
            if ($project['pm_id'] === null || !$this->activeUser($project['tenant_id'], $project['pm_id'])) {
                continue;
            }
            $eventKey = "tasks.overdue_digest:{$project['project_id']}:{$date}";
            $exists = Notification::query()->withoutGlobalScopes()
                ->where('tenant_id', $project['tenant_id'])
                ->where('user_id', $project['pm_id'])
                ->where('event_key', $eventKey)
                ->exists();
            if ($exists) {
                continue;
            }
            Notification::query()->withoutGlobalScopes()->create([
                'tenant_id' => $project['tenant_id'],
                'user_id' => $project['pm_id'],
                'type' => 'tasks.overdue_digest',
                'priority' => Notification::PRIORITY_NORMAL,
                'title' => "Dự án {$project['project_name']} có {$project['count']} việc quá hạn",
                'body' => $this->lines($project),
                'link_url' => route('app.projects.show', $project['project_id'], false),
                'channel' => Notification::CHANNEL_INAPP,
                'event_key' => $eventKey,
                'project_id' => $project['project_id'],
            ]);
            $written++;
        }

        foreach ($byTenant as $tenantId => $projects) {
            if ($this->slack->webhookUrl((string) $tenantId) === null || !Cache::add("overdue-digest-slack:{$tenantId}:{$date}", 1, 36 * 3600)) {
                continue;
            }
            $total = array_sum(array_column($projects, 'count'));
            $text = '*Việc quá hạn ngày ' . $today->format('d/m/Y') . ": {$total} việc*";
            foreach ($projects as $project) {
                $text .= "\n\n*{$project['project_name']}: {$project['count']} việc* <" . route('app.projects.show', $project['project_id']) . "|Mở dự án>\n" . $this->lines($project);
            }
            if ($this->slack->post((string) $tenantId, $text)) {
                $posted++;
            } else {
                Cache::forget("overdue-digest-slack:{$tenantId}:{$date}");
            }
        }

        return ['notifications' => $written, 'slack_messages' => $posted];
    }

    /**
     * @param array{count: int, oldest: list<array{name: string, end_date: string, days: int}>} $project
     */
    private function lines(array $project): string
    {
        $lines = array_map(
            static fn (array $t): string => '• ' . $t['name'] . ' — hạn ' . Carbon::parse($t['end_date'])->format('d/m') . " (trễ {$t['days']} ngày)",
            $project['oldest'],
        );
        if ($project['count'] > count($project['oldest'])) {
            $lines[] = '… và ' . ($project['count'] - count($project['oldest'])) . ' việc khác';
        }

        return implode("\n", $lines);
    }

    private function activeUser(string $tenantId, string $userId): bool
    {
        return DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->where('is_active', true)->exists();
    }
}
