<?php

declare(strict_types=1);

namespace App\Services\TimeTracking;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GAP-112 (Gate 2 T-1, M-1, C-1, L-1, P-1) — a person's own time entries.
 *
 * T-1: one running timer per person (the user row is locked while it changes); starting another activity stops the
 * running one; a timer is capped at the earlier of start + 10 h and 23:59 of its start day and then flagged
 * auto_stopped. M-1: manual entries need a reason, may not overlap timed entries, and a person's day stays within 24 h;
 * own entries can be edited / deleted until approved. C-1: fixed categories for non-project time ("other" needs a
 * note). P-1: a task may be logged by its assignee, a task assignee, the project PM or a project member. L-1: dates in a
 * closed payroll month are locked. Every change is audited.
 */
class TimeEntryService
{
    public const MAX_TIMER_MINUTES = 600;

    public const MAX_DAY_MINUTES = 1440;

    public function start(User $user, ?Task $task, ?string $category = null, ?string $note = null): TimeEntry
    {
        [$projectId, $taskId, $category] = $this->target($user, $task, $category, $note);
        $now = Carbon::now()->startOfMinute();
        $this->requireOpenMonth($user, $now);

        return DB::transaction(function () use ($user, $projectId, $taskId, $category, $note, $now): TimeEntry {
            $this->lockUser($user);
            $this->closeRunning($user, $now);
            $entry = TimeEntry::query()->create([
                'tenant_id' => (string) $user->tenant_id,
                'user_id' => (string) $user->id,
                'project_id' => $projectId,
                'task_id' => $taskId,
                'category' => $category,
                'work_date' => $now->toDateString(),
                'started_at' => $now,
                'minutes' => 0,
                'source' => TimeEntry::SOURCE_TIMER,
                'note' => self::text($note, 500),
                'status' => TimeEntry::STATUS_RUNNING,
            ]);
            $this->audit($user, $entry, 'time.entry.started', null);

            return $entry;
        });
    }

    public function stop(User $user): ?TimeEntry
    {
        return DB::transaction(function () use ($user): ?TimeEntry {
            $this->lockUser($user);

            return $this->closeRunning($user, Carbon::now()->startOfMinute());
        });
    }

    /** The running timer after forgotten ones are closed (T-1). */
    public function running(User $user): ?TimeEntry
    {
        $this->closeStale($user);

        /** @var TimeEntry|null $entry */
        $entry = TimeEntry::query()->withoutGlobalScopes()->with('task:id,name,title')
            ->where('user_id', (string) $user->id)
            ->where('status', TimeEntry::STATUS_RUNNING)
            ->first();

        return $entry;
    }

