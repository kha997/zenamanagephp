<?php declare(strict_types=1);

// Dispatch event khi cập nhật component progress
use Illuminate\Support\Facades\Auth;
use Src\CoreProject\Events\ComponentProgressUpdated;

$event = new ComponentProgressUpdated(
    componentId: $component->id,
    projectId: $component->project_id,
    actorId: Auth::id() ?? 'system',
    tenantId: Auth::user()?->tenant_id ?? null,
    oldProgress: $oldProgress,
    newProgress: $newProgress,
    oldCost: $oldCost,
    newCost: $newCost,
    changedFields: ['progress_percent', 'actual_cost'],
    timestamp: new \DateTime()
);

$event->component = $component;

event($event);
