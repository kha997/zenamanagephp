<?php declare(strict_types=1);

namespace App\Support\Today;

final class TodayTeamMemberSummary
{
    public function __construct(
        public readonly string $userId,
        public readonly string $userName,
        public readonly int $openCount,
        public readonly int $overdueCount,
        public readonly int $blockedCount,
    ) {
    }
}
