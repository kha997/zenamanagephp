<?php declare(strict_types=1);

namespace Src\InteractionLogs\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi interaction log được approve cho client
 * Trigger notifications cho client và team members
 */
class InteractionLogApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $interactionLogId,
        public readonly string $projectId,
        public readonly string $approvedBy,
        public readonly string $tenantId,
        public readonly \DateTime $timestamp
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    public function getEventName(): string
    {
        return 'InteractionLogs.InteractionLog.Approved';
    }

    public function getPayload(): array
    {
        return [
            'interaction_log_id' => $this->interactionLogId,
            'project_id' => $this->projectId,
            'approved_by' => $this->approvedBy,
            'tenant_id' => $this->tenantId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}