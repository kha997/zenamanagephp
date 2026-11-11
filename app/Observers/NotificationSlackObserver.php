<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SendSlackMessage;
use App\Models\ChangeRequest;
use App\Models\Ncr;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GAP-124 (Gate 2 E-1, Owner amendment 3) — business events whose source is an in-app notification: change request
 * implemented, NCR assigned, month-end reminders. One Slack message per business event (the change request, the NCR, the
 * month-end key), however many people the event notifies in the app. Submittal and payment-certificate events come from
 * EventRecord (EventRecordSlackObserver). Nothing here may break the action that created the notification.
 */
class NotificationSlackObserver
{
    public const EVENT_KEYS = ['change_request.implemented', 'ncr.assigned'];

    public const ONCE_PER_KEY_PREFIXES = ['month_end.'];

    public function created(Notification $notification): void
    {
        try {
            $eventKey = (string) $notification->getAttribute('event_key');
            $tenantId = (string) $notification->getAttribute('tenant_id');
            $message = match (true) {
                $eventKey === 'change_request.implemented' => $this->changeRequest($notification),
                $eventKey === 'ncr.assigned' => $this->ncr($notification),
                Str::startsWith($eventKey, self::ONCE_PER_KEY_PREFIXES) => [
                    $eventKey,
                    '*' . trim((string) $notification->getAttribute('title')) . "*\n<" . route('app.notifications.index') . '|Mở trong Zena>',
                ],
                default => null,
            };
            if ($message !== null) {
                SendSlackMessage::dispatch($tenantId, $message[0], $message[1])->afterCommit();
            }
        } catch (\Throwable $e) {
            Log::warning('slack.notify_failed', ['notification_id' => (string) $notification->getKey(), 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function changeRequest(Notification $notification): ?array
    {
        $id = $this->dataId($notification, 'change_request_id');
        $cr = $id !== null ? ChangeRequest::query()->withoutGlobalScopes()->whereKey($id)->first() : null;
        if (!$cr instanceof ChangeRequest) {
            return null;
        }
        $label = trim($cr->getAttribute('change_number') . ' – ' . $cr->getAttribute('title'), ' –');

        return [
            "change_request.implemented:{$id}",
            "*Yêu cầu thay đổi đã áp dụng: {$label}*\n<" . route('operator.change-requests.show', $id) . '|Mở trong Zena>',
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function ncr(Notification $notification): ?array
    {
        $id = $this->dataId($notification, 'ncr_id');
        $ncr = $id !== null ? Ncr::query()->withoutGlobalScopes()->with('assignee:id,name')->whereKey($id)->first() : null;
        if (!$ncr instanceof Ncr) {
            return null;
        }
        $assignee = $ncr->getRelation('assignee');
        $lines = ['*NCR mới: ' . trim($ncr->getAttribute('ncr_number') . ' – ' . $ncr->getAttribute('title'), ' –') . '*'];
        if (is_object($assignee) && method_exists($assignee, 'getAttribute')) {
            $lines[] = 'Giao cho: ' . $assignee->getAttribute('name');
        }
        $lines[] = '<' . route('operator.inspections.ncrs.show', [(string) $ncr->getAttribute('inspection_id'), $id]) . '|Mở trong Zena>';

        return ["ncr.assigned:{$id}", implode("\n", $lines)];
    }

    private function dataId(Notification $notification, string $key): ?string
    {
        $data = $notification->getAttribute('data');
        $value = is_array($data) ? ($data[$key] ?? null) : null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
