<?php declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when users are removed from a project
 */
class ProjectUsersRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $projectId;
    public string $tenantId;
    public string $actorId;
    public array $userIds;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $projectId,
        string $tenantId,
        string $actorId,
        array $userIds
    ) {
        $this->projectId = $projectId;
        $this->tenantId = $tenantId;
        $this->actorId = $actorId;
        $this->userIds = $userIds;
        $this->timestamp = now()->toISOString();
    }
}

