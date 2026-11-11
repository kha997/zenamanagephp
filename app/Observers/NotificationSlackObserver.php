<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SendSlackMessage;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GAP-124 (Gate 2 E-1) — selects the business notifications that also go to the company's Slack channel and hands them
 * to SendSlackMessage (which checks the company's channel, de-duplicates per event and posts). CRM, design-item and other
 * events stay in-app only. Nothing here may break the action that created the notification.
 */
class NotificationSlackObserver
{
    /** Exact event keys sent to Slack. */
    public const EVENT_KEYS = [
        'change_request.implemented',
        'submittal.submitted',
        'submittal.resubmitted',
        'submittal.approved',
        'submittal.rejected',
        'ncr.assigned',
        'payment_certificate.approved',
    ];

    public function created(Notification $notification): void
    {
        try {
            $eventKey = (string) $notification->getAttribute('event_key');
            if (!in_array($eventKey, self::EVENT_KEYS, true) && !Str::startsWith($eventKey, SendSlackMessage::ONCE_PER_KEY_PREFIXES)) {
                return;
            }
            SendSlackMessage::dispatch((string) $notification->getKey())->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('slack.notify_failed', ['notification_id' => (string) $notification->getKey(), 'error' => $e->getMessage()]);
        }
    }
}
