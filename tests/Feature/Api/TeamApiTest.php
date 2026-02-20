<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    public function test_team_crud_happy_path(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createApiUser($tenant, [
            'team.view', 'team.create', 'team.update', 'team.delete',
        ]);

        $create = $this->apiAs($user, $tenant)->postJson('/api/teams', [
            'name' => 'MEP Team',
            'description' => 'Mechanical engineering team',
            'department' => 'Engineering',
            'status' => Team::STATUS_ACTIVE,
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.name', 'MEP Team');

        $teamId = (string) $create->json('data.id');

        $this->apiAs($user, $tenant)
            ->getJson('/api/teams/' . $teamId)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $teamId);

        $this->apiAs($user, $tenant)
            ->putJson('/api/teams/' . $teamId, [
                'name' => 'MEP Team Updated',
                'department' => 'MEP',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'MEP Team Updated');

        $this->apiAs($user, $tenant)
            ->deleteJson('/api/teams/' . $teamId)
            ->assertStatus(200);

        $this->assertDatabaseMissing('teams', ['id' => $teamId]);
    }

    public function test_team_cross_tenant_show_update_destroy_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $owner = $this->createApiUser($tenantA, ['team.view', 'team.create', 'team.update', 'team.delete']);
        $crossTenantUser = $this->createApiUser($tenantB, ['team.view', 'team.update', 'team.delete']);

        $team = Team::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantA->id,
            'name' => 'Tenant A Team',
            'description' => 'A only',
            'status' => Team::STATUS_ACTIVE,
            'is_active' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->apiAs($crossTenantUser, $tenantB)
            ->getJson('/api/teams/' . $team->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');

        $this->apiAs($crossTenantUser, $tenantB)
            ->putJson('/api/teams/' . $team->id, ['name' => 'Should fail'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');

        $this->apiAs($crossTenantUser, $tenantB)
            ->deleteJson('/api/teams/' . $team->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'E404.NOT_FOUND');
    }

    public function test_team_index_permission_missing_returns_403(): void
    {
        $tenant = Tenant::factory()->create();
        $userWithoutTeamPermission = $this->createApiUser($tenant, []);

        $this->apiAs($userWithoutTeamPermission, $tenant)
            ->getJson('/api/teams')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    private function createApiUser(Tenant $tenant, array $permissions): User
    {
        return $this->createTenantUser(
            $tenant,
            ['email' => 'team+' . Str::random(8) . '@example.com'],
            ['admin'],
            $permissions,
        );
    }
}
