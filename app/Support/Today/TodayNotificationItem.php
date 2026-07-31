<?php declare(strict_types=1);

namespace App\Support\Today;

use Illuminate\Support\Carbon;

final class TodayNotificationItem
{
    public function __construct(
        public readonly string $notificationId,
        public readonly string $title,
        public readonly ?string $body,
        public readonly ?string $url,
        public readonly Carbon $createdAt,
    ) {
    }
}
