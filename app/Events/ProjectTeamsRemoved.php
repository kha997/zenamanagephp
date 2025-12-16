<?php declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when teams are removed from a project
 */
class ProjectTeamsRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $projectId;
    public string $tenantId;
    public string $actorId;
    public array $teamIds;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $projectId,
        string $tenantId,
        string $actorId,
        array $teamIds
    ) {
        $this->projectId = $projectId;
        $this->tenantId = $tenantId;
        $this->actorId = $actorId;
        $this->teamIds = $teamIds;
        $this->timestamp = now()->toISOString();
    }
}

