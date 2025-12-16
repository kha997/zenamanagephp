<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\ProjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProjectAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectAssignmentService $service;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProjectAssignmentService();
        $this->tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X'; // Test tenant ID
    }

    /**
     * Test assigning a single user to a project
     */
    public function test_assign_user_to_project(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        Auth::login($user);
        
        $this->service->assignUserToProject(
            $project->id,
            $user->id,
            null,
            $this->tenantId
        );
        
        $this->assertTrue($project->users()->where('users.id', $user->id)->exists());
    }

    /**
     * Test assigning multiple users to a project (bulk)
     */
    public function test_assign_users_to_project_bulk(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $users = User::factory()->count(3)->create(['tenant_id' => $this->tenantId]);
        
        $assignments = $users->map(function ($user) {
            return ['user_id' => $user->id];
        })->toArray();
        
        $results = $this->service->assignUsersToProject(
            $project->id,
            $assignments,
            $this->tenantId
        );
        
        $this->assertCount(3, $results['success']);
        $this->assertCount(0, $results['failed']);
    }

    /**
     * Test tenant isolation - cannot assign user from different tenant
     */
    public function test_tenant_isolation_user_assignment(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX5Y';
        $user = User::factory()->create(['tenant_id' => $otherTenantId]);
        
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $this->service->assignUserToProject(
            $project->id,
            $user->id,
            null,
            $this->tenantId
        );
    }

    /**
     * Test assigning a single team to a project
     */
    public function test_assign_team_to_project(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $team = Team::factory()->create(['tenant_id' => $this->tenantId]);
        
        $this->service->assignTeamToProject(
            $project->id,
            $team->id,
            'contributor',
            $this->tenantId
        );
        
        $this->assertTrue($project->teams()->where('teams.id', $team->id)->exists());
    }

    /**
     * Test assigning multiple teams to a project (bulk)
     */
    public function test_assign_teams_to_project_bulk(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $teams = Team::factory()->count(2)->create(['tenant_id' => $this->tenantId]);
        
        $assignments = $teams->map(function ($team) {
            return [
                'team_id' => $team->id,
                'role' => 'contributor'
            ];
        })->toArray();
        
        $results = $this->service->assignTeamsToProject(
            $project->id,
            $assignments,
            $this->tenantId
        );
        
        $this->assertCount(2, $results['success']);
        $this->assertCount(0, $results['failed']);
    }

    /**
     * Test tenant isolation - cannot assign team from different tenant
     */
    public function test_tenant_isolation_team_assignment(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX5Y';
        $team = Team::factory()->create(['tenant_id' => $otherTenantId]);
        
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $this->service->assignTeamToProject(
            $project->id,
            $team->id,
            'contributor',
            $this->tenantId
        );
    }

    /**
     * Test removing a user from a project
     */
    public function test_remove_user_from_project(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $project->users()->attach($user->id);
        
        $this->service->removeUserFromProject(
            $project->id,
            $user->id,
            $this->tenantId
        );
        
        $this->assertFalse($project->users()->where('users.id', $user->id)->exists());
    }

    /**
     * Test removing a team from a project
     */
    public function test_remove_team_from_project(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $team = Team::factory()->create(['tenant_id' => $this->tenantId]);
        
        $project->teams()->attach($team->id, [
            'role' => 'contributor',
            'joined_at' => now()
        ]);
        
        $this->service->removeTeamFromProject(
            $project->id,
            $team->id,
            $this->tenantId
        );
        
        $pivot = $project->teams()->where('teams.id', $team->id)->first();
        $this->assertNotNull($pivot->pivot->left_at);
    }

    /**
     * Test syncing project users
     */
    public function test_sync_project_users(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $existingUser = User::factory()->create(['tenant_id' => $this->tenantId]);
        $newUser = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $project->users()->attach($existingUser->id);
        
        $assignments = [
            ['user_id' => $existingUser->id],
            ['user_id' => $newUser->id]
        ];
        
        $results = $this->service->syncProjectUsers(
            $project->id,
            $assignments,
            $this->tenantId
        );
        
        $this->assertContains($newUser->id, $results['added']);
        $this->assertContains($existingUser->id, $results['kept']);
    }

    /**
     * Test getting project assignments
     */
    public function test_get_project_assignments(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        $team = Team::factory()->create(['tenant_id' => $this->tenantId]);
        
        $project->users()->attach($user->id);
        $project->teams()->attach($team->id, [
            'role' => 'contributor',
            'joined_at' => now()
        ]);
        
        $assignments = $this->service->getProjectAssignments(
            $project->id,
            $this->tenantId
        );
        
        $this->assertArrayHasKey('users', $assignments);
        $this->assertArrayHasKey('teams', $assignments);
        $this->assertCount(1, $assignments['users']);
        $this->assertCount(1, $assignments['teams']);
    }
}

