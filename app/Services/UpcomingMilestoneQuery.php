<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ProjectMilestone;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodayMilestoneItem;
use App\Support\Today\TodaySectionResult;
use Carbon\Carbon;

/**
 * Milestone overdue/sắp tới của các project actor có work hoặc là PM.
 * ProjectMilestone KHÔNG có tenant_id/TenantScope — tenant isolation bắt
 * buộc đi qua join Project.tenant_id. Không dùng scopeOverdue()/isOverdue()
 * của model (cả hai không nhất quán với nhau) — tính "overdue" trực tiếp
 * từ target_date/completed_date tại đây.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §6.4
 */
class UpcomingMilestoneQuery
{
    private const UPCOMING_WINDOW_DAYS = 30;
    private const LIMIT = 10;

    /**
     * @param string[] $relatedProjectIds
     */
    public function build(string $tenantId, string $actorId, array $relatedProjectIds): TodaySectionResult
    {
        if ($relatedProjectIds === []) {
            return new TodaySectionResult([], Availability::NO_DATA, Reliability::RELIABLE, null);
        }

        $today = Carbon::now()->startOfDay();
        $windowEnd = $today->copy()->addDays(self::UPCOMING_WINDOW_DAYS);

        $milestones = ProjectMilestone::query()
            ->whereIn('project_id', $relatedProjectIds)
            ->whereHas('project', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotIn('status', [ProjectMilestone::STATUS_CANCELLED, ProjectMilestone::STATUS_COMPLETED])
            ->whereNotNull('target_date')
            ->with('project:id,tenant_id,name')
            ->get();

        $items = $milestones
            ->filter(function (ProjectMilestone $milestone) use ($today, $windowEnd) {
                $isLiveOverdue = $milestone->target_date->lt($today) && $milestone->completed_date === null;
                $isUpcoming = $milestone->target_date->between($today, $windowEnd);

                return $isLiveOverdue || $isUpcoming;
            })
            ->map(function (ProjectMilestone $milestone) use ($today) {
                $isOverdue = $milestone->target_date->lt($today) && $milestone->completed_date === null;

                return new TodayMilestoneItem(
                    milestoneId: (string) $milestone->id,
                    name: $milestone->name,
                    projectId: (string) $milestone->project_id,
                    projectName: $milestone->project?->name ?? '—',
                    targetDate: $milestone->target_date,
                    isOverdue: $isOverdue,
                    status: $milestone->status,
                    url: route('app.projects.show', $milestone->project_id),
                );
            })
            ->sort(function (TodayMilestoneItem $a, TodayMilestoneItem $b) use ($today) {
                $overdueDiff = ($b->isOverdue ? 1 : 0) <=> ($a->isOverdue ? 1 : 0);
                if ($overdueDiff !== 0) {
                    return $overdueDiff;
                }

                // Sort by distance from today (both overdue and upcoming sorted by nearest first)
                $aDaysFromToday = abs($a->targetDate->diffInDays($today));
                $bDaysFromToday = abs($b->targetDate->diffInDays($today));
                $distanceDiff = $aDaysFromToday <=> $bDaysFromToday;
                if ($distanceDiff !== 0) {
                    return $distanceDiff;
                }

                return $a->milestoneId <=> $b->milestoneId;
            })
            ->take(self::LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult(
            $items,
            $items === [] ? Availability::NO_DATA : Availability::AVAILABLE,
            Reliability::RELIABLE,
            null,
        );
    }
}
