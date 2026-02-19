<?php declare(strict_types=1);

namespace Src\CoreProject\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComponentCostUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly \DateTime $timestamp;
    public ?object $component = null;
    public readonly string $entityId;

    public function __construct(
        public readonly string $componentId,
        public readonly string $projectId,
        public readonly string $actorId,
        public readonly string $tenantId,
        public readonly ?float $oldCost,
        public readonly ?float $newCost,
        public readonly array $changedFields,
        ?\DateTime $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
        $this->entityId = $this->componentId;
    }

    public function getEventName(): string
    {
        return 'Project.Component.CostUpdated';
    }

    public function getPayload(): array
    {
        return [
            'entityId' => $this->componentId,
            'component_id' => $this->componentId,
            'componentId' => $this->componentId,
            'project_id' => $this->projectId,
            'projectId' => $this->projectId,
            'actor_id' => $this->actorId,
            'actorId' => $this->actorId,
            'tenant_id' => $this->tenantId,
            'tenantId' => $this->tenantId,
            'old_cost' => $this->oldCost,
            'new_cost' => $this->newCost,
            'changed_fields' => $this->changedFields,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}
