<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SchedulePageController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $projects = Project::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'code']);

        $selectedProjectId = (string) $request->query('project_id', '');
        if ($selectedProjectId === '' && $projects->isNotEmpty()) {
            $selectedProjectId = (string) $projects->first()->id;
        }

        $tasks = collect();
        $timeline = null;

        if ($selectedProjectId !== '' && $projects->firstWhere('id', $selectedProjectId)) {
            $tasks = Task::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', $selectedProjectId)
                ->whereNotNull('start_date')
                ->whereNotNull('end_date')
                ->orderBy('start_date')
                ->get(['id', 'tenant_id', 'project_id', 'name', 'title', 'status', 'start_date', 'end_date', 'progress_percent']);

            if ($tasks->isNotEmpty()) {
                $rangeStart = CarbonImmutable::parse($tasks->min('start_date'))->startOfDay();
                $rangeEnd = CarbonImmutable::parse($tasks->max('end_date'))->endOfDay();
                $totalDays = max(1, (int) ceil($rangeStart->diffInDays($rangeEnd)));

                $bars = $tasks->map(function (Task $task) use ($rangeStart, $totalDays): array {
                    $start = CarbonImmutable::parse($task->start_date)->startOfDay();
                    $end = CarbonImmutable::parse($task->end_date)->endOfDay();
                    $offsetDays = max(0, (int) floor($rangeStart->diffInDays($start)));
                    $durationDays = max(1, (int) ceil($start->diffInDays($end)));

                    return [
                        'id' => (string) $task->id,
                        'label' => (string) ($task->name ?? $task->title ?? $task->id),
                        'status' => (string) ($task->status ?? ''),
                        'progress' => (int) ($task->progress_percent ?? 0),
                        'start' => $start->format('d/m/Y'),
                        'end' => $end->format('d/m/Y'),
                        'offset_percent' => round($offsetDays / $totalDays * 100, 2),
                        'width_percent' => max(1.0, round($durationDays / $totalDays * 100, 2)),
                    ];
                });

                // Month tick marks across the range
                $months = [];
                $cursor = $rangeStart->startOfMonth();
                while ($cursor->lessThanOrEqualTo($rangeEnd)) {
                    $offsetDays = max(0, (int) floor($rangeStart->diffInDays($cursor)));
                    $months[] = [
                        'label' => $cursor->format('m/Y'),
                        'offset_percent' => round($offsetDays / $totalDays * 100, 2),
                    ];
                    $cursor = $cursor->addMonth();
                }

                $timeline = [
                    'range_start' => $rangeStart->format('d/m/Y'),
                    'range_end' => $rangeEnd->format('d/m/Y'),
                    'total_days' => $totalDays,
                    'bars' => $bars,
                    'months' => $months,
                ];
            }
        }

        return view('schedule.index', [
            'projects' => $projects,
            'selectedProjectId' => $selectedProjectId,
            'timeline' => $timeline,
            'taskCount' => $tasks->count(),
        ]);
    }
}