    /** Close a running timer that is past its cap (another day, or more than 10 h). */
    public function closeStale(User $user): void
    {
        $now = Carbon::now()->startOfMinute();
        $stale = TimeEntry::query()->withoutGlobalScopes()
            ->where('user_id', (string) $user->id)
            ->where('status', TimeEntry::STATUS_RUNNING)
            ->get()
            ->filter(fn (TimeEntry $entry): bool => $now->greaterThan($this->cap($entry)));
        if ($stale->isEmpty()) {
            return;
        }
        DB::transaction(function () use ($user, $now): void {
            $this->lockUser($user);
            $this->closeRunning($user, $now);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addManual(User $user, array $data): TimeEntry
    {
        $values = $this->manualValues($user, $data, null);

        return DB::transaction(function () use ($user, $values): TimeEntry {
            $this->lockUser($user);
            $this->requireFits($user, $values, null);
            $entry = TimeEntry::query()->create($values + [
                'tenant_id' => (string) $user->tenant_id,
                'user_id' => (string) $user->id,
                'source' => TimeEntry::SOURCE_MANUAL,
                'status' => TimeEntry::STATUS_SUBMITTED,
            ]);
            $this->audit($user, $entry, 'time.entry.added', null);

            return $entry;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(User $user, TimeEntry $entry, array $data): TimeEntry
    {
        $this->requireEditable($user, $entry);
        $values = $this->manualValues($user, $data, $entry);

        return DB::transaction(function () use ($user, $entry, $values): TimeEntry {
            $this->lockUser($user);
            /** @var TimeEntry $locked */
            $locked = TimeEntry::query()->withoutGlobalScopes()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $this->requireEditable($user, $locked);
            $this->requireFits($user, $values, $locked);
            $before = $this->snapshot($locked);
            // A corrected entry (rejected or not) goes back to review; the review fields are cleared.
            $locked->fill($values + ['status' => TimeEntry::STATUS_SUBMITTED, 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null]);
            $locked->save();
            $this->audit($user, $locked, 'time.entry.updated', $before);

            return $locked;
        });
    }

    public function delete(User $user, TimeEntry $entry): void
    {
        $this->requireEditable($user, $entry);
        DB::transaction(function () use ($user, $entry): void {
            /** @var TimeEntry $locked */
            $locked = TimeEntry::query()->withoutGlobalScopes()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $this->requireEditable($user, $locked);
            $before = $this->snapshot($locked);
            $locked->delete();
            AuditLog::query()->create([
                'user_id' => (string) $user->id,
                'tenant_id' => (string) $user->tenant_id,
                'project_id' => $before['project_id'],
                'action' => 'time.entry.deleted',
                'entity_type' => 'time_entry',
                'entity_id' => (string) $entry->id,
                'old_data' => $before,
                'new_data' => null,
            ]);
        });
    }

    /**
     * @return Collection<int, TimeEntry>
     */
    public function week(User $user, Carbon $weekStart): Collection
    {
        /** @var Collection<int, TimeEntry> $rows */
        $rows = TimeEntry::query()->withoutGlobalScopes()->with(['task:id,name,title', 'project:id,name'])
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('user_id', (string) $user->id)
            ->whereDate('work_date', '>=', $weekStart->toDateString())
            ->whereDate('work_date', '<=', $weekStart->copy()->addDays(6)->toDateString())
            ->orderBy('work_date')
            ->orderBy('started_at')
            ->get();

        return $rows;
    }

    /** P-1: tasks a person may log time on. */
    public function canLogOn(User $user, Task $task): bool
    {
        if ((string) $task->getAttribute('tenant_id') !== (string) $user->tenant_id) {
            return false;
        }
        $userId = (string) $user->id;
        if ((string) $task->getAttribute('assigned_to') === $userId) {
            return true;
        }
        $projectId = (string) $task->getAttribute('project_id');
        if (DB::table('task_assignments')->where('task_id', (string) $task->id)->where('user_id', $userId)->exists()) {
            return true;
        }
        if (Project::query()->withoutGlobalScopes()->whereKey($projectId)->where('tenant_id', (string) $user->tenant_id)->where('pm_id', $userId)->exists()) {
            return true;
        }

        return DB::table('project_user_roles')->where('project_id', $projectId)->where('user_id', $userId)->exists();
    }

    /**
     * P-1 scope as a query, for the task pickers: assigned, task-assigned, PM project or member project; cancelled
     * tasks excluded, completed ones kept so past time can still be entered.
     *
     * @return \Illuminate\Support\Collection<int, Task>
     */
    public function loggableTasks(User $user, int $limit = 300): Collection
    {
        $userId = (string) $user->id;
        /** @var Collection<int, Task> $tasks */
        $tasks = Task::query()->withoutGlobalScopes()->with('project:id,name')
            ->where('tenant_id', (string) $user->tenant_id)
            ->whereNull('deleted_at')
            ->where('status', '!=', Task::STATUS_CANCELLED)
            ->where(static function ($q) use ($userId): void {
                $q->where('assigned_to', $userId)
                    ->orWhereIn('id', static fn ($sub) => $sub->select('task_id')->from('task_assignments')->where('user_id', $userId))
                    ->orWhereIn('project_id', static fn ($sub) => $sub->select('id')->from('projects')->where('pm_id', $userId))
                    ->orWhereIn('project_id', static fn ($sub) => $sub->select('project_id')->from('project_user_roles')->where('user_id', $userId));
            })
            ->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'title', 'project_id', 'status']);

        return $tasks;
    }

    public function isLocked(string $tenantId, Carbon|string $date): bool
    {
        $period = substr($date instanceof Carbon ? $date->toDateString() : $date, 0, 7);

        return DB::table('payroll_periods')->where('tenant_id', $tenantId)->where('period', $period)->where('status', 'closed')->exists();
    }

    public function requireOpenMonth(User $user, Carbon|string $date): void
    {
        if ($this->isLocked((string) $user->tenant_id, $date)) {
            throw new TimeRuleViolation('Tháng này đã chốt lương; không thêm / sửa / duyệt giờ được.', 'work_date');
        }
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function target(User $user, ?Task $task, ?string $category, ?string $note): array
    {
        if ($task !== null) {
            if (!$this->canLogOn($user, $task)) {
                throw new TimeRuleViolation('Bạn không được ghi giờ cho công việc này.', 'task_id');
            }

            return [(string) $task->getAttribute('project_id'), (string) $task->id, null];
        }
        $category = trim((string) $category);
        if ($category === '') {
            throw new TimeRuleViolation('Chọn công việc hoặc loại việc không thuộc công trình.', 'task_id');
        }
        if (!array_key_exists($category, TimeEntry::CATEGORIES)) {
            throw new TimeRuleViolation('Loại việc không hợp lệ.', 'category');
        }
        if ($category === TimeEntry::CATEGORY_OTHER && trim((string) $note) === '') {
            throw new TimeRuleViolation('Loại "Khác" phải có ghi chú (lý do).', 'note');
        }

        return [null, null, $category];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function manualValues(User $user, array $data, ?TimeEntry $current): array
    {
        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '') {
            throw new TimeRuleViolation('Nhập / sửa giờ tay phải ghi lý do.', 'note');
        }
        $taskId = trim((string) ($data['task_id'] ?? ''));
        $task = null;
        if ($taskId !== '') {
            /** @var Task|null $task */
            $task = Task::query()->withoutGlobalScopes()->whereKey($taskId)->where('tenant_id', (string) $user->tenant_id)->first();
            if ($task === null) {
                throw new TimeRuleViolation('Bạn không được ghi giờ cho công việc này.', 'task_id');
            }
        }
        [$projectId, $taskId, $category] = $this->target($user, $task, (string) ($data['category'] ?? ''), $note);

        $date = trim((string) ($data['work_date'] ?? ''));
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new TimeRuleViolation('Ngày làm việc không hợp lệ.', 'work_date');
        }
        if ($date > Carbon::now()->toDateString()) {
            throw new TimeRuleViolation('Không ghi giờ cho ngày trong tương lai.', 'work_date');
        }
        $this->requireOpenMonth($user, $date);
        if ($current !== null && $current->work_date->toDateString() !== $date) {
            $this->requireOpenMonth($user, $current->work_date);
        }

        $start = trim((string) ($data['start_time'] ?? ''));
        $end = trim((string) ($data['end_time'] ?? ''));
        if ($start !== '' || $end !== '') {
            $from = self::clock($date, $start, 'start_time');
            $to = self::clock($date, $end, 'end_time');
            if ($to->lessThanOrEqualTo($from)) {
                throw new TimeRuleViolation('Giờ kết thúc phải sau giờ bắt đầu.', 'end_time');
            }
            $minutes = (int) $from->diffInMinutes($to);
        } else {
            $raw = trim((string) ($data['minutes'] ?? ''));
            if (filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw <= 0) {
                throw new TimeRuleViolation('Nhập giờ bắt đầu / kết thúc hoặc số phút lớn hơn 0.', 'minutes');
            }
            $minutes = (int) $raw;
            $from = null;
            $to = null;
        }
        if ($minutes > self::MAX_DAY_MINUTES) {
            throw new TimeRuleViolation('Một ngày không quá 24 giờ.', 'minutes');
        }

        return [
            'project_id' => $projectId,
            'task_id' => $taskId,
            'category' => $category,
            'work_date' => $date,
            'started_at' => $from,
            'ended_at' => $to,
            'minutes' => $minutes,
            'note' => mb_substr($note, 0, 500),
        ];
    }

    /**
     * M-1: no overlap with this person's timed entries of the day, and the day stays within 24 h.
     *
     * @param array<string, mixed> $values
     */
    private function requireFits(User $user, array $values, ?TimeEntry $ignore): void
    {
        $sameDay = TimeEntry::query()->withoutGlobalScopes()
            ->where('user_id', (string) $user->id)
            ->whereDate('work_date', (string) $values['work_date'])
            ->where('id', '!=', $ignore === null ? '' : (string) $ignore->id)
            ->get(['id', 'started_at', 'ended_at', 'minutes', 'status']);
        if ((int) $sameDay->sum('minutes') + (int) $values['minutes'] > self::MAX_DAY_MINUTES) {
            throw new TimeRuleViolation('Tổng giờ trong một ngày không quá 24 giờ.', 'minutes');
        }
        if ($values['started_at'] === null) {
            return;
        }
        foreach ($sameDay as $other) {
            if ($other->started_at === null) {
                continue;
            }
            $otherEnd = $other->ended_at ?? Carbon::now();
            if ($values['started_at']->lessThan($otherEnd) && $other->started_at->lessThan($values['ended_at'])) {
                throw new TimeRuleViolation('Khoảng giờ bị chồng lên một dòng giờ khác cùng ngày.', 'start_time');
            }
        }
    }

    private function requireEditable(User $user, TimeEntry $entry): void
    {
        if ((string) $entry->user_id !== (string) $user->id || (string) $entry->tenant_id !== (string) $user->tenant_id) {
            throw new TimeRuleViolation('Chỉ sửa / xoá được giờ của mình.', 'entry');
        }
        if (in_array($entry->status, [TimeEntry::STATUS_APPROVED, TimeEntry::STATUS_RUNNING], true)) {
            throw new TimeRuleViolation($entry->status === TimeEntry::STATUS_APPROVED ? 'Giờ đã duyệt không sửa / xoá được.' : 'Dừng đồng hồ trước khi sửa.', 'entry');
        }
        $this->requireOpenMonth($user, $entry->work_date);
    }

    private function closeRunning(User $user, Carbon $now): ?TimeEntry
    {
        /** @var TimeEntry|null $entry */
        $entry = TimeEntry::query()->withoutGlobalScopes()
            ->where('user_id', (string) $user->id)
            ->where('status', TimeEntry::STATUS_RUNNING)
            ->lockForUpdate()
            ->first();
        if ($entry === null || $entry->started_at === null) {
            return null;
        }
        $start = $entry->started_at->copy();
        // L-1: a timer left running into a payroll month that was closed meanwhile never counts. It is closed as
        // rejected with no minutes, so the closed month's counted time does not change and the person can carry on.
        if ($this->isLocked((string) $user->tenant_id, $entry->work_date)) {
            $entry->ended_at = $start;
            $entry->minutes = 0;
            $entry->auto_stopped = true;
            $entry->status = TimeEntry::STATUS_REJECTED;
            $entry->review_note = 'Tháng đã chốt lương trước khi dừng đồng hồ; dòng này không được tính.';
            $entry->save();
            $this->audit($user, $entry, 'time.entry.voided_closed_month', null);

            return $entry;
        }

        $cap = $this->cap($entry);
        $end = $now->greaterThan($cap) ? $cap->copy() : $now->copy();
        $clipped = $now->greaterThan($cap);
        // M-1 also for timers: no overlap with the day's other timed entries, and the day stays within 24 h.
        $others = TimeEntry::query()->withoutGlobalScopes()
            ->where('user_id', (string) $user->id)
            ->whereDate('work_date', $entry->work_date->toDateString())
            ->where('id', '!=', (string) $entry->id)
            ->get(['id', 'started_at', 'ended_at', 'minutes']);
        foreach ($others as $other) {
            if ($other->started_at === null || $other->ended_at === null) {
                continue;
            }
            if ($other->started_at->lessThanOrEqualTo($start) && $other->ended_at->greaterThan($start)) {
                $end = $start->copy();
                $clipped = true;
            } elseif ($other->started_at->greaterThan($start) && $other->started_at->lessThan($end)) {
                $end = $other->started_at->copy();
                $clipped = true;
            }
        }
        $minutes = max(0, (int) $start->diffInMinutes($end));
        $room = max(0, self::MAX_DAY_MINUTES - (int) $others->sum('minutes'));
        if ($minutes > $room) {
            $minutes = $room;
            $end = $start->copy()->addMinutes($room);
            $clipped = true;
        }
        $entry->ended_at = $end;
        $entry->minutes = $minutes;
        $entry->auto_stopped = $clipped;
        $entry->status = TimeEntry::STATUS_SUBMITTED;
        $entry->save();
        $this->audit($user, $entry, $clipped ? 'time.entry.auto_stopped' : 'time.entry.stopped', null);

        return $entry;
    }

    /** T-1 cap: the earlier of start + 10 h and 23:59 of the start day. */
    private function cap(TimeEntry $entry): Carbon
    {
        $start = ($entry->started_at ?? Carbon::now())->copy();
        $tenHours = $start->copy()->addMinutes(self::MAX_TIMER_MINUTES);
        $endOfDay = $start->copy()->setTime(23, 59, 0);

        return $tenHours->lessThan($endOfDay) ? $tenHours : $endOfDay;
    }

    private function lockUser(User $user): void
    {
        DB::table('users')->where('id', (string) $user->id)->lockForUpdate()->first();
    }

    private static function clock(string $date, string $time, string $field): Carbon
    {
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw new TimeRuleViolation('Giờ không hợp lệ (HH:MM).', $field);
        }

        return Carbon::parse($date . ' ' . $time . ':00');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TimeEntry $entry): array
    {
        return [
            'project_id' => $entry->project_id,
            'task_id' => $entry->task_id,
            'category' => $entry->category,
            'work_date' => $entry->work_date->toDateString(),
            'started_at' => $entry->started_at?->format('Y-m-d H:i'),
            'ended_at' => $entry->ended_at?->format('Y-m-d H:i'),
            'minutes' => $entry->minutes,
            'status' => $entry->status,
            'note' => $entry->note,
        ];
    }

    /**
     * @param array<string, mixed>|null $before
     */
    private function audit(User $user, TimeEntry $entry, string $action, ?array $before): void
    {
        AuditLog::query()->create([
            'user_id' => (string) $user->id,
            'tenant_id' => (string) $user->tenant_id,
            'project_id' => $entry->project_id,
            'action' => $action,
            'entity_type' => 'time_entry',
            'entity_id' => (string) $entry->id,
            'old_data' => $before,
            'new_data' => $this->snapshot($entry),
        ]);
    }

    private static function text(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
