<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class TeamMembersApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    public function test_add_remove_list_team_members_happy_path(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->createApiUser($tenant, [
            'team.member.add', 'team.member.remove', 'team.member.view', 'team.member.update-role',
        ]);
        $member = $this->createApiUser($tenant, []);

        $team = Team::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => 'QA Team',
            'status' => Team::STATUS_ACTIVE,
            'is_active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->apiAs($manager, $tenant)
            ->postJson('/api/teams/' . $team->id . '/members', [
                'user_id' => $member->id,
                'role' => Team::ROLE_MEMBER,
            ])
            ->assertStatus(201);

        $this->apiAs($manager, $tenant)
            ->patchJson('/api/teams/' . $team->id . '/members/role', [
                'user_id' => $member->id,
                'role' => Team::ROLE_LEAD,
            ])
            ->assertStatus(200);

        $this->apiAs($manager, $tenant)
            ->getJson('/api/teams/' . $team->id . '/members')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $member->id);

        $this->apiAs($manager, $tenant)
            ->deleteJson('/api/teams/' . $team->id . '/members', ['user_id' => $member->id])
            ->assertStatus(200);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_team_members_cross_tenant_requests_blocked_with_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $managerA = $this->createApiUser($tenantA, ['team.member.add', 'team.member.view', 'team.member.remove']);
        $managerB = $this->createApiUser($tenantB, ['team.member.add', 'team.member.view', 'team.member.remove']);

        $memberA = $this->createApiUser($tenantA, []);

        $teamA = Team::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantA->id,
            'name' => 'Tenant A Team',
            'status' => Team::STATUS_ACTIVE,
            'is_active' => true,
            'created_by' => $managerA->id,
            'updated_by' => $managerA->id,
        ]);

        $this->apiAs($managerB, $tenantB)
            ->postJson('/api/teams/' . $teamA->id . '/members', [
                'user_id' => $memberA->id,
                'role' => Team::ROLE_MEMBER,
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');

        $this->apiAs($managerB, $tenantB)
            ->getJson('/api/teams/' . $teamA->id . '/members')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');

        $this->apiAs($managerB, $tenantB)
            ->deleteJson('/api/teams/' . $teamA->id . '/members', ['user_id' => $memberA->id])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_team_members_permission_missing_returns_403(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createApiUser($tenant, []);
        $member = $this->createApiUser($tenant, []);

        $team = Team::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => 'No Permission Team',
            'status' => Team::STATUS_ACTIVE,
            'is_active' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->apiAs($actor, $tenant)
            ->postJson('/api/teams/' . $team->id . '/members', [
                'user_id' => $member->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    private function createApiUser(Tenant $tenant, array $permissions): User
    {
        return $this->createTenantUser(
            $tenant,
            ['email' => 'team-member+' . Str::random(8) . '@example.com'],
            ['admin'],
            $permissions,
        );
    }
}
