<?php declare(strict_types=1);

namespace Tests\Feature\Api\Projects;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Enums\TaskStatus;
use App\Services\ProjectStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Project Status Transition Tests
 * 
 * Tests for project status transitions and business rules
 * 
 * Uses DomainTestIsolation for reproducible test data
 */
class ProjectStatusTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenant;
    protected User $user;
    protected ProjectStatusTransitionService $transitionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        $this->setDomainName('projects');
        $this->setupDomainIsolation();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'pm',
        ]);

        $this->transitionService = app(ProjectStatusTransitionService::class);

        Sanctum::actingAs($this->user, [], 'sanctum');
    }

    /**
     * Test active → completed → archived (happy path)
     */
    public function test_active_to_completed_to_archived(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_ACTIVE,
        ]);

        // Step 1: active → completed
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_COMPLETED);
        $this->assertTrue($validation->isValid);
        
        $project->update(['status' => Project::STATUS_COMPLETED]);
        $this->assertEquals(Project::STATUS_COMPLETED, $project->fresh()->status);

        // Step 2: completed → archived
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_ARCHIVED);
        $this->assertTrue($validation->isValid);
        
        $project->update(['status' => Project::STATUS_ARCHIVED]);
        $this->assertEquals(Project::STATUS_ARCHIVED, $project->fresh()->status);

        // Step 3: Verify archived is terminal (cannot transition from it)
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_ACTIVE);
        $this->assertFalse($validation->isValid);
        $this->assertEquals('invalid_transition', $validation->errorCode);
    }

    /**
     * Test archive project with in_progress tasks (should be allowed if project is completed)
     */
    public function test_archive_completed_project_with_tasks(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_COMPLETED,
        ]);

        // Create tasks (even in_progress tasks)
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        // Should be able to archive completed project even with tasks
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_ARCHIVED);
        $this->assertTrue($validation->isValid);
        
        $project->update(['status' => Project::STATUS_ARCHIVED]);
        $this->assertEquals(Project::STATUS_ARCHIVED, $project->fresh()->status);
    }

    /**
     * Test planning → completed with unfinished tasks (should be blocked)
     */
    public function test_planning_to_completed_with_unfinished_tasks_blocked(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_PLANNING,
        ]);

        // Create unfinished task (in_progress)
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        // Should be blocked
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_COMPLETED);
        $this->assertFalse($validation->isValid);
        $this->assertEquals('has_unfinished_tasks', $validation->errorCode);
        $this->assertStringContainsString('unfinished tasks', $validation->error);
    }

    /**
     * Test planning → completed without unfinished tasks (should be allowed)
     */
    public function test_planning_to_completed_without_unfinished_tasks_allowed(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_PLANNING,
        ]);

        // Create only backlog tasks (not unfinished)
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'status' => TaskStatus::BACKLOG,
        ]);

        // Should be allowed
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_COMPLETED);
        $this->assertTrue($validation->isValid);
    }

    /**
     * Test active → planning with active tasks (should be blocked)
     */
    public function test_active_to_planning_with_active_tasks_blocked(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_ACTIVE,
        ]);

        // Create active task (in_progress)
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        // Should be blocked
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_PLANNING);
        $this->assertFalse($validation->isValid);
        $this->assertEquals('has_active_tasks', $validation->errorCode);
        $this->assertStringContainsString('active tasks', $validation->error);
    }

    /**
     * Test active → planning without active tasks (should be allowed)
     */
    public function test_active_to_planning_without_active_tasks_allowed(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_ACTIVE,
        ]);

        // Create only backlog tasks (not active)
        Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'status' => TaskStatus::BACKLOG,
        ]);

        // Should be allowed
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_PLANNING);
        $this->assertTrue($validation->isValid);
    }

    /**
     * Test completed → active (should be blocked - not in transition matrix)
     */
    public function test_completed_to_active_blocked(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_COMPLETED,
        ]);

        // Should be blocked
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_ACTIVE);
        $this->assertFalse($validation->isValid);
        $this->assertEquals('invalid_transition', $validation->errorCode);
    }

    /**
     * Test invalid transition (planning → on_hold - not allowed)
     */
    public function test_invalid_transition_blocked(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_PLANNING,
        ]);

        // planning → on_hold is not in transition matrix
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_ON_HOLD);
        $this->assertFalse($validation->isValid);
        $this->assertEquals('invalid_transition', $validation->errorCode);
    }

    /**
     * Test same status transition (should be allowed - no-op)
     */
    public function test_same_status_transition_allowed(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_ACTIVE,
        ]);

        // Same status should be allowed
        $validation = $this->transitionService->validateTransition($project, Project::STATUS_ACTIVE);
        $this->assertTrue($validation->isValid);
    }

    /**
     * Test cancelled → archived
     */
    public function test_cancelled_to_archived(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_CANCELLED,
        ]);

        $validation = $this->transitionService->validateTransition($project, Project::STATUS_ARCHIVED);
        $this->assertTrue($validation->isValid);
        
        $project->update(['status' => Project::STATUS_ARCHIVED]);
        $this->assertEquals(Project::STATUS_ARCHIVED, $project->fresh()->status);
    }
}

