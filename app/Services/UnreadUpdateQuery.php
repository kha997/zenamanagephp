<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodayNotificationItem;
use App\Support\Today\TodaySectionResult;

/**
 * Notification chưa đọc của actor — không tự động là Action Required
 * (không có Action Required nào ở MVP).
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §6.5
 */
class UnreadUpdateQuery
{
    private const LIMIT = 10;

    public function build(string $tenantId, string $actorId): TodaySectionResult
    {
        $notifications = Notification::query()
            ->where('tenant_id', $tenantId)
            ->forUser($actorId)
            ->unread()
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        $items = $notifications
            ->map(fn (Notification $n) => new TodayNotificationItem(
                notificationId: (string) $n->id,
                title: $n->title,
                body: $n->body,
                url: $n->link_url,
                createdAt: $n->created_at,
            ))
            ->all();

        return new TodaySectionResult(
            $items,
            $items === [] ? Availability::NO_DATA : Availability::AVAILABLE,
            Reliability::RELIABLE,
            null,
        );
    }
}
