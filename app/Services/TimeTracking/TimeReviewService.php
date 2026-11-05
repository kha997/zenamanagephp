<?php

declare(strict_types=1);

namespace App\Services\TimeTracking;

use App\Models\AuditLog;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GAP-112 (Gate 2 R-1, L-1) — review of submitted time. Project entries: the project PM or project.update holders;
 * non-project entries: payroll.manage holders. Nobody reviews their own entries except payroll.close holders.
 * Rejection needs a reason and sends the entry back to its author. Closed payroll months are locked. Audited.
 */
class TimeReviewService
{
    public function __construct(private readonly TimeEntryService $entries)
    {
    }

    /** Whether the person reviews anything at all (menu / page access). */
    public function isReviewer(User $user): bool
    {
        return $user->hasPermission('project.update')
            || $user->hasPermission('payroll.manage')
            || DB::table('projects')->where('tenant_id', (string) $user->tenant_id)->where('pm_id', (string) $user->id)->exists();
    }

    /**
     * @return Collection<int, TimeEntry>
     */
    public function queue(User $reviewer, Carbon $weekStart): Collection
    {
        /** @var Collection<int, TimeEntry> $rows */
        $rows = $this->scoped($reviewer, TimeEntry::query()
            ->with(['task:id,name,title', 'project:id,name', 'user:id,name'])
            ->where('time_entries.tenant_id', (string) $reviewer->tenant_id)
            ->where('time_entries.status', TimeEntry::STATUS_SUBMITTED)
            ->whereDate('time_entries.work_date', '>=', $weekStart->toDateString())
            ->whereDate('time_entries.work_date', '<=', $weekStart->copy()->addDays(6)->toDateString()))
            ->orderBy('time_entries.user_id')
            ->orderBy('time_entries.work_date')
            ->get();

        return $rows;
    }

    /**
     * @param list<string> $ids
     */
    public function approve(User $reviewer, array $ids): int
    {
        return $this->decide($reviewer, $ids, TimeEntry::STATUS_APPROVED, null);
    }

    /**
     * @param list<string> $ids
     */
    public function reject(User $reviewer, array $ids, string $reason): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new TimeRuleViolation('Từ chối phải ghi lý do.', 'reason');
        }

        return $this->decide($reviewer, $ids, TimeEntry::STATUS_REJECTED, mb_substr($reason, 0, 500));
    }

    public function canReview(User $reviewer, TimeEntry $entry): bool
    {
        if ((string) $entry->tenant_id !== (string) $reviewer->tenant_id) {
            return false;
        }
        if ((string) $entry->user_id === (string) $reviewer->id && !$reviewer->hasPermission('payroll.close')) {
            return false;
        }
        if ($entry->project_id === null) {
            return $reviewer->hasPermission('payroll.manage');
        }
        if ($reviewer->hasPermission('project.update')) {
            return true;
        }

        return DB::table('projects')->where('id', (string) $entry->project_id)->where('pm_id', (string) $reviewer->id)->exists();
    }

    /**
     * @param list<string> $ids
     */
    private function decide(User $reviewer, array $ids, string $status, ?string $note): int
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            throw new TimeRuleViolation('Chọn ít nhất một dòng giờ.', 'ids');
        }

        return DB::transaction(function () use ($reviewer, $ids, $status, $note): int {
            /** @var \Illuminate\Database\Eloquent\Collection<int, TimeEntry> $rows */
            $rows = TimeEntry::query()->whereIn('id', $ids)->lockForUpdate()->get();
            if ($rows->count() !== count($ids)) {
                throw new TimeRuleViolation('Có dòng giờ không tồn tại.', 'ids');
            }
            foreach ($rows as $entry) {
                if (!$this->canReview($reviewer, $entry)) {
                    throw new TimeRuleViolation('Bạn không có quyền duyệt dòng giờ này.', 'ids');
                }
                if ($entry->status !== TimeEntry::STATUS_SUBMITTED) {
                    throw new TimeRuleViolation('Chỉ duyệt / từ chối được dòng giờ đang chờ duyệt.', 'ids');
                }
                $this->entries->requireOpenMonth($reviewer, $entry->work_date);
            }
            foreach ($rows as $entry) {
                $entry->status = $status;
                $entry->reviewed_by = (string) $reviewer->id;
                $entry->reviewed_at = Carbon::now();
                $entry->review_note = $note;
                $entry->save();
                AuditLog::query()->create([
                    'user_id' => (string) $reviewer->id,
                    'tenant_id' => (string) $reviewer->tenant_id,
                    'project_id' => $entry->project_id,
                    'action' => $status === TimeEntry::STATUS_APPROVED ? 'time.entry.approved' : 'time.entry.rejected',
                    'entity_type' => 'time_entry',
                    'entity_id' => (string) $entry->id,
                    'old_data' => ['status' => TimeEntry::STATUS_SUBMITTED],
                    'new_data' => ['status' => $status, 'review_note' => $note, 'minutes' => $entry->minutes],
                ]);
            }

            return $rows->count();
        });
    }

    /**
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder<TimeEntry>|\Illuminate\Database\Query\Builder
     * @param TBuilder $query
     * @return TBuilder
     */
    private function scoped(User $reviewer, \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
    {
        $selfAllowed = $reviewer->hasPermission('payroll.close');
        $nonProject = $reviewer->hasPermission('payroll.manage');
        $allProjects = $reviewer->hasPermission('project.update');
        $userId = (string) $reviewer->id;

        return $query
            ->when(!$selfAllowed, static fn ($q) => $q->where('time_entries.user_id', '!=', $userId))
            ->where(static function ($q) use ($nonProject, $allProjects, $userId): void {
                $q->whereRaw('1 = 0');
                if ($nonProject) {
                    $q->orWhereNull('time_entries.project_id');
                }
                if ($allProjects) {
                    $q->orWhereNotNull('time_entries.project_id');
                } else {
                    $q->orWhereIn('time_entries.project_id', static fn ($sub) => $sub->select('id')->from('projects')->where('pm_id', $userId));
                }
            });
    }
}
