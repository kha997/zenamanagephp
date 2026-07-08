<?php declare(strict_types=1);

namespace App\Observers;

use App\Jobs\DeliverWebhook;
use App\Models\EventRecord;
use App\Models\WebhookEndpoint;

class EventRecordObserver
{
    public function created(EventRecord $eventRecord): void
    {
        $endpoints = WebhookEndpoint::query()
            ->forTenant((string) $eventRecord->tenant_id)
            ->where('is_active', true)
            ->get();

        if ($endpoints->isEmpty()) {
            return;
        }

        $payload = [
            'event_id' => (string) $eventRecord->id,
            'event_key' => (string) $eventRecord->event_key,
            'aggregate_type' => (string) $eventRecord->aggregate_type,
            'aggregate_id' => (string) $eventRecord->aggregate_id,
            'project_id' => $eventRecord->project_id ? (string) $eventRecord->project_id : null,
            'payload' => $eventRecord->payload,
            'occurred_at' => $eventRecord->occurred_at?->toISOString(),
        ];

        foreach ($endpoints as $endpoint) {
            if ($endpoint->matchesEventKey((string) $eventRecord->event_key)) {
                DeliverWebhook::dispatch((string) $endpoint->id, $payload);
            }
        }
    }
}
