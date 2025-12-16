<?php declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TaskAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $tenantId;
    protected User $user;
    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X'; // Test tenant ID
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'email' => 'test@example.com'
        ]);
        $project = \App\Models\Project::factory()->create([
            'tenant_id' => $this->tenantId,
            'owner_id' => $this->user->id
        ]);
        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id,
            'created_by' => $this->user->id
        ]);
        
        Auth::login($this->user);
    }

    /**
     * Test assigning users to a task via API
     */
    public function test_assign_users_endpoint(): void
    {
        $users = User::factory()->count(2)->create(['tenant_id' => $this->tenantId]);
        
        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
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
     * Test removing a user from a task via API
     */
    public function test_remove_user_endpoint(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $service = new \App\Services\TaskAssignmentService();
        $service->assignUserToTask($this->task->id, $user->id, $this->tenantId);
        
        $response = $this->deleteJson("/api/v1/app/tasks/{$this->task->id}/assignments/users/{$user->id}");
        
        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    /**
     * Test assigning teams to a task via API
     */
    public function test_assign_teams_endpoint(): void
    {
        $teams = Team::factory()->count(2)->create(['tenant_id' => $this->tenantId]);
        
        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/teams", [
            'teams' => $teams->map(function ($team) {
                return [
                    'team_id' => $team->id,
                    'role' => TaskAssignment::ROLE_ASSIGNEE
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
     * Test removing a team from a task via API
     */
    public function test_remove_team_endpoint(): void
    {
        $team = Team::factory()->create(['tenant_id' => $this->tenantId]);
        
        $service = new \App\Services\TaskAssignmentService();
        $service->assignTeamToTask($this->task->id, $team->id, $this->tenantId);
        
        $response = $this->deleteJson("/api/v1/app/tasks/{$this->task->id}/assignments/teams/{$team->id}");
        
        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    /**
     * Test getting task assignments via API
     */
    public function test_get_assignments_endpoint(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        $team = Team::factory()->create(['tenant_id' => $this->tenantId]);
        
        $service = new \App\Services\TaskAssignmentService();
        $service->assignUserToTask($this->task->id, $user->id, $this->tenantId);
        $service->assignTeamToTask($this->task->id, $team->id, $this->tenantId);
        
        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/assignments");
        
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
     * Test getting users for a task via API
     */
    public function test_get_users_endpoint(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $service = new \App\Services\TaskAssignmentService();
        $service->assignUserToTask($this->task->id, $user->id, $this->tenantId);
        
        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/assignments/users");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test getting teams for a task via API
     */
    public function test_get_teams_endpoint(): void
    {
        $team = Team::factory()->create(['tenant_id' => $this->tenantId]);
        
        $service = new \App\Services\TaskAssignmentService();
        $service->assignTeamToTask($this->task->id, $team->id, $this->tenantId);
        
        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/assignments/teams");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data'
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
        $otherProject = \App\Models\Project::factory()->create([
            'tenant_id' => $this->tenantId,
            'owner_id' => $otherUser->id
        ]);
        $otherTask = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $otherProject->id,
            'created_by' => $otherUser->id
        ]);
        
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $response = $this->postJson("/api/v1/app/tasks/{$otherTask->id}/assignments/users", [
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
        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
            'users' => [['user_id' => 'invalid-id']]
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['users.0.user_id']);
    }

    /**
     * Test validation - invalid team_id
     */
    public function test_validation_invalid_team_id(): void
    {
        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/teams", [
            'teams' => [['team_id' => 'invalid-id']]
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['teams.0.team_id']);
    }
}

