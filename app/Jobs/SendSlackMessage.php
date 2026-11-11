<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Notifications\SlackChannelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * GAP-124 (Gate 2 E-1, Owner amendment 3) — posts one business event to the company's Slack channel, on the queue so the
 * user's action never waits for Slack. $eventIdentity names the business event, not the recipient (for example
 * "change_request.implemented:<change request id>" or "event_record:<id>"), so an event that notifies several people in
 * the app is posted once, and two different events never suppress each other. Nothing is sent when the company has no
 * Slack channel switched on. A failed post is retried twice, one minute apart, and never throws.
 */
class SendSlackMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEDUPE_SECONDS = 36 * 3600;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $eventIdentity,
        public readonly string $text,
    ) {
    }

    public function handle(SlackChannelService $slack): void
    {
        if ($this->tenantId === '' || $slack->webhookUrl($this->tenantId) === null) {
            return;
        }
        $dedupe = 'slack-event:' . sha1($this->tenantId . '|' . $this->eventIdentity);
        if ($this->attempts() <= 1 && !Cache::add($dedupe, 1, self::DEDUPE_SECONDS)) {
            return;
        }
        if ($slack->post($this->tenantId, $this->text)) {
            return;
        }
        if ($this->attempts() < $this->tries && $this->job !== null) {
            $this->release(60);
        }
    }
}
