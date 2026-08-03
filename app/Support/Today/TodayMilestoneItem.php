<?php declare(strict_types=1);

namespace App\Support\Today;

use Illuminate\Support\Carbon;

final class TodayMilestoneItem
{
    public function __construct(
        public readonly string $milestoneId,
        public readonly string $name,
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly ?Carbon $targetDate,
        public readonly bool $isOverdue,
        public readonly string $status,
        public readonly string $url,
    ) {
    }
}
