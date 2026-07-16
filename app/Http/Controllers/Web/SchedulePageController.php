<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\TaskController as ApiTaskController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class SchedulePageController extends Controller
{
    use DelegatesToApiControllers;
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
                // Compare date-only values: mixed datetime strings across
                // timezones would otherwise shift bars by a day
                $dateOnly = static fn (mixed $value): CarbonImmutable => CarbonImmutable::parse(substr((string) $value, 0, 10));

                $rangeStart = $dateOnly($tasks->min('start_date'))->startOfDay();
                $rangeEnd = $dateOnly($tasks->max('end_date'))->endOfDay();
                $totalDays = max(1, (int) ceil($rangeStart->diffInDays($rangeEnd)));

                $bars = $tasks->map(function (Task $task) use ($rangeStart, $totalDays, $dateOnly): array {
                    $start = $dateOnly($task->start_date)->startOfDay();
                    $end = $dateOnly($task->end_date)->endOfDay();
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
            'tasks' => $tasks,
        ]);
    }

    public function storeTask(Request $request, ApiTaskController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string'],
        ]);

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse(
            $response,
            route('operator.schedule.index', ['project_id' => $validated['project_id']]),
            'Đã thêm công việc'
        );
    }

    public function updateTask(Request $request, string $id, ApiTaskController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $payload = collect($validated)->except('project_id')->filter(fn ($value) => $value !== null)->all();

        try {
            $response = $apiController->update($this->buildApiRequest($request, $payload), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse(
            $response,
            route('operator.schedule.index', ['project_id' => $validated['project_id']]),
            'Đã cập nhật công việc'
        );
    }

    public function destroyTask(Request $request, string $id, ApiTaskController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->destroy($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse(
            $response,
            route('operator.schedule.index', ['project_id' => (string) $request->input('project_id')]),
            'Đã xóa công việc'
        );
    }
}
