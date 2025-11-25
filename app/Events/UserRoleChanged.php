<?php declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * User Role Changed Event
 * 
 * Fired when a user's role is changed.
 * Used to revoke WebSocket connections and update permissions.
 */
class UserRoleChanged
{
    use Dispatchable, SerializesModels;

    public User $user;
    public ?string $oldRole;
    public ?string $newRole;

    public function __construct(User $user, ?string $oldRole, ?string $newRole)
    {
        $this->user = $user;
        $this->oldRole = $oldRole;
        $this->newRole = $newRole;
    }
}

