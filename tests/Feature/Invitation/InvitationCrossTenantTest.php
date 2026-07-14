<?php declare(strict_types=1);

namespace Tests\Feature\Invitation;

use App\Models\Invitation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * Proves that Invitation is intentionally EXCLUDED from TenantScope.
 *
 * The accept flow resolves invitations via TeamInvitationService::resolveByToken()
 * which manually scopes by tenant_id. A cross-tenant attempt yields 404 because
 * the invitation belongs to a different tenant than the authenticated user.
 *
 * @see docs/architecture/module-ownership-ssot.md#invitation--documented-tenantscope-exclusion
 */
class InvitationCrossTenantTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_cross_tenant_invitation_accept_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $inviterA = $this->createApiUser($tenantA, ['invitation.create']);
        $teamA = $this->createTeam($tenantA, $inviterA);

        $inviteeB = $this->createApiUser($tenantB, ['invitation.accept'], [
            'email' => 'invitee-cross+' . Str::random(8) . '@example.com',
        ]);

        $token = 'cross-tenant-' . Str::random(40);
        Invitation::factory()
            ->withRawToken($token)
            ->create([
                'tenant_id' => $tenantA->id,
                'team_id' => $teamA->id,
                'invited_by_user_id' => $inviterA->id,
                'email' => strtolower($inviteeB->email),
                'status' => Invitation::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]);

        // User from tenant B tries to accept invitation belonging to tenant A
        // → 404 because resolveByToken scopes by tenant_id
        $this->asUser($inviteeB, $tenantB)
            ->postJson('/api/teams/' . $teamA->id . '/invitations/' . $token . '/accept')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_same_tenant_invitation_accept_succeeds(): void
    {
        $tenant = Tenant::factory()->create();
        $inviter = $this->createApiUser($tenant, ['invitation.create']);
        $team = $this->createTeam($tenant, $inviter);

        $invitee = $this->createApiUser($tenant, ['invitation.accept'], [
            'email' => 'invitee-same+' . Str::random(8) . '@example.com',
        ]);

        $token = 'same-tenant-' . Str::random(40);
        Invitation::factory()
            ->withRawToken($token)
            ->create([
                'tenant_id' => $tenant->id,
                'team_id' => $team->id,
                'invited_by_user_id' => $inviter->id,
                'email' => strtolower($invitee->email),
                'status' => Invitation::STATUS_PENDING,
                'expires_at' => now()->addDay(),
            ]);

        $this->asUser($invitee, $tenant)
            ->postJson('/api/teams/' . $team->id . '/invitations/' . $token . '/accept')
            ->assertStatus(200)
            ->assertJsonPath('data.status', Invitation::STATUS_ACCEPTED);
    }

    public function test_invitation_model_does_not_use_tenantscope_trait(): void
    {
        $this->assertNotContains(
            \App\Traits\TenantScope::class,
            class_uses_recursive(Invitation::class),
            'Invitation must NOT use TenantScope — it is a documented exclusion'
        );
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
        return Team::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Team ' . Str::random(5),
            'status' => Team::STATUS_ACTIVE,
            'is_active' => true,
            'team_lead_id' => $owner->id,
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
