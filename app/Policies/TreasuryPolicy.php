<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\UserRoleProject;

/**
 * GAP-063 S1 — Project Treasury access rule (Gate 2 Option A §3).
 *
 * Project-scoped abilities require, all at once: same tenant, the action's
 * treasury.* code, and either treasury.all_projects or an active membership
 * of the project (project_user_roles, deleted_at NULL). Parties are
 * tenant-scoped, so their abilities skip the membership step.
 */
class TreasuryPolicy
{
    public function viewProject(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.view');
    }

    public function manageWallets(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.manage_wallets');
    }

    public function viewParties(User $user): bool
    {
        return $user->hasPermission('treasury.view');
    }

    public function manageParties(User $user): bool
    {
        return $user->hasPermission('treasury.manage_parties');
    }

    public function accessesAllProjects(User $user): bool
    {
        return $user->hasPermission('treasury.all_projects');
    }

    private function projectAbility(User $user, Project $project, string $code): bool
    {
        if ((string) $user->tenant_id !== (string) $project->tenant_id) {
            return false;
        }

        if (!$user->hasPermission($code)) {
            return false;
        }

        return $this->accessesAllProjects($user) || $this->isProjectMember($user, $project);
    }

    private function isProjectMember(User $user, Project $project): bool
    {
        return UserRoleProject::query()
            ->where('project_id', (string) $project->id)
            ->where('user_id', (string) $user->id)
            ->whereNull('deleted_at')
            ->exists();
    }
}
