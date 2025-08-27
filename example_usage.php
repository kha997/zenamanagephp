// Dispatch event khi cập nhật component progress
use Src\CoreProject\Events\ComponentProgressUpdated;

$event = new ComponentProgressUpdated(
    componentId: $component->id,
    projectId: $component->project_id,
    actorId: auth()->id(),
    tenantId: auth()->user()->tenant_id,
    oldProgress: $oldProgress,
    newProgress: $newProgress,
    oldCost: $oldCost,
    newCost: $newCost,
    changedFields: ['progress_percent', 'actual_cost'],
    timestamp: new \DateTime()
);

event($event);