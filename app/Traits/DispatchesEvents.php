<?php

namespace App\Traits;

use App\Events\ChangeRequested;
use App\Events\ComponentCreated;
use App\Events\DocumentUploaded;
use App\Events\TaskCompleted;
use App\Events\TaskCreated;
use App\Events\TaskUpdated;
use App\Services\EventBusService;

trait DispatchesEvents
{
    /**
     * Dispatch task created event
     */
    protected function dispatchTaskCreated($task, $user, array $metadata = []): void
    {
        app(EventBusService::class)->dispatch(TaskCreated::class, $task, $user, $metadata);
    }

    /**
     * Dispatch task completed event
     */
    protected function dispatchTaskCompleted($task, $user, array $metadata = []): void
    {
        app(EventBusService::class)->dispatch(TaskCompleted::class, $task, $user, $metadata);
    }

    /**
     * Dispatch task updated event
     */
    protected function dispatchTaskUpdated($task, $user, array $oldData = [], array $changes = [], array $metadata = []): void
    {
        app(EventBusService::class)->dispatch(TaskUpdated::class, $task, $user, $oldData, $changes, $metadata);
    }

    /**
     * Dispatch component created event
     */
    protected function dispatchComponentCreated($component, $user, array $metadata = []): void
    {
        app(EventBusService::class)->dispatch(ComponentCreated::class, $component, $user, $metadata);
    }

    /**
     * Dispatch change requested event
     */
    protected function dispatchChangeRequested($changeRequest, $user, array $metadata = []): void
    {
        app(EventBusService::class)->dispatch(ChangeRequested::class, $changeRequest, $user, $metadata);
    }

    /**
     * Dispatch document uploaded event
     */
    protected function dispatchDocumentUploaded($document, $user, array $metadata = []): void
    {
        app(EventBusService::class)->dispatch(DocumentUploaded::class, $document, $user, $metadata);
    }

    /**
     * Dispatch event asynchronously
     */
    protected function dispatchAsync(string $eventClass, ...$args): void
    {
        app(EventBusService::class)->dispatchAsync($eventClass, ...$args);
    }

    /**
     * Dispatch multiple events in batch
     */
    protected function dispatchBatch(array $events): void
    {
        app(EventBusService::class)->dispatchBatch($events);
    }
}