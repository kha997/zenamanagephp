<?php declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProjectAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $tenantId;
    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X'; // Test tenant ID
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'email' => 'test@example.com'
        ]);
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
            'owner_id' => $this->user->id
        ]);
        
        Auth::login($this->user);
    }

    /**
     * Test assigning users to a project via API
     */
    public function test_assign_users_endpoint(): void
    {
        $users = User::factory()->count(2)->create(['tenant_id' => $this->tenantId]);
        
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/assignments/users", [
            'users' => $users->map(function ($user) {
                return ['user_id' => $user->id];
            })->toArray()
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test removing a user from a project via API
     */
    public function test_remove_user_endpoint(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        $this->project->users()->attach($user->id);
        
        $response = $this->deleteJson("/api/v1/app/projects/{$this->project->id}/assignments/users/{$user->id}");
        
        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    /**
     * Test assigning teams to a project via API
     */
    public function test_assign_teams_endpoint(): void
    {
        $teams = Team::factory()->count(2)->create(['tenant_id' => $this->tenantId]);
        
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/assignments/teams", [
            'teams' => $teams->map(function ($team) {
                return [
                    'team_id' => $team->id,
                    'role' => 'contributor'
                ];
            })->toArray()
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test removing a team from a project via API
     */
    public function test_remove_team_endpoint(): void
    {
        $team = Team::factory()->create(['tenant_id' => $this->tenantId]);
        $this->project->teams()->attach($team->id, [
            'role' => 'contributor',
            'joined_at' => now()
        ]);
        
        $response = $this->deleteJson("/api/v1/app/projects/{$this->project->id}/assignments/teams/{$team->id}");
        
        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    /**
     * Test getting project assignments via API
     */
    public function test_get_assignments_endpoint(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        $team = Team::factory()->create(['tenant_id' => $this->tenantId]);
        
        $this->project->users()->attach($user->id);
        $this->project->teams()->attach($team->id, [
            'role' => 'contributor',
            'joined_at' => now()
        ]);
        
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/assignments");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data' => [
                    'users',
                    'teams'
                ]
            ]);
    }

    /**
     * Test authorization - unauthorized user cannot assign
     */
    public function test_unauthorized_user_cannot_assign(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenantId
        ]);
        $otherProject = Project::factory()->create([
            'tenant_id' => $this->tenantId,
            'owner_id' => $otherUser->id
        ]);
        
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $response = $this->postJson("/api/v1/app/projects/{$otherProject->id}/assignments/users", [
            'users' => [['user_id' => $user->id]]
        ]);
        
        // Should fail authorization (403) or return error
        $this->assertContains($response->status(), [403, 500]);
    }

    /**
     * Test validation - invalid user_id
     */
    public function test_validation_invalid_user_id(): void
    {
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/assignments/users", [
            'users' => [['user_id' => 'invalid-id']]
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['users.0.user_id']);
    }

    /**
     * Test sync users endpoint
     */
    public function test_sync_users_endpoint(): void
    {
        $users = User::factory()->count(2)->create(['tenant_id' => $this->tenantId]);
        
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/assignments/users/sync", [
            'users' => $users->map(function ($user) {
                return ['user_id' => $user->id];
            })->toArray()
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data' => [
                    'added',
                    'removed',
                    'kept',
                    'total'
                ]
            ]);
    }
}

