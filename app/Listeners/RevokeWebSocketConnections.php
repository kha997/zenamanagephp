<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserDisabled;
use App\WebSocket\DashboardWebSocketHandler;
use Illuminate\Support\Facades\Log;

/**
 * Listener để revoke WebSocket connections khi user bị disable
 */
class RevokeWebSocketConnections
{
    private DashboardWebSocketHandler $websocketHandler;

    /**
     * Constructor
     */
    public function __construct(DashboardWebSocketHandler $websocketHandler)
    {
        $this->websocketHandler = $websocketHandler;
    }

    /**
     * Handle the event
     */
    public function handle(UserDisabled $event): void
    {
        $user = $event->user;

        Log::info('Revoking WebSocket connections for disabled user', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'traceId' => request()->header('X-Request-Id', uniqid('req_', true))
        ]);

        // Revoke all WebSocket connections for this user
        $this->websocketHandler->revokeUserConnections((string) $user->id);
    }
}

