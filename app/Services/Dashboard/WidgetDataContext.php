<?php

namespace App\Services\Dashboard;

use App\Models\User;
use InvalidArgumentException;

final readonly class WidgetDataContext
{
    public function __construct(
        public User $user,
        public string $tenantId,
        public ?string $projectId = null,
        public array $parameters = [],
    ) {
        if ((string) $user->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Widget context tenant does not match the authenticated user.');
        }
    }
}
