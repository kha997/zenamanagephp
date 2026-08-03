<?php declare(strict_types=1);

namespace App\Support\Today;

final class TodayWorkspaceViewModel
{
    public function __construct(
        public readonly TodaySectionResult $personalOpenWork,
        public readonly TodaySectionResult $inProgress,
        public readonly TodaySectionResult $overdueAndBlocked,
        public readonly TodaySectionResult $upcomingMilestones,
        public readonly TodaySectionResult $unreadUpdates,
        public readonly ?TodaySectionResult $teamException,
    ) {
    }
}
