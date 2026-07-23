<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Team;
use App\Models\Tenant;
use App\Policies\TeamPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the /app/team 403 bug: TeamPolicy used to check
 * $user->hasRole(['super_admin','admin','pm']) — literal role-name strings
 * that never match this app's actual seeded admin role name ("System Admin",
 * created by ZenaAdminRolePermissionSeeder), so a real logged-in admin got a
 * hard 403 on every Team ability. Fixed by switching to permission-based
 * checks (hasPermission('team.view') etc.), matching the pattern already
 * used by ProjectPolicy. These tests grant the actual `team.*` permission
 * (not just an empty role) so they exercise the real authorization path.
 */
class TeamPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $policy;
    protected $tenant;
    protected $user;
    protected $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TeamPolicy();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant'
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example-' . uniqid() . '.com'
        ]);

        $this->team = Team::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Team'
        ]);
    }

    /**
     * Grants the given team.* permission code to $this->user via a fresh
     * Role, mirroring how a real seeded role (e.g. "System Admin") grants
     * permissions in production -- not via assignRole()'s bare role name,
     * which carries no permissions at all.
     */
    private function grantTeamPermission(string $code): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'Team Policy Test Role ' . uniqid()],
            ['scope' => 'tenant', 'allow_override' => false, 'is_active' => true]
        );

        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'module' => 'team', 'action' => explode('.', $code)[1] ?? $code]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $this->user->roles()->syncWithoutDetaching($role->id);
    }

    public function test_user_can_view_team_with_permission(): void
    {
        $this->grantTeamPermission('team.view');
        $this->assertTrue($this->policy->view($this->user, $this->team));
    }

    public function test_user_without_permission_or_membership_cannot_view_team(): void
    {
        $this->assertFalse($this->policy->view($this->user, $this->team));
    }

    public function test_user_cannot_view_team_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant-' . uniqid()]);
        $otherTeam = Team::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->grantTeamPermission('team.view');
        $this->assertFalse($this->policy->view($this->user, $otherTeam));
    }

    public function test_user_can_create_team_with_permission(): void
    {
        $this->grantTeamPermission('team.create');
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_can_invite_members_with_permission(): void
    {
        $this->grantTeamPermission('team.member.add');
        $this->assertTrue($this->policy->invite($this->user, $this->team));
    }

    public function test_user_can_delete_team_with_permission(): void
    {
        $this->grantTeamPermission('team.delete');
        $this->assertTrue($this->policy->delete($this->user, $this->team));
    }

    /**
     * Reproduces the exact real-world bug: a user whose only grant is a
     * team.view PERMISSION (as "System Admin" actually gets in production,
     * via ZenaAdminRolePermissionSeeder's canonical-permission sync) must
     * pass viewAny() -- regardless of what the role is literally named.
     */
    public function test_viewany_passes_for_permission_grant_regardless_of_role_name(): void
    {
        $this->grantTeamPermission('team.view');
        $this->assertTrue($this->policy->viewAny($this->user));
    }
}