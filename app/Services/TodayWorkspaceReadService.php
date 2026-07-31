<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodaySectionResult;
use App\Support\Work\OpenWorkItem;
use Illuminate\Support\Collection;

/**
 * Orchestration boundary cho trang /app/today.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §3.2
 */
class TodayWorkspaceReadService
{
    private const PERSONAL_OPEN_WORK_LIMIT = 20;
    private const IN_PROGRESS_LIMIT = 10;
    private const OVERDUE_AND_BLOCKED_LIMIT = 20;

    public function __construct(private readonly OpenWorkReadQuery $openWorkReadQuery)
    {
    }

    /**
     * @param Collection<int, OpenWorkItem> $openWork
     */
    public function personalOpenWork(Collection $openWork, string $actorId): TodaySectionResult
    {
        $items = $openWork
            ->filter(fn (OpenWorkItem $i) => $i->assignedTo === $actorId)
            ->sort(fn (OpenWorkItem $a, OpenWorkItem $b) => $this->sortRank($a, $b))
            ->take(self::PERSONAL_OPEN_WORK_LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult($items, Availability::AVAILABLE, Reliability::RELIABLE, null);
    }

    /**
     * @param Collection<int, OpenWorkItem> $openWork
     */
    public function inProgress(Collection $openWork, string $actorId): TodaySectionResult
    {
        $items = $openWork
            ->filter(fn (OpenWorkItem $i) => $i->assignedTo === $actorId
                && $i->sourceType === 'task'
                && $i->status === Task::STATUS_IN_PROGRESS)
            ->sort(fn (OpenWorkItem $a, OpenWorkItem $b) => $this->sortRank($a, $b))
            ->take(self::IN_PROGRESS_LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult($items, Availability::AVAILABLE, Reliability::RELIABLE, null);
    }

    /**
     * @param Collection<int, OpenWorkItem> $openWork
     */
    public function overdueAndBlocked(Collection $openWork, string $actorId): TodaySectionResult
    {
        $items = $openWork
            ->filter(fn (OpenWorkItem $i) => $i->assignedTo === $actorId && ($i->isOverdue || $i->isBlocked))
            ->sort(function (OpenWorkItem $a, OpenWorkItem $b) {
                $rankDiff = $this->overdueBlockedRank($b) <=> $this->overdueBlockedRank($a);

                return $rankDiff !== 0 ? $rankDiff : $this->compareEndDate($a, $b);
            })
            ->take(self::OVERDUE_AND_BLOCKED_LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult($items, Availability::AVAILABLE, Reliability::RELIABLE, null);
    }

    private function sortRank(OpenWorkItem $a, OpenWorkItem $b): int
    {
        $overdueDiff = ($b->isOverdue ? 1 : 0) <=> ($a->isOverdue ? 1 : 0);
        if ($overdueDiff !== 0) {
            return $overdueDiff;
        }

        $dateDiff = $this->compareEndDate($a, $b);
        if ($dateDiff !== 0) {
            return $dateDiff;
        }

        $priorityDiff = $this->comparePriority($a, $b);
        if ($priorityDiff !== 0) {
            return $priorityDiff;
        }

        return $a->sourceId <=> $b->sourceId;
    }

    /** overdue+blocked=2, overdue-only=1, blocked-only=0 */
    private function overdueBlockedRank(OpenWorkItem $item): int
    {
        return (int) $item->isOverdue + (int) $item->isBlocked;
    }

    private function compareEndDate(OpenWorkItem $a, OpenWorkItem $b): int
    {
        if ($a->endDate === null && $b->endDate === null) {
            return 0;
        }
        if ($a->endDate === null) {
            return 1;
        }
        if ($b->endDate === null) {
            return -1;
        }

        return $a->endDate <=> $b->endDate;
    }

    private function comparePriority(OpenWorkItem $a, OpenWorkItem $b): int
    {
        $rank = [
            Task::PRIORITY_CRITICAL => 0,
            Task::PRIORITY_HIGH => 1,
            Task::PRIORITY_MEDIUM => 2,
            Task::PRIORITY_LOW => 3,
        ];

        $aRank = $a->priority !== null ? ($rank[$a->priority] ?? 4) : 4;
        $bRank = $b->priority !== null ? ($rank[$b->priority] ?? 4) : 4;

        return $aRank <=> $bRank;
    }
}
