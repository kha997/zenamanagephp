<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRoleChanged;
use App\WebSocket\DashboardWebSocketHandler;
use Illuminate\Support\Facades\Log;

/**
 * Revoke WebSocket Connections on Role Change
 * 
 * Revokes all WebSocket connections when a user's role changes.
 * Ensures users reconnect with updated permissions.
 */
class RevokeWebSocketOnRoleChange
{
    public function __construct(
        private DashboardWebSocketHandler $wsHandler
    ) {}

    /**
     * Handle the event
     */
    public function handle(UserRoleChanged $event): void
    {
        $user = $event->user;

        Log::info('Revoking WebSocket connections due to role change', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'old_role' => $event->oldRole,
            'new_role' => $event->newRole,
            'traceId' => request()->header('X-Request-Id', uniqid('req_', true)),
        ]);

        // Revoke all WebSocket connections for this user
        $this->wsHandler->revokeUserConnectionsOnRoleChange(
            (string) $user->id,
            $event->oldRole,
            $event->newRole
        );
    }
}

