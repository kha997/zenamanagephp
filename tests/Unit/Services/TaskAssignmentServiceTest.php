<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TaskAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaskAssignmentService $service;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TaskAssignmentService();
        $this->tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X'; // Test tenant ID
        \App\Models\Tenant::unguard();
        \App\Models\Tenant::firstOrCreate([
            'id' => $this->tenantId,
        ], [
            'name' => 'Task Assignment Test Tenant',
            'slug' => 'task-assignment-test-tenant',
            'status' => 'active',
            'is_active' => true,
        ]);
        \App\Models\Tenant::reguard();
    }

    /**
     * Test assigning a single user to a task
     */
    public function test_assign_user_to_task(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        Auth::login($user);
        
        $assignment = $this->service->assignUserToTask(
            $task->id,
            $user->id,
            $this->tenantId
        );
        
        $this->assertInstanceOf(TaskAssignment::class, $assignment);
        $this->assertEquals($task->id, $assignment->task_id);
        $this->assertEquals($user->id, $assignment->user_id);
        $this->assertEquals(TaskAssignment::TYPE_USER, $assignment->assignment_type);
    }

    /**
     * Test assigning multiple users to a task (bulk)
     */
    public function test_assign_users_to_task_bulk(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $users = User::factory()->count(3)->create(['tenant_id' => $this->tenantId]);
        
        $assignments = $users->map(function ($user) {
            return ['user_id' => $user->id];
        })->toArray();
        
        $results = $this->service->assignUsersToTask(
            $task->id,
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
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX5Y';
        $user = User::factory()->create(['tenant_id' => $otherTenantId]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found or tenant mismatch');
        
        $this->service->assignUserToTask(
            $task->id,
            $user->id,
            $this->tenantId
        );
    }

    /**
     * Test assigning a single team to a task
     */
    public function test_assign_team_to_task(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $team = $this->createTeamWithLead($this->tenantId);
        
        $assignment = $this->service->assignTeamToTask(
            $task->id,
            $team->id,
            $this->tenantId
        );
        
        $this->assertInstanceOf(TaskAssignment::class, $assignment);
        $this->assertEquals($task->id, $assignment->task_id);
        $this->assertEquals($team->id, $assignment->team_id);
        $this->assertEquals(TaskAssignment::TYPE_TEAM, $assignment->assignment_type);
    }

    /**
     * Test assigning multiple teams to a task (bulk)
     */
    public function test_assign_teams_to_task_bulk(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $teams = collect(range(1, 2))->map(fn () => $this->createTeamWithLead($this->tenantId));
        
        $assignments = $teams->map(function ($team) {
            return [
                'team_id' => $team->id,
                'role' => TaskAssignment::ROLE_ASSIGNEE
            ];
        })->toArray();
        
        $results = $this->service->assignTeamsToTask(
            $task->id,
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
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX5Y';
        $team = $this->createTeamWithLead($otherTenantId);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Team not found or tenant mismatch');
        
        $this->service->assignTeamToTask(
            $task->id,
            $team->id,
            $this->tenantId
        );
    }

    /**
     * Test removing a user from a task
     */
    public function test_remove_user_from_task(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $assignment = $this->service->assignUserToTask(
            $task->id,
            $user->id,
            $this->tenantId
        );
        
        $this->service->removeUserFromTask(
            $task->id,
            $user->id,
            $this->tenantId
        );
        
        $this->assertFalse(
            TaskAssignment::where('task_id', $task->id)
                ->where('user_id', $user->id)
                ->where('tenant_id', $this->tenantId)
                ->exists()
        );
    }

    /**
     * Test removing a team from a task
     */
    public function test_remove_team_from_task(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $team = $this->createTeamWithLead($this->tenantId);
        
        $this->service->assignTeamToTask(
            $task->id,
            $team->id,
            $this->tenantId
        );
        
        $this->service->removeTeamFromTask(
            $task->id,
            $team->id,
            $this->tenantId
        );
        
        $this->assertFalse(
            TaskAssignment::where('task_id', $task->id)
                ->where('team_id', $team->id)
                ->where('tenant_id', $this->tenantId)
                ->exists()
        );
    }

    /**
     * Test getting task assignments
     */
    public function test_get_task_assignments(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        $team = $this->createTeamWithLead($this->tenantId);
        
        $this->service->assignUserToTask($task->id, $user->id, $this->tenantId);
        $this->service->assignTeamToTask($task->id, $team->id, $this->tenantId);
        
        $assignments = $this->service->getTaskAssignments(
            $task->id,
            $this->tenantId
        );
        
        $this->assertArrayHasKey('users', $assignments);
        $this->assertArrayHasKey('teams', $assignments);
        $this->assertCount(1, $assignments['users']);
        $this->assertCount(1, $assignments['teams']);
    }

    /**
     * Test getting assignments for a user
     */
    public function test_get_assignments_for_user(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task1 = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $task2 = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $this->service->assignUserToTask($task1->id, $user->id, $this->tenantId);
        $this->service->assignUserToTask($task2->id, $user->id, $this->tenantId);
        
        $assignments = $this->service->getAssignmentsForUser($user->id, $this->tenantId);
        
        $this->assertCount(2, $assignments);
    }

    /**
     * Test getting assignments for a team
     */
    public function test_get_assignments_for_team(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task1 = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $task2 = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $team = $this->createTeamWithLead($this->tenantId);
        
        $this->service->assignTeamToTask($task1->id, $team->id, $this->tenantId);
        $this->service->assignTeamToTask($task2->id, $team->id, $this->tenantId);
        
        $assignments = $this->service->getAssignmentsForTeam($team->id, $this->tenantId);
        
        $this->assertCount(2, $assignments);
    }

    /**
     * Test checking if user is assigned to task
     */
    public function test_is_user_assigned_to_task(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId]);
        
        $this->assertFalse(
            $this->service->isUserAssignedToTask($user->id, $task->id, $this->tenantId)
        );
        
        $this->service->assignUserToTask($task->id, $user->id, $this->tenantId);
        
        $this->assertTrue(
            $this->service->isUserAssignedToTask($user->id, $task->id, $this->tenantId)
        );
    }

    /**
     * Test checking if team is assigned to task
     */
    public function test_is_team_assigned_to_task(): void
    {
        $project = \App\Models\Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id
        ]);
        $team = $this->createTeamWithLead($this->tenantId);
        
        $this->assertFalse(
            $this->service->isTeamAssignedToTask($team->id, $task->id, $this->tenantId)
        );
        
        $this->service->assignTeamToTask($task->id, $team->id, $this->tenantId);
        
        $this->assertTrue(
            $this->service->isTeamAssignedToTask($team->id, $task->id, $this->tenantId)
        );
    }

    private function createTeamWithLead(string $tenantId): Team
    {
        \App\Models\Tenant::unguard();
        \App\Models\Tenant::firstOrCreate(
            ['id' => $tenantId],
            [
                'name' => 'Team Tenant',
                'slug' => 'team-tenant-' . substr($tenantId, 0, 8),
                'status' => 'active',
                'is_active' => true,
            ]
        );
        \App\Models\Tenant::reguard();

        $lead = User::factory()->create(['tenant_id' => $tenantId]);

        return Team::factory()->create([
            'tenant_id' => $tenantId,
            'team_lead_id' => $lead->id,
            'created_by' => $lead->id,
            'updated_by' => $lead->id,
        ]);
    }
}
