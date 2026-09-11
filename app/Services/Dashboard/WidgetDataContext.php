<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Models\Project;
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

        if ($projectId !== null) {
            $hasAccess = Project::query()
                ->whereKey($projectId)
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($user) {
                    if ($user->role === 'system_admin') {
                        return;
                    }

                    $query->where('pm_id', $user->id)
                        ->orWhereHas('projectUsers', function ($projectUsers) use ($user) {
                            $projectUsers->where('user_id', $user->id);
                        });
                })
                ->exists();

            if (! $hasAccess) {
                throw new InvalidArgumentException('Widget context project is not accessible to the authenticated user.');
            }
        }
    }
}
