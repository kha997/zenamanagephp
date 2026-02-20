<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Invitation;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class InvitationApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    public function test_create_invitation_returns_token(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->createApiUser($tenant, ['invitation.create', 'invitation.view']);

        $team = $this->createTeam($tenant, $manager);

        $response = $this->asUser($manager, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations', [
                'email' => 'invitee@example.com',
                'role' => Team::ROLE_MEMBER,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'invitee@example.com')
            ->assertJsonPath('data.team_id', $team->id);

        $token = (string) $response->json('data.token');
        $this->assertNotSame('', $token);
    }

    public function test_accept_invitation_creates_team_membership(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->createApiUser($tenant, ['invitation.create', 'invitation.accept']);
        $invitee = $this->createApiUser($tenant, ['invitation.accept'], [
            'email' => 'invitee+' . Str::random(6) . '@example.com',
        ]);

        $team = $this->createTeam($tenant, $manager);

        $inviteResponse = $this->asUser($manager, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations', [
                'email' => $invitee->email,
                'role' => Team::ROLE_MEMBER,
            ]);

        $token = (string) $inviteResponse->json('data.token');

        $this->asUser($invitee, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations/' . $token . '/accept')
            ->assertStatus(200)
            ->assertJsonPath('data.status', Invitation::STATUS_ACCEPTED);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_revoke_and_expired_invitation_behaviour(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->createApiUser($tenant, ['invitation.create', 'invitation.revoke', 'invitation.accept']);
        $invitee = $this->createApiUser($tenant, ['invitation.accept']);

        $team = $this->createTeam($tenant, $manager);

        $created = $this->asUser($manager, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations', [
                'email' => 'revoked@example.com',
                'role' => Team::ROLE_MEMBER,
            ])
            ->assertStatus(201);

        $invitationId = (string) $created->json('data.id');

        $this->asUser($manager, $tenant)
            ->deleteJson('/api/teams/' . $team->id . '/invitations/' . $invitationId)
            ->assertStatus(200)
            ->assertJsonPath('data.status', Invitation::STATUS_CANCELLED);

        $expiredToken = (string) Str::random(32);
        Invitation::create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'token' => $expiredToken,
            'email' => $invitee->email,
            'role' => Team::ROLE_MEMBER,
            'organization_id' => 0,
            'invited_by' => 0,
            'invited_by_user_id' => $manager->id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->asUser($invitee, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations/' . $expiredToken . '/accept')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'E409.CONFLICT');
    }

    public function test_cross_tenant_token_team_mismatch_blocked(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $managerA = $this->createApiUser($tenantA, ['invitation.create', 'invitation.accept']);
        $userB = $this->createApiUser($tenantB, ['invitation.accept']);

        $teamA = $this->createTeam($tenantA, $managerA);
        $teamB = $this->createTeam($tenantB, $userB);

        $create = $this->asUser($managerA, $tenantA)
            ->postJson('/api/teams/' . $teamA->id . '/invitations', [
                'email' => 'tenant-a-invitee@example.com',
                'role' => Team::ROLE_MEMBER,
            ]);

        $token = (string) $create->json('data.token');

        $this->asUser($userB, $tenantB)
            ->postJson('/api/teams/' . $teamA->id . '/invitations/' . $token . '/accept')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');

        $this->asUser($managerA, $tenantA)
            ->postJson('/api/teams/' . $teamB->id . '/invitations/' . $token . '/accept')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_invitation_create_permission_missing_returns_403(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createApiUser($tenant, []);
        $team = $this->createTeam($tenant, $user);

        $this->asUser($user, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations', [
                'email' => 'noperm@example.com',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    private function createApiUser(Tenant $tenant, array $permissions, array $attributes = []): User
    {
        return $this->createTenantUser(
            $tenant,
            array_merge(['email' => 'invite+' . Str::random(8) . '@example.com'], $attributes),
            ['admin'],
            $permissions,
        );
    }

    private function createTeam(Tenant $tenant, User $owner): Team
    {
        return Team::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => 'Team ' . Str::random(5),
            'status' => Team::STATUS_ACTIVE,
            'is_active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
    }

    private function asUser(User $user, Tenant $tenant): self
    {
        Sanctum::actingAs($user);

        return $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ]);
    }
}
