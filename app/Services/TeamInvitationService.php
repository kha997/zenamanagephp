<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Notifications\TeamInvitationCreatedNotification;

class TeamInvitationService
{
    public function resolveByToken(string $tenantId, ?string $teamId, string $token): ?Invitation
    {
        $providedHash = hash('sha256', $token);

        $query = Invitation::query()
            ->where('tenant_id', $tenantId);

        if ($teamId !== null && $teamId !== '') {
            $query->where('team_id', $teamId);
        }

        $hashedMatch = (clone $query)
            ->where('token_hash', $providedHash)
            ->orderByDesc('created_at')
            ->first();

        if ($hashedMatch instanceof Invitation && hash_equals((string) $hashedMatch->token_hash, $providedHash)) {
            return $hashedMatch;
        }

        $legacyCandidates = (clone $query)
            ->whereNull('token_hash')
            ->whereNotNull('token')
            ->orderByDesc('created_at')
            ->get();

        foreach ($legacyCandidates as $candidate) {
            $storedToken = (string) ($candidate->token ?? '');
            if ($storedToken !== '' && hash_equals($storedToken, $token)) {
                $candidate->forceFill([
                    'token_hash' => $providedHash,
                    'token_version' => 1,
                ])->save();

                return $candidate;
            }
        }

        return null;
    }

    public function create(
        Team $team,
        User $inviter,
        string $email,
        string $role,
        ?string $message,
        ?int $expiresInDays = null
    ): Invitation {
        $normalizedEmail = strtolower(trim($email));

        $existingPending = Invitation::query()
            ->where('tenant_id', $team->tenant_id)
            ->where('team_id', $team->id)
            ->where('email', $normalizedEmail)
            ->where('status', Invitation::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            throw new \DomainException('A pending invitation already exists for this email in the team.');
        }

        $expiresAt = now()->addDays($expiresInDays ?? 7);
        $rawToken = Str::random(80);

        $invitation = Invitation::query()->create([
            'tenant_id' => $team->tenant_id,
            'team_id' => $team->id,
            'token' => $rawToken,
            'token_hash' => hash('sha256', $rawToken),
            'token_version' => 1,
            'email' => $normalizedEmail,
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

        try {
            Notification::route('mail', $invitation->email)
                ->notify(new TeamInvitationCreatedNotification($invitation, $rawToken, $team, $inviter));
        } catch (\Throwable $exception) {
            Log::warning('Failed to dispatch invitation notification', [
                'invitation_id' => $invitation->id,
                'team_id' => $team->id,
                'tenant_id' => $team->tenant_id,
                'error' => $exception->getMessage(),
            ]);
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
        if (
            $invitation->status === Invitation::STATUS_ACCEPTED
            || $invitation->accepted_at !== null
            || ($invitation->accepted_by_user_id ?? '') !== ''
        ) {
            throw new \DomainException('Invitation has already been accepted.');
        }

        if (
            $invitation->status === Invitation::STATUS_CANCELLED
            || $invitation->revoked_at !== null
            || ($invitation->revoked_by_user_id ?? '') !== ''
        ) {
            throw new \DomainException('Invitation has been revoked.');
        }

        if ($invitation->expires_at?->isPast() ?? true) {
            throw new \DomainException('Invitation has expired.');
        }

        if ($invitation->status !== Invitation::STATUS_PENDING) {
            throw new \DomainException('Invitation is no longer valid.');
        }

        $invitedUserId = (string) data_get($invitation->getAttributes(), 'invited_user_id', '');
        if ($invitedUserId !== '') {
            if (!hash_equals($invitedUserId, $actor->id)) {
                throw new \InvalidArgumentException('Invitation is not intended for this user.');
            }
        } elseif (strcasecmp((string) $actor->email, (string) $invitation->email) !== 0) {
            throw new \InvalidArgumentException('Invitation is not intended for this user.');
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
