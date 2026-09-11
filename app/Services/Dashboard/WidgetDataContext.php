<?php

namespace App\Services\Dashboard;

use App\Models\User;
use InvalidArgumentException;

final readonly class WidgetDataContext
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public User $user,
        public string $tenantId,
        public ?string $projectId = null,
        public array $parameters = [],
    ) {
        if ((string) $user->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Widget context tenant does not match the authenticated user.');
        }

        // Project authorization is request-wide and is performed by the role service
        // before this context is constructed. Providers receive only authorized context.
    }
}
