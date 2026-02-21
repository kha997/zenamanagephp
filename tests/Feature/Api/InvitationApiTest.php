<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Invitation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class InvitationApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    public function test_can_list_invitations_with_permission_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->createApiUser($tenant, ['invitation.view']);
        $team = $this->createTeam($tenant, $viewer);
        $otherTeam = $this->createTeam($tenant, $viewer);

        Invitation::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'invited_by_user_id' => $viewer->id,
        ]);

        Invitation::factory()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $otherTeam->id,
            'invited_by_user_id' => $viewer->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createApiUser($otherTenant, ['invitation.view']);
        $otherTenantTeam = $this->createTeam($otherTenant, $otherUser);

        Invitation::factory()->create([
            'tenant_id' => $otherTenant->id,
            'team_id' => $otherTenantTeam->id,
            'invited_by_user_id' => $otherUser->id,
        ]);

        $response = $this->asUser($viewer, $tenant)
            ->getJson('/api/teams/' . $team->id . '/invitations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success');

        $data = collect($response->json('data'));
        $this->assertCount(2, $data);
        $this->assertTrue($data->every(fn (array $row): bool => $row['team_id'] === $team->id));
        $this->assertTrue($data->every(fn (array $row): bool => $row['tenant_id'] === $tenant->id));
    }

    public function test_create_invitation_requires_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createApiUser($tenant, []);
        $team = $this->createTeam($tenant, $user);

        $this->asUser($user, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations', [
                'email' => 'noperm@example.com',
                'role' => Team::ROLE_MEMBER,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    public function test_can_create_invitation_hash_only_token(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->createApiUser($tenant, ['invitation.create']);
        $team = $this->createTeam($tenant, $manager);

        $response = $this->asUser($manager, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations', [
                'email' => 'invitee+' . Str::random(8) . '@example.com',
                'role' => Team::ROLE_MEMBER,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success');

        $invitationId = (int) $response->json('data.id');
        $rawToken = (string) $response->json('data.token');

        $this->assertNotSame('', $rawToken);
        $this->assertDatabaseHas('invitations', [
            'id' => $invitationId,
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'token' => null,
            'token_version' => Invitation::TOKEN_VERSION_HASH_ONLY,
        ]);

        $invitation = Invitation::query()->findOrFail($invitationId);
        $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);
    }

    public function test_cross_tenant_team_returns_404_for_create_and_list(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->createApiUser($tenantA, ['invitation.view', 'invitation.create']);
        $userB = $this->createApiUser($tenantB, ['invitation.view', 'invitation.create']);

        $teamB = $this->createTeam($tenantB, $userB);

        $this->asUser($userA, $tenantA)
            ->getJson('/api/teams/' . $teamB->id . '/invitations')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');

        $this->asUser($userA, $tenantA)
            ->postJson('/api/teams/' . $teamB->id . '/invitations', [
                'email' => 'cross-tenant@example.com',
                'role' => Team::ROLE_MEMBER,
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_can_revoke_invitation_with_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->createApiUser($tenant, ['invitation.revoke']);
        $team = $this->createTeam($tenant, $manager);

        $invitation = Invitation::factory()->create([
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'invited_by_user_id' => $manager->id,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $this->asUser($manager, $tenant)
            ->deleteJson('/api/teams/' . $team->id . '/invitations/' . $invitation->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Invitation::STATUS_CANCELLED);

        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_CANCELLED, $invitation->status);
        $this->assertNotNull($invitation->revoked_at);
    }

    public function test_accept_invitation_valid_token_succeeds_and_reuse_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->createApiUser($tenant, ['invitation.accept']);
        $invitee = $this->createApiUser($tenant, ['invitation.accept'], [
            'email' => 'invitee+' . Str::random(8) . '@example.com',
        ]);
        $team = $this->createTeam($tenant, $manager);

        $validToken = 'valid-' . Str::random(40);
        $validInvitation = Invitation::factory()
            ->withRawToken($validToken)
            ->create([
                'tenant_id' => $tenant->id,
                'team_id' => $team->id,
                'invited_by_user_id' => $manager->id,
                'email' => strtolower($invitee->email),
                'status' => Invitation::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]);

        $this->assertSame(hash('sha256', $validToken), $validInvitation->token_hash);
        $this->assertNull($validInvitation->token);

        $this->asUser($invitee, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations/' . $validToken . '/accept')
            ->assertStatus(200)
            ->assertJsonPath('data.status', Invitation::STATUS_ACCEPTED);

        $this->asUser($invitee, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations/' . $validToken . '/accept')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'E409.CONFLICT');

        $expiredToken = 'expired-' . Str::random(40);
        Invitation::factory()
            ->withRawToken($expiredToken)
            ->expired()
            ->create([
                'tenant_id' => $tenant->id,
                'team_id' => $team->id,
                'invited_by_user_id' => $manager->id,
                'email' => strtolower($invitee->email),
                'status' => Invitation::STATUS_PENDING,
            ]);

        $this->asUser($invitee, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations/' . $expiredToken . '/accept')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'E409.CONFLICT');
    }

    public function test_accept_route_uses_invitation_accept_throttle_middleware(): void
    {
        $apiRoute = $this->findRoute('api/teams/{team}/invitations/{token}/accept', 'POST');

        $this->assertNotNull($apiRoute);
        $this->assertContains('throttle:invitation-accept', $apiRoute->gatherMiddleware());
    }

    private function createApiUser(Tenant $tenant, array $permissions, array $attributes = []): User
    {
        $user = $this->createTenantUser(
            $tenant,
            array_merge(['email' => 'invite+' . Str::random(8) . '@example.com'], $attributes),
            ['admin'],
            $permissions,
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'scope' => Role::SCOPE_SYSTEM,
                'allow_override' => true,
                'is_active' => true,
                'description' => 'System Administrator',
            ]
        );

        foreach ($permissions as $permissionCode) {
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                [
                    'name' => $permissionCode,
                    'module' => 'invitation',
                    'action' => 'access',
                    'description' => $permissionCode,
                ]
            );

            $adminRole->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return $user;
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

    private function findRoute(string $uri, string $method): ?\Illuminate\Routing\Route
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array(strtoupper($method), $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }
}
