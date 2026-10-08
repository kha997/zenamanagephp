<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
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

    public function declareFunding(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.declare_funding');
    }

    public function createTransfer(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.create_transfer');
    }

    public function createExpense(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.create_expense');
    }

    public function submitExpense(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.submit_expense');
    }

    public function approveExpense(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.approve_expense');
    }

    public function adjust(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.adjust');
    }

    public function reverse(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.reverse');
    }

    /** GAP-067 S4a: reconcile and undo reconciliation of the project's wallets. */
    public function reconcile(User $user, Project $project): bool
    {
        return $this->projectAbility($user, $project, 'treasury.reconcile');
    }

    /**
     * GAP-064 Owner answer 2: holders of treasury.manage_wallets (owner X)
     * may move money out of any wallet of the project; everyone else only
     * out of a wallet whose custodian party is linked to their own user.
     */
    public function transferFromWallet(User $user, TreasuryWallet $wallet): bool
    {
        if ((string) $user->tenant_id !== (string) $wallet->tenant_id) {
            return false;
        }

        if ($user->hasPermission('treasury.manage_wallets')) {
            return true;
        }

        return $wallet->custodian_party_id !== null
            && TreasuryFinancialParty::query()
                ->whereKey($wallet->custodian_party_id)
                ->where('linked_user_id', (string) $user->id)
                ->exists();
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
