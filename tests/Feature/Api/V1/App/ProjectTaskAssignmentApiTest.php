<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectActivity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * ProjectTask Assignment API Test
 * 
 * Round 213: Test task assignment and my-tasks endpoints
 * 
 * @group project-tasks
 * @group api-v1
 * @group assignment
 */
class ProjectTaskAssignmentApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected User $userC; // User in tenant A
    protected Project $projectA;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(202001);
        $this->setDomainName('project-task-assignment');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        $this->userC = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'member',
        ]);

        // Attach users to tenants via pivot table
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        $this->userC->tenants()->attach($this->tenantA->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        // Refresh users
        $this->userA->refresh();
        $this->userB->refresh();
        $this->userC->refresh();

        // Get resolved tenant ID for userA
        $tenancyService = app(\App\Services\TenancyService::class);
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($this->userA, request());

        // Create project
        $this->projectA = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Test Project',
            'code' => 'PRJ-TEST-001',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);
    }

    /**
     * Test assigning task to user in same tenant
     */
    public function test_it_assigns_task_to_user_in_same_tenant(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Task',
            'sort_order' => 1,
        ]);

        // Update task with assignee_id
        $response = $this->patchJson("/api/v1/app/projects/{$this->projectA->id}/tasks/{$task->id}", [
            'assignee_id' => $this->userC->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'assignee_id',
            ],
        ]);

        // Assert DB has assignee_id set
        $task->refresh();
        $this->assertEquals($this->userC->id, $task->assignee_id);

        // Assert response returns assignee correctly
        $response->assertJson([
            'data' => [
                'assignee_id' => $this->userC->id,
            ],
        ]);
    }

    /**
     * Test unassigning task
     */
    public function test_it_unassigns_task(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task with assignee
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
        ]);

        // Update task to unassign
        $response = $this->patchJson("/api/v1/app/projects/{$this->projectA->id}/tasks/{$task->id}", [
            'assignee_id' => null,
        ]);

        $response->assertStatus(200);

        // Assert DB has assignee_id = null
        $task->refresh();
        $this->assertNull($task->assignee_id);
    }

    /**
     * Test cannot assign cross-tenant
     */
    public function test_it_cannot_assign_cross_tenant(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Task',
            'sort_order' => 1,
        ]);

        // Try to assign user from another tenant
        $response = $this->patchJson("/api/v1/app/projects/{$this->projectA->id}/tasks/{$task->id}", [
            'assignee_id' => $this->userB->id, // User from tenant B
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assignee_id']);
    }

    /**
     * Test my tasks returns only current user's tasks
     */
    public function test_my_tasks_returns_only_current_user_tasks(): void
    {
        Sanctum::actingAs($this->userC, [], 'sanctum');

        // Create tasks
        $taskA = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Task A - Assigned to userC',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
        ]);

        $taskB = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Task B - Assigned to userA',
            'sort_order' => 2,
            'assignee_id' => $this->userA->id,
        ]);

        $taskC = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Task C - Unassigned',
            'sort_order' => 3,
            'assignee_id' => null,
        ]);

        // Call my-tasks endpoint as userC
        $response = $this->getJson('/api/v1/app/my/tasks');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'assignee_id',
                ],
            ],
        ]);

        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();

        // Only taskA should appear (assigned to userC)
        $this->assertContains($taskA->id, $taskIds);
        $this->assertNotContains($taskB->id, $taskIds);
        $this->assertNotContains($taskC->id, $taskIds);
    }

    /**
     * Test my tasks respects status filter
     */
    public function test_my_tasks_respects_status_filter(): void
    {
        Sanctum::actingAs($this->userC, [], 'sanctum');

        // Create completed and open tasks
        $completedTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Completed Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
            'is_completed' => true,
        ]);

        $openTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Open Task',
            'sort_order' => 2,
            'assignee_id' => $this->userC->id,
            'is_completed' => false,
        ]);

        // Test status=open filter
        $response = $this->getJson('/api/v1/app/my/tasks?status=open');
        $response->assertStatus(200);
        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();
        $this->assertContains($openTask->id, $taskIds);
        $this->assertNotContains($completedTask->id, $taskIds);

        // Test status=completed filter
        $response = $this->getJson('/api/v1/app/my/tasks?status=completed');
        $response->assertStatus(200);
        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();
        $this->assertContains($completedTask->id, $taskIds);
        $this->assertNotContains($openTask->id, $taskIds);

        // Test status=all filter
        $response = $this->getJson('/api/v1/app/my/tasks?status=all');
        $response->assertStatus(200);
        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();
        $this->assertContains($openTask->id, $taskIds);
        $this->assertContains($completedTask->id, $taskIds);
    }

    /**
     * Test logs assignment from null to user
     * 
     * Round 214: Assignment history logging
     */
    public function test_it_logs_assignment_from_null_to_user(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task without assignee
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Task',
            'sort_order' => 1,
            'assignee_id' => null,
        ]);

        // Assign task to userC
        $response = $this->patchJson("/api/v1/app/projects/{$this->projectA->id}/tasks/{$task->id}", [
            'assignee_id' => $this->userC->id,
        ]);

        $response->assertStatus(200);

        // Assert ProjectActivity was created
        $activity = ProjectActivity::where('project_id', $this->projectA->id)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASK_ASSIGNED)
            ->where('entity_id', $task->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->tenantA->id, $activity->tenant_id);
        $this->assertNull($activity->metadata['old_assignee_id']);
        $this->assertEquals($this->userC->id, $activity->metadata['new_assignee_id']);
        $this->assertEquals($this->userC->name, $activity->metadata['new_assignee_name']);
        $this->assertEquals($task->id, $activity->metadata['task_id']);
        $this->assertEquals($task->name, $activity->metadata['task_name']);
    }

    /**
     * Test logs unassignment from user to null
     * 
     * Round 214: Assignment history logging
     */
    public function test_it_logs_unassignment_from_user_to_null(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task with assignee
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
        ]);

        // Unassign task
        $response = $this->patchJson("/api/v1/app/projects/{$this->projectA->id}/tasks/{$task->id}", [
            'assignee_id' => null,
        ]);

        $response->assertStatus(200);

        // Assert ProjectActivity was created
        $activity = ProjectActivity::where('project_id', $this->projectA->id)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASK_UNASSIGNED)
            ->where('entity_id', $task->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->tenantA->id, $activity->tenant_id);
        $this->assertEquals($this->userC->id, $activity->metadata['old_assignee_id']);
        $this->assertEquals($this->userC->name, $activity->metadata['old_assignee_name']);
        $this->assertNull($activity->metadata['new_assignee_id']);
        $this->assertNull($activity->metadata['new_assignee_name']);
    }

    /**
     * Test logs reassignment from user A to user B
     * 
     * Round 214: Assignment history logging
     */
    public function test_it_logs_reassignment_from_user_a_to_user_b(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task assigned to userC
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
        ]);

        // Reassign to userA
        $response = $this->patchJson("/api/v1/app/projects/{$this->projectA->id}/tasks/{$task->id}", [
            'assignee_id' => $this->userA->id,
        ]);

        $response->assertStatus(200);

        // Assert ProjectActivity was created
        $activity = ProjectActivity::where('project_id', $this->projectA->id)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASK_REASSIGNED)
            ->where('entity_id', $task->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->tenantA->id, $activity->tenant_id);
        $this->assertEquals($this->userC->id, $activity->metadata['old_assignee_id']);
        $this->assertEquals($this->userC->name, $activity->metadata['old_assignee_name']);
        $this->assertEquals($this->userA->id, $activity->metadata['new_assignee_id']);
        $this->assertEquals($this->userA->name, $activity->metadata['new_assignee_name']);
    }

    /**
     * Test does NOT log when assignee unchanged
     * 
     * Round 214: Assignment history logging
     */
    public function test_it_does_not_log_when_assignee_unchanged(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task assigned to userC
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
        ]);

        // Count activities before update
        $activitiesBefore = ProjectActivity::where('project_id', $this->projectA->id)
            ->whereIn('action', [
                ProjectActivity::ACTION_PROJECT_TASK_ASSIGNED,
                ProjectActivity::ACTION_PROJECT_TASK_UNASSIGNED,
                ProjectActivity::ACTION_PROJECT_TASK_REASSIGNED,
            ])
            ->count();

        // Update task with same assignee_id
        $response = $this->patchJson("/api/v1/app/projects/{$this->projectA->id}/tasks/{$task->id}", [
            'assignee_id' => $this->userC->id, // Same assignee
            'name' => 'Updated Task Name', // Update other field
        ]);

        $response->assertStatus(200);

        // Assert no new assignment-related activity was created
        $activitiesAfter = ProjectActivity::where('project_id', $this->projectA->id)
            ->whereIn('action', [
                ProjectActivity::ACTION_PROJECT_TASK_ASSIGNED,
                ProjectActivity::ACTION_PROJECT_TASK_UNASSIGNED,
                ProjectActivity::ACTION_PROJECT_TASK_REASSIGNED,
            ])
            ->count();

        $this->assertEquals($activitiesBefore, $activitiesAfter);
    }

    /**
     * Test multi-tenant safety for assignment history
     * 
     * Round 214: Assignment history logging
     */
    public function test_assignment_history_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task in tenant A
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Task',
            'sort_order' => 1,
            'assignee_id' => null,
        ]);

        // Assign task
        $response = $this->patchJson("/api/v1/app/projects/{$this->projectA->id}/tasks/{$task->id}", [
            'assignee_id' => $this->userC->id,
        ]);

        $response->assertStatus(200);

        // Assert activity has correct tenant_id
        $activity = ProjectActivity::where('project_id', $this->projectA->id)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASK_ASSIGNED)
            ->where('entity_id', $task->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->tenantA->id, $activity->tenant_id);
        $this->assertEquals($this->projectA->id, $activity->project_id);
    }

    /**
     * Test my tasks respects range filter - overdue
     * 
     * Round 217: Date range filtering
     */
    public function test_my_tasks_respects_range_filter_overdue(): void
    {
        Sanctum::actingAs($this->userC, [], 'sanctum');

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        // Create overdue task (due yesterday, not completed)
        $overdueTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Overdue Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
            'due_date' => $yesterday,
            'is_completed' => false,
        ]);

        // Create future task (due tomorrow)
        $futureTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Future Task',
            'sort_order' => 2,
            'assignee_id' => $this->userC->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
        ]);

        // Create completed overdue task (should not appear in overdue filter)
        $completedOverdueTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Completed Overdue Task',
            'sort_order' => 3,
            'assignee_id' => $this->userC->id,
            'due_date' => $yesterday,
            'is_completed' => true,
        ]);

        // Test range=overdue filter
        $response = $this->getJson('/api/v1/app/my/tasks?range=overdue');
        $response->assertStatus(200);
        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();
        
        $this->assertContains($overdueTask->id, $taskIds);
        $this->assertNotContains($futureTask->id, $taskIds);
        $this->assertNotContains($completedOverdueTask->id, $taskIds);
    }

    /**
     * Test my tasks respects range filter - today
     * 
     * Round 217: Date range filtering
     */
    public function test_my_tasks_respects_range_filter_today(): void
    {
        Sanctum::actingAs($this->userC, [], 'sanctum');

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        // Create task due today
        $todayTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Today Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
            'due_date' => $today,
            'is_completed' => false,
        ]);

        // Create task due tomorrow
        $tomorrowTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Tomorrow Task',
            'sort_order' => 2,
            'assignee_id' => $this->userC->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
        ]);

        // Test range=today filter
        $response = $this->getJson('/api/v1/app/my/tasks?range=today');
        $response->assertStatus(200);
        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();
        
        $this->assertContains($todayTask->id, $taskIds);
        $this->assertNotContains($tomorrowTask->id, $taskIds);
    }

    /**
     * Test my tasks respects range filter - next_7_days
     * 
     * Round 217: Date range filtering
     */
    public function test_my_tasks_respects_range_filter_next_7_days(): void
    {
        Sanctum::actingAs($this->userC, [], 'sanctum');

        $today = now()->toDateString();
        $in5Days = now()->addDays(5)->toDateString();
        $in8Days = now()->addDays(8)->toDateString();

        // Create task due in 5 days (within range)
        $withinRangeTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Within Range Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
            'due_date' => $in5Days,
            'is_completed' => false,
        ]);

        // Create task due in 8 days (outside range)
        $outsideRangeTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Outside Range Task',
            'sort_order' => 2,
            'assignee_id' => $this->userC->id,
            'due_date' => $in8Days,
            'is_completed' => false,
        ]);

        // Create task due today (within range)
        $todayTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Today Task',
            'sort_order' => 3,
            'assignee_id' => $this->userC->id,
            'due_date' => $today,
            'is_completed' => false,
        ]);

        // Test range=next_7_days filter
        $response = $this->getJson('/api/v1/app/my/tasks?range=next_7_days');
        $response->assertStatus(200);
        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();
        
        $this->assertContains($withinRangeTask->id, $taskIds);
        $this->assertContains($todayTask->id, $taskIds);
        $this->assertNotContains($outsideRangeTask->id, $taskIds);
    }

    /**
     * Test my tasks combines status and range filters
     * 
     * Round 217: Combined filtering
     * 
     * Note: range=overdue inherently filters for is_completed=false, so it only works with status=open
     */
    public function test_my_tasks_combines_status_and_range_filters(): void
    {
        Sanctum::actingAs($this->userC, [], 'sanctum');

        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        // Create completed overdue task
        $completedOverdueTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Completed Overdue Task',
            'sort_order' => 1,
            'assignee_id' => $this->userC->id,
            'due_date' => $yesterday,
            'is_completed' => true,
        ]);

        // Create open overdue task
        $openOverdueTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Open Overdue Task',
            'sort_order' => 2,
            'assignee_id' => $this->userC->id,
            'due_date' => $yesterday,
            'is_completed' => false,
        ]);

        // Create completed task due today
        $completedTodayTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Completed Today Task',
            'sort_order' => 3,
            'assignee_id' => $this->userC->id,
            'due_date' => $today,
            'is_completed' => true,
        ]);

        // Create open task due today
        $openTodayTask = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Open Today Task',
            'sort_order' => 4,
            'assignee_id' => $this->userC->id,
            'due_date' => $today,
            'is_completed' => false,
        ]);

        // Test status=open&range=overdue (should return only open overdue tasks)
        // Note: range=overdue requires is_completed=false, so it only works with status=open
        $response = $this->getJson('/api/v1/app/my/tasks?status=open&range=overdue');
        $response->assertStatus(200);
        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();
        
        $this->assertContains($openOverdueTask->id, $taskIds);
        $this->assertNotContains($completedOverdueTask->id, $taskIds);
        $this->assertNotContains($openTodayTask->id, $taskIds);
        $this->assertNotContains($completedTodayTask->id, $taskIds);

        // Test status=completed&range=today (should return completed tasks due today)
        $response = $this->getJson('/api/v1/app/my/tasks?status=completed&range=today');
        $response->assertStatus(200);
        $data = $response->json('data');
        $taskIds = collect($data)->pluck('id')->toArray();
        
        $this->assertContains($completedTodayTask->id, $taskIds);
        $this->assertNotContains($openTodayTask->id, $taskIds);
        $this->assertNotContains($completedOverdueTask->id, $taskIds);
        $this->assertNotContains($openOverdueTask->id, $taskIds);
    }
}

