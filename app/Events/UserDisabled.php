<?php declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi user bị disable
 * Used to revoke WebSocket connections and invalidate sessions
 */
class UserDisabled
{
    use Dispatchable, SerializesModels;

    /**
     * Constructor
     *
     * @param User $user User bị disable
     */
    public function __construct(
        public User $user
    ) {
    }

    /**
     * Lấy tên event theo convention Domain.Entity.Action
     *
     * @return string
     */
    public function getEventName(): string
    {
        return 'User.User.Disabled';
    }

    /**
     * Lấy payload đầy đủ của event
     *
     * @return array
     */
    public function getPayload(): array
    {
        return [
            'entityId' => $this->user->id,
            'userId' => $this->user->id,
            'tenantId' => $this->user->tenant_id,
            'email' => $this->user->email,
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];
    }
}

