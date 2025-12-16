<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Project Status API Endpoint Tests
 * 
 * Tests for project status transitions via API endpoints
 * Verifies API properly uses ProjectStatusTransitionService
 * 
 * Uses DomainTestIsolation for reproducible test data
 */
class ProjectStatusTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(78901);
        $this->setDomainName('projects-api');
        $this->setupDomainIsolation();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'pm',
        ]);

        Sanctum::actingAs($this->user, [], 'sanctum');
    }

    /**
     * Test API endpoint: active → completed → archived (happy path)
     */
    public function test_api_active_to_completed_to_archived(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_ACTIVE,
        ]);

        // Step 1: active → completed via API
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_COMPLETED,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Project::STATUS_COMPLETED);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_COMPLETED,
        ]);

        // Step 2: completed → archived via API
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_ARCHIVED,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Project::STATUS_ARCHIVED);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_ARCHIVED,
        ]);

        // Step 3: Verify archived is terminal (cannot transition from it)
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_ACTIVE,
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test API endpoint: planning → completed with unfinished tasks (should be blocked)
     */
    public function test_api_planning_to_completed_with_unfinished_tasks_blocked(): void
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

        // Should be blocked via API
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_COMPLETED,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'has_unfinished_tasks')
            ->assertJsonPath('error.message', fn ($message) => str_contains($message, 'unfinished tasks'));
    }

    /**
     * Test API endpoint: planning → completed without unfinished tasks (should be allowed)
     */
    public function test_api_planning_to_completed_without_unfinished_tasks_allowed(): void
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

        // Should be allowed via API
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_COMPLETED,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Project::STATUS_COMPLETED);
    }

    /**
     * Test API endpoint: active → planning with active tasks (should be blocked)
     */
    public function test_api_active_to_planning_with_active_tasks_blocked(): void
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

        // Should be blocked via API
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_PLANNING,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'has_active_tasks')
            ->assertJsonPath('error.message', fn ($message) => str_contains($message, 'active tasks'));
    }

    /**
     * Test API endpoint: active → planning without active tasks (should be allowed)
     */
    public function test_api_active_to_planning_without_active_tasks_allowed(): void
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

        // Should be allowed via API
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_PLANNING,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Project::STATUS_PLANNING);
    }

    /**
     * Test API endpoint: completed → active (should be blocked - not in transition matrix)
     */
    public function test_api_completed_to_active_blocked(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_COMPLETED,
        ]);

        // Should be blocked via API
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_ACTIVE,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    /**
     * Test API endpoint: invalid transition (planning → on_hold - not allowed)
     */
    public function test_api_invalid_transition_blocked(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_PLANNING,
        ]);

        // planning → on_hold is not in transition matrix
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_ON_HOLD,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    /**
     * Test API endpoint: cancelled → archived
     */
    public function test_api_cancelled_to_archived(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
            'status' => Project::STATUS_CANCELLED,
        ]);

        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_ARCHIVED,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Project::STATUS_ARCHIVED);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_ARCHIVED,
        ]);
    }

    /**
     * Test API endpoint: archive completed project with tasks (should be allowed)
     */
    public function test_api_archive_completed_project_with_tasks_allowed(): void
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

        // Should be able to archive completed project even with tasks via API
        $response = $this->putJson("/api/v1/app/projects/{$project->id}", [
            'status' => Project::STATUS_ARCHIVED,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', Project::STATUS_ARCHIVED);
    }
}

