<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\TimeTracking;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\TimeTracking\TimeEntryService;
use App\Services\TimeTracking\TimeReportService;
use App\Services\TimeTracking\TimeReviewService;
use App\Services\TimeTracking\TimeRuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-112 (Gate 2 T-1, M-1, C-1, P-1) — "Bảng giờ của tôi": the week's entries, timer start / stop, manual entries,
 * edit / delete before approval. Rules live in TimeEntryService.
 */
class TimesheetPageController extends Controller
{
    public function __construct(private readonly TimeEntryService $entries)
    {
    }

    public function index(Request $request): View
    {
        $user = $this->user();
        $week = self::weekStart((string) $request->query('week', ''));
        $rows = $this->entries->week($user, $week);
        $days = [];
        foreach (range(0, 6) as $offset) {
            $date = $week->copy()->addDays($offset)->toDateString();
            $days[$date] = (int) $rows->filter(static fn (TimeEntry $e): bool => $e->work_date->toDateString() === $date)->sum('minutes');
        }
        $tasks = Task::query()->withoutGlobalScopes()->with('project:id,name')
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('assigned_to', (string) $user->id)
            ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'title', 'project_id']);

        return view('time.timesheet', [
            'week' => $week,
            'entries' => $rows,
            'days' => $days,
            'total' => array_sum($days),
            'running' => $this->entries->running($user),
            'tasks' => $tasks,
            'categories' => TimeEntry::CATEGORIES,
            'statuses' => TimeEntry::STATUSES,
            'canReview' => app(TimeReviewService::class)->isReviewer($user),
            'canReport' => app(TimeReportService::class)->canView($user),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate(['task_id' => ['nullable', 'string'], 'category' => ['nullable', 'string'], 'note' => ['nullable', 'string', 'max:500']]);
        $user = $this->user();
        $task = null;
        if (($data['task_id'] ?? '') !== '') {
            /** @var Task|null $task */
            $task = Task::query()->withoutGlobalScopes()->where('tenant_id', (string) $user->tenant_id)->whereKey((string) $data['task_id'])->first();
            if ($task === null) {
                return back()->withErrors(['task_id' => 'Bạn không được ghi giờ cho công việc này.']);
            }
        }

        try {
            $this->entries->start($user, $task, $data['category'] ?? null, $data['note'] ?? null);
        } catch (TimeRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'task_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã bắt đầu ghi giờ.');
    }

    public function stop(): RedirectResponse
    {
        $entry = $this->entries->stop($this->user());

        return back()->with('success', $entry === null ? 'Không có đồng hồ đang chạy.' : 'Đã dừng ghi giờ (' . intdiv($entry->minutes, 60) . ':' . str_pad((string) ($entry->minutes % 60), 2, '0', STR_PAD_LEFT) . ').');
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->attempt(fn () => $this->entries->addManual($this->user(), $this->manual($request)), 'Đã thêm giờ.');
    }

    public function update(Request $request, string $entry): RedirectResponse
    {
        $row = $this->find($entry);

        return $this->attempt(fn () => $this->entries->update($this->user(), $row, $this->manual($request)), 'Đã sửa giờ.');
    }

    public function destroy(string $entry): RedirectResponse
    {
        $row = $this->find($entry);

        return $this->attempt(fn () => $this->entries->delete($this->user(), $row), 'Đã xoá dòng giờ.');
    }

    /**
     * @return array<string, mixed>
     */
    private function manual(Request $request): array
    {
        return $request->validate([
            'task_id' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'note' => ['required', 'string', 'max:500'],
        ]);
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (TimeRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'entry' => $e->getMessage()]);
        }

        return back()->with('success', $message);
    }

    private function find(string $id): TimeEntry
    {
        /** @var TimeEntry $entry */
        $entry = TimeEntry::query()->withoutGlobalScopes()->where('tenant_id', (string) $this->user()->tenant_id)->whereKey($id)->firstOrFail();

        return $entry;
    }

    public static function weekStart(string $value): Carbon
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return ($date === false ? Carbon::now() : Carbon::instance($date))->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
