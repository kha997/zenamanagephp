<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskWatcher;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Team & Collaboration Test
 * 
 * Tests the team and collaboration functionality including:
 * - Team creation and management
 * - Team member management
 * - Task assignments to teams and users
 * - Project team collaboration
 * - Task watching and notifications
 * - Team-based permissions and access control
 * - Multi-tenant team isolation
 */
class TeamCollaborationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $project;
    protected $team;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active'
        ]);

        $this->user = User::factory()->create([
            'name' => 'Team Manager',
            'email' => 'manager@test-' . uniqid() . '.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEAM-' . uniqid(),
            'name' => 'Team Collaboration Project',
            'description' => 'Test project for team collaboration',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);

        $this->team = Team::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Development Team',
            'description' => 'Software development team',
            'department' => 'Engineering',
            'team_lead_id' => $this->user->id,
            'created_by' => $this->user->id
        ]);
    }

    /**
     * Test team creation and basic management
     */
    public function test_can_create_and_manage_teams(): void
    {
        // Test team creation
        $this->assertDatabaseHas('teams', [
            'id' => $this->team->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Development Team',
            'department' => 'Engineering',
            'team_lead_id' => $this->user->id,
            'is_active' => true
        ]);

        // Test team relationships
        $this->assertEquals($this->tenant->id, $this->team->tenant->id);
        $this->assertEquals($this->user->id, $this->team->teamLead->id);
        $this->assertEquals($this->user->id, $this->team->creator->id);

        // Test team update
        $this->team->update([
            'description' => 'Updated description',
            'department' => 'Product Development'
        ]);

        $this->assertEquals('Updated description', $this->team->fresh()->description);
        $this->assertEquals('Product Development', $this->team->fresh()->department);
    }

    /**
     * Test team member management
     */
    public function test_can_manage_team_members(): void
    {
        // Create additional users
        $member1 = User::factory()->create([
            'name' => 'Developer 1',
            'email' => 'dev1@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $member2 = User::factory()->create([
            'name' => 'Developer 2',
            'email' => 'dev2@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        // Add members to team
        $this->team->addMember($member1->id, Team::ROLE_MEMBER);
        $this->team->addMember($member2->id, Team::ROLE_ADMIN);

        // Test member relationships
        $this->assertTrue($this->team->hasMember($member1->id));
        $this->assertTrue($this->team->hasMember($member2->id));
        $this->assertTrue($this->team->hasLeader($member2->id));
        $this->assertFalse($this->team->hasLeader($member1->id));

        // Test team size
        $this->assertEquals(2, $this->team->size);

        // Test member roles
        $members = $this->team->activeMembers;
        $this->assertCount(2, $members);
        
        $adminMember = $members->where('id', $member2->id)->first();
        $this->assertEquals(Team::ROLE_ADMIN, $adminMember->pivot->role);

        // Test role update
        $this->team->updateMemberRole($member1->id, Team::ROLE_LEAD);
        $this->assertTrue($this->team->hasLeader($member1->id));

        // Test member removal
        $this->team->removeMember($member1->id);
        $this->assertFalse($this->team->hasMember($member1->id));
        $this->assertEquals(1, $this->team->fresh()->size);
    }

    /**
     * Test task assignments to teams and users
     */
    public function test_can_assign_tasks_to_teams_and_users(): void
    {
        // Create users and tasks
        $user1 = User::factory()->create([
            'name' => 'User 1',
            'email' => 'user1@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $user2 = User::factory()->create([
            'name' => 'User 2',
            'email' => 'user2@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task 1',
            'description' => 'Individual task',
            'status' => 'open',
            'assigned_to' => $user1->id,
            'created_by' => $this->user->id
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Task 2',
            'description' => 'Team task',
            'status' => 'open',
            'created_by' => $this->user->id
        ]);

        // Add users to team
        $this->team->addMember($user1->id);
        $this->team->addMember($user2->id);

        // Create task assignments
        $userAssignment = TaskAssignment::create([
            'task_id' => $task1->id,
            'user_id' => $user1->id,
            'assignment_type' => TaskAssignment::TYPE_USER,
            'role' => TaskAssignment::ROLE_ASSIGNEE,
            'assigned_hours' => 8.0,
            'status' => TaskAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'created_by' => $this->user->id
        ]);

        $teamAssignment = TaskAssignment::create([
            'task_id' => $task2->id,
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'assignment_type' => TaskAssignment::TYPE_TEAM,
            'role' => TaskAssignment::ROLE_ASSIGNEE,
            'assigned_hours' => 16.0,
            'status' => TaskAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'created_by' => $this->user->id
        ]);

        // Test assignments
        $this->assertDatabaseHas('task_assignments', [
            'id' => $userAssignment->id,
            'task_id' => $task1->id,
            'user_id' => $user1->id,
            'assignment_type' => TaskAssignment::TYPE_USER,
            'role' => TaskAssignment::ROLE_ASSIGNEE
        ]);

        $this->assertDatabaseHas('task_assignments', [
            'id' => $teamAssignment->id,
            'task_id' => $task2->id,
            'team_id' => $this->team->id,
            'assignment_type' => TaskAssignment::TYPE_TEAM,
            'role' => TaskAssignment::ROLE_ASSIGNEE
        ]);

        // Test assignment relationships
        $this->assertEquals($task1->id, $userAssignment->task->id);
        $this->assertEquals($user1->id, $userAssignment->user->id);
        $this->assertEquals($task2->id, $teamAssignment->task->id);
        $this->assertEquals($this->team->id, $teamAssignment->team->id);

        // Test assignment types
        $this->assertTrue($userAssignment->isUserAssignment());
        $this->assertFalse($userAssignment->isTeamAssignment());
        $this->assertFalse($teamAssignment->isUserAssignment());
        $this->assertTrue($teamAssignment->isTeamAssignment());

        // Test assignee names
        $this->assertEquals('User 1', $userAssignment->assignee_name);
        $this->assertEquals('Development Team', $teamAssignment->assignee_name);
    }

    /**
     * Test task assignment status management
     */
    public function test_can_manage_task_assignment_status(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Status Test Task',
            'status' => 'open',
            'created_by' => $this->user->id
        ]);

        $assignment = TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'assignment_type' => TaskAssignment::TYPE_USER,
            'role' => TaskAssignment::ROLE_ASSIGNEE,
            'assigned_hours' => 8.0,
            'status' => TaskAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'created_by' => $this->user->id
        ]);

        // Test status progression
        $this->assertEquals(TaskAssignment::STATUS_ASSIGNED, $assignment->status);
        $this->assertNull($assignment->started_at);
        $this->assertNull($assignment->completed_at);

        // Mark as started
        $assignment->markAsStarted();
        $this->assertEquals(TaskAssignment::STATUS_IN_PROGRESS, $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->started_at);

        // Mark as completed
        $assignment->markAsCompleted();
        $this->assertEquals(TaskAssignment::STATUS_COMPLETED, $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->completed_at);

        // Test hours tracking
        $assignment->updateActualHours(10.0);
        $this->assertEquals(10.0, $assignment->fresh()->actual_hours);
        $this->assertEquals(-2.0, $assignment->fresh()->remaining_hours); // 8 - 10 = -2
    }

    /**
     * Test project team collaboration
     */
    public function test_can_manage_project_team_collaboration(): void
    {
        // Create additional team
        $qaTeam = Team::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'QA Team',
            'description' => 'Quality assurance team',
            'department' => 'Quality',
            'created_by' => $this->user->id
        ]);

        // Add teams to project
        $this->project->teams()->attach($this->team->id, [
            'role' => 'contributor',
            'joined_at' => now()
        ]);

        $this->project->teams()->attach($qaTeam->id, [
            'role' => 'reviewer',
            'joined_at' => now()
        ]);

        // Test project-team relationships
        $projectTeams = $this->project->teams;
        $this->assertCount(2, $projectTeams);

        $devTeamPivot = $projectTeams->where('id', $this->team->id)->first();
        $qaTeamPivot = $projectTeams->where('id', $qaTeam->id)->first();

        $this->assertEquals('contributor', $devTeamPivot->pivot->role);
        $this->assertEquals('reviewer', $qaTeamPivot->pivot->role);

        // Test team-project relationships
        $teamProjects = $this->team->projects;
        $this->assertCount(1, $teamProjects);
        $this->assertEquals($this->project->id, $teamProjects->first()->id);

        // Test project team statistics
        $this->assertDatabaseHas('project_teams', [
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'role' => 'contributor'
        ]);

        $this->assertDatabaseHas('project_teams', [
            'project_id' => $this->project->id,
            'team_id' => $qaTeam->id,
            'role' => 'reviewer'
        ]);
    }

    /**
     * Test task watching functionality
     */
    public function test_can_manage_task_watching(): void
    {
        $watcher1 = User::factory()->create([
            'name' => 'Watcher 1',
            'email' => 'watcher1@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $watcher2 = User::factory()->create([
            'name' => 'Watcher 2',
            'email' => 'watcher2@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Watched Task',
            'status' => 'open',
            'created_by' => $this->user->id
        ]);

        // Add watchers to task
        TaskWatcher::create([
            'task_id' => $task->id,
            'user_id' => $watcher1->id,
        ]);

        TaskWatcher::create([
            'task_id' => $task->id,
            'user_id' => $watcher2->id,
        ]);

        // Test watcher relationships
        $this->assertDatabaseHas('task_watchers', [
            'task_id' => $task->id,
            'user_id' => $watcher1->id
        ]);

        $this->assertDatabaseHas('task_watchers', [
            'task_id' => $task->id,
            'user_id' => $watcher2->id
        ]);

        // Test unique constraint
        $this->expectException(\Exception::class);
        DB::table('task_watchers')->insert([
            'id' => \Illuminate\Support\Str::ulid(),
            'task_id' => $task->id,
            'user_id' => $watcher1->id, // Duplicate
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Test team-based permissions and access control
     */
    public function test_can_enforce_team_based_permissions(): void
    {
        $teamMember = User::factory()->create([
            'name' => 'Team Member',
            'email' => 'member@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $nonMember = User::factory()->create([
            'name' => 'Non Member',
            'email' => 'nonmember@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        // Add member to team
        $this->team->addMember($teamMember->id, Team::ROLE_MEMBER);

        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Team Task',
            'status' => 'open',
            'created_by' => $this->user->id
        ]);

        // Assign task to team
        $assignment = TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'assignment_type' => TaskAssignment::TYPE_TEAM,
            'role' => TaskAssignment::ROLE_ASSIGNEE,
            'status' => TaskAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'created_by' => $this->user->id
        ]);

        // Test team member can access task
        $teamAssignments = TaskAssignment::forTeam($this->team->id)->get();
        $this->assertCount(1, $teamAssignments);
        $this->assertEquals($task->id, $teamAssignments->first()->task_id);

        // Test non-member cannot access team tasks
        $nonMemberAssignments = TaskAssignment::forUser($nonMember->id)->get();
        $this->assertCount(0, $nonMemberAssignments);

        // Test team member assignments
        $memberAssignments = TaskAssignment::forUser($teamMember->id)->get();
        $this->assertCount(0, $memberAssignments); // Direct assignments only
    }

    /**
     * Test team statistics and analytics
     */
    public function test_can_generate_team_statistics(): void
    {
        // Create team members
        $members = [];
        for ($i = 1; $i <= 5; $i++) {
            $members[] = User::factory()->create([
                'name' => "Member {$i}",
                'email' => "member{$i}@test.com",
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Add members to team with different roles
        $this->team->addMember($members[0]->id, Team::ROLE_ADMIN);
        $this->team->addMember($members[1]->id, Team::ROLE_LEAD);
        $this->team->addMember($members[2]->id, Team::ROLE_MEMBER);
        $this->team->addMember($members[3]->id, Team::ROLE_MEMBER);
        $this->team->addMember($members[4]->id, Team::ROLE_MEMBER);

        // Create tasks and assignments
        $tasks = [];
        for ($i = 1; $i <= 3; $i++) {
            $tasks[] = Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Task {$i}",
                'status' => $i === 3 ? 'completed' : 'in_progress',
                'created_by' => $this->user->id
            ]);
        }

        // Create assignments
        TaskAssignment::create([
            'task_id' => $tasks[0]->id,
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'assignment_type' => TaskAssignment::TYPE_TEAM,
            'role' => TaskAssignment::ROLE_ASSIGNEE,
            'assigned_hours' => 8.0,
            'actual_hours' => 6.0,
            'status' => TaskAssignment::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
            'created_by' => $this->user->id
        ]);

        TaskAssignment::create([
            'task_id' => $tasks[1]->id,
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'assignment_type' => TaskAssignment::TYPE_TEAM,
            'role' => TaskAssignment::ROLE_ASSIGNEE,
            'assigned_hours' => 12.0,
            'actual_hours' => 12.0,
            'status' => TaskAssignment::STATUS_COMPLETED,
            'assigned_at' => now(),
            'created_by' => $this->user->id
        ]);

        // Test team statistics
        $stats = $this->team->getStatistics();
        
        $this->assertEquals(5, $stats['total_members']);
        $this->assertEquals(2, $stats['leaders']);
        $this->assertEquals(3, $stats['members']);

        // Test team task statistics
        $teamAssignments = $this->team->taskAssignments;
        $this->assertCount(2, $teamAssignments);

        $activeAssignments = TaskAssignment::forTeam($this->team->id)->active()->get();
        $this->assertCount(1, $activeAssignments);

        $completedAssignments = TaskAssignment::forTeam($this->team->id)->byStatus(TaskAssignment::STATUS_COMPLETED)->get();
        $this->assertCount(1, $completedAssignments);
    }

    /**
     * Test multi-tenant team isolation
     */
    public function test_team_data_is_tenant_isolated(): void
    {
        // Create another tenant
        $tenant2 = Tenant::factory()->create([
            'name' => 'Another Company',
            'slug' => 'another-company',
            'status' => 'active'
        ]);

        $user2 = User::factory()->create([
            'name' => 'User 2',
            'email' => 'user2@another.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant2->id
        ]);

        $team2 = Team::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Another Team',
            'description' => 'Team in another tenant',
            'created_by' => $user2->id
        ]);

        // Test tenant isolation
        $tenant1Teams = Team::forTenant($this->tenant->id)->get();
        $tenant2Teams = Team::forTenant($tenant2->id)->get();

        $this->assertCount(1, $tenant1Teams);
        $this->assertCount(1, $tenant2Teams);
        $this->assertEquals($this->team->id, $tenant1Teams->first()->id);
        $this->assertEquals($team2->id, $tenant2Teams->first()->id);

        // Test cross-tenant access prevention
        $this->assertDatabaseHas('teams', [
            'id' => $this->team->id,
            'tenant_id' => $this->tenant->id
        ]);

        $this->assertDatabaseHas('teams', [
            'id' => $team2->id,
            'tenant_id' => $tenant2->id
        ]);

        // Test team member isolation
        $this->team->addMember($this->user->id);
        $team2->addMember($user2->id);

        $tenant1Members = $this->team->activeMembers;
        $tenant2Members = $team2->activeMembers;

        $this->assertCount(1, $tenant1Members);
        $this->assertCount(1, $tenant2Members);
        $this->assertEquals($this->user->id, $tenant1Members->first()->id);
        $this->assertEquals($user2->id, $tenant2Members->first()->id);
    }

    /**
     * Test team search and filtering
     */
    public function test_can_search_and_filter_teams(): void
    {
        // Create additional teams
        $qaTeam = Team::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Quality Assurance Team',
            'description' => 'QA and testing team',
            'department' => 'Quality',
            'created_by' => $this->user->id
        ]);

        $designTeam = Team::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Design Team',
            'description' => 'UI/UX design team',
            'department' => 'Design',
            'created_by' => $this->user->id
        ]);

        // Test search functionality
        $searchResults = Team::forTenant($this->tenant->id)->search('Development')->get();
        $this->assertCount(1, $searchResults);
        $this->assertEquals($this->team->id, $searchResults->first()->id);

        $searchResults = Team::forTenant($this->tenant->id)->search('Team')->get();
        $this->assertCount(3, $searchResults);

        // Test department filtering
        $engineeringTeams = Team::forTenant($this->tenant->id)->byDepartment('Engineering')->get();
        $this->assertCount(1, $engineeringTeams);
        $this->assertEquals($this->team->id, $engineeringTeams->first()->id);

        $qualityTeams = Team::forTenant($this->tenant->id)->byDepartment('Quality')->get();
        $this->assertCount(1, $qualityTeams);
        $this->assertEquals($qaTeam->id, $qualityTeams->first()->id);

        // Test active teams filtering
        $activeTeams = Team::forTenant($this->tenant->id)->active()->get();
        $this->assertCount(3, $activeTeams);

        // Deactivate a team
        $qaTeam->update(['is_active' => false]);
        $activeTeams = Team::forTenant($this->tenant->id)->active()->get();
        $this->assertCount(2, $activeTeams);
    }

    /**
     * Test team collaboration workflow end-to-end
     */
    public function test_team_collaboration_workflow_end_to_end(): void
    {
        // Create team members
        $developer = User::factory()->create([
            'name' => 'Developer',
            'email' => 'dev@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $reviewer = User::factory()->create([
            'name' => 'Reviewer',
            'email' => 'reviewer@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        // Add members to team
        $this->team->addMember($developer->id, Team::ROLE_MEMBER);
        $this->team->addMember($reviewer->id, Team::ROLE_LEAD);

        // Add team to project
        $this->project->teams()->attach($this->team->id, [
            'role' => 'contributor',
            'joined_at' => now()
        ]);

        // Create task
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Collaborative Task',
            'description' => 'Task requiring team collaboration',
            'status' => 'open',
            'created_by' => $this->user->id
        ]);

        // Assign task to team
        $teamAssignment = TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'assignment_type' => TaskAssignment::TYPE_TEAM,
            'role' => TaskAssignment::ROLE_ASSIGNEE,
            'assigned_hours' => 16.0,
            'status' => TaskAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'created_by' => $this->user->id
        ]);

        // Assign individual reviewer
        $reviewerAssignment = TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $reviewer->id,
            'assignment_type' => TaskAssignment::TYPE_USER,
            'role' => TaskAssignment::ROLE_REVIEWER,
            'assigned_hours' => 4.0,
            'status' => TaskAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'created_by' => $this->user->id
        ]);

        // Add watchers
        TaskWatcher::create([
            'task_id' => $task->id,
            'user_id' => $this->user->id,
        ]);

        // Test complete workflow
        $this->assertDatabaseHas('teams', ['id' => $this->team->id]);
        $this->assertDatabaseHas('project_teams', [
            'project_id' => $this->project->id,
            'team_id' => $this->team->id
        ]);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'team_id' => $this->team->id
        ]);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'user_id' => $reviewer->id,
            'role' => TaskAssignment::ROLE_REVIEWER
        ]);
        $this->assertDatabaseHas('task_watchers', [
            'task_id' => $task->id,
            'user_id' => $this->user->id
        ]);

        // Test team can work on task
        $teamAssignment->markAsStarted();
        $this->assertEquals(TaskAssignment::STATUS_IN_PROGRESS, $teamAssignment->fresh()->status);

        // Test reviewer can review
        $reviewerAssignment->markAsStarted();
        $this->assertEquals(TaskAssignment::STATUS_IN_PROGRESS, $reviewerAssignment->fresh()->status);

        // Complete assignments
        $teamAssignment->markAsCompleted();
        $reviewerAssignment->markAsCompleted();

        $this->assertEquals(TaskAssignment::STATUS_COMPLETED, $teamAssignment->fresh()->status);
        $this->assertEquals(TaskAssignment::STATUS_COMPLETED, $reviewerAssignment->fresh()->status);

        // Test workflow completion
        $completedAssignments = TaskAssignment::byTask($task->id)->byStatus(TaskAssignment::STATUS_COMPLETED)->get();
        $this->assertCount(2, $completedAssignments);
    }
}
