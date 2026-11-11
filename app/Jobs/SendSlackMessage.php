<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Notifications\SlackChannelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * GAP-124 (Gate 2 E-1) — mirrors one business notification to the company's Slack channel, on the queue so the user's
 * action never waits for Slack. In-app notifications are one row per recipient; Slack gets one message per event (same
 * company, event, link and title within 10 minutes; month-end reminders once per company and key — the key carries the
 * day). Nothing is sent when the company has no Slack channel switched on. A failed post is retried twice, one minute
 * apart, and never throws.
 */
class SendSlackMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Event key prefixes sent once per company and key. */
    public const ONCE_PER_KEY_PREFIXES = ['month_end.'];

    private const DEDUPE_SECONDS = 600;

    public int $tries = 3;

    public function __construct(public readonly string $notificationId)
    {
    }

    public function handle(SlackChannelService $slack): void
    {
        $notification = Notification::query()->withoutGlobalScopes()->whereKey($this->notificationId)->first();
        if (!$notification instanceof Notification) {
            return;
        }
        $tenantId = (string) $notification->getAttribute('tenant_id');
        if ($tenantId === '' || $slack->webhookUrl($tenantId) === null) {
            return;
        }

        $eventKey = (string) $notification->getAttribute('event_key');
        $oncePerKey = Str::startsWith($eventKey, self::ONCE_PER_KEY_PREFIXES);
        $title = (string) $notification->getAttribute('title');
        $link = (string) $notification->getAttribute('link_url');
        $dedupe = 'slack-notify:' . sha1($oncePerKey ? "{$tenantId}|{$eventKey}" : "{$tenantId}|{$eventKey}|{$link}|{$title}");
        if ($this->attempts() <= 1 && !Cache::add($dedupe, 1, $oncePerKey ? 36 * 3600 : self::DEDUPE_SECONDS)) {
            return;
        }

        if ($slack->post($tenantId, $this->text($notification, $oncePerKey))) {
            return;
        }
        if ($this->attempts() < $this->tries && $this->job !== null) {
            $this->release(60);
        }
    }

    private function text(Notification $notification, bool $oncePerKey): string
    {
        $lines = ['*' . trim((string) $notification->getAttribute('title')) . '*'];
        $body = trim((string) $notification->getAttribute('body'));
        if ($body !== '' && !$oncePerKey) {
            $lines[] = Str::limit($body, 300);
        }
        $link = (string) $notification->getAttribute('link_url');
        // Month-end links are per person; the channel gets the reminder and the page that lists everything.
        $target = $oncePerKey || !str_starts_with($link, '/') || str_starts_with($link, '//')
            ? route('app.notifications.index')
            : url($link);
        $lines[] = '<' . $target . '|Mở trong Zena>';

        return implode("\n", $lines);
    }
}
