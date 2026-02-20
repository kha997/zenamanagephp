<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeamInvitationService
{
    public function create(
        Team $team,
        User $inviter,
        string $email,
        string $role,
        ?string $message,
        ?int $expiresInDays = null
    ): Invitation {
        $existingPending = Invitation::query()
            ->where('tenant_id', $team->tenant_id)
            ->where('team_id', $team->id)
            ->where('email', $email)
            ->where('status', Invitation::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            throw new \DomainException('A pending invitation already exists for this email in the team.');
        }

        $expiresAt = now()->addDays($expiresInDays ?? 7);

        $invitation = Invitation::query()->create([
            'tenant_id' => $team->tenant_id,
            'team_id' => $team->id,
            'email' => $email,
            'role' => $role,
            'message' => $message,
            'organization_id' => (int) ($inviter->organization_id ?? 0),
            'invited_by' => 0,
            'invited_by_user_id' => $inviter->id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => $expiresAt,
            'metadata' => [
                'team_name' => $team->name,
            ],
        ]);

        if (!$invitation instanceof Invitation) {
            throw new \RuntimeException('Failed to create invitation.');
        }

        return $invitation;
    }

    public function revoke(Invitation $invitation, User $actor): Invitation
    {
        if ($invitation->status !== Invitation::STATUS_PENDING) {
            throw new \DomainException('Only pending invitations can be revoked.');
        }

        $invitation->markAsCancelled($actor->id);

        return $invitation->fresh() ?? $invitation;
    }

    public function accept(Invitation $invitation, User $actor): Invitation
    {
        if (!$invitation->canBeAccepted()) {
            throw new \DomainException('Invitation is no longer valid.');
        }

        DB::transaction(function () use ($invitation, $actor): void {
            /** @var Team $team */
            $team = Team::query()
                ->where('tenant_id', $invitation->tenant_id)
                ->whereKey($invitation->team_id)
                ->firstOrFail();

            $alreadyMember = DB::table('team_members')
                ->where('team_id', $team->id)
                ->where('user_id', $actor->id)
                ->whereNull('left_at')
                ->exists();

            if (!$alreadyMember) {
                $team->members()->syncWithoutDetaching([
                    $actor->id => [
                        'role' => $this->normalizeTeamRole((string) $invitation->role),
                        'joined_at' => now(),
                        'left_at' => null,
                    ],
                ]);
            }

            $invitation->markAsAccepted($actor->id);
            $invitation->update(['accepted_by' => 0]);
        });

        return $invitation->fresh() ?? $invitation;
    }

    private function normalizeTeamRole(string $role): string
    {
        $normalized = strtolower(trim($role));

        return match ($normalized) {
            Team::ROLE_ADMIN, Team::ROLE_LEAD, Team::ROLE_MEMBER => $normalized,
            default => Team::ROLE_MEMBER,
        };
    }
}
