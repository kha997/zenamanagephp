<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

/**
 * Integration tests for Task Assignments API Controller (V1)
 * 
 * Tests the new Api/V1/App/TaskAssignmentsController that replaced Unified/TaskAssignmentController
 * 
 * @group task-assignments
 */
class TaskAssignmentsControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker, DomainTestIsolation;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected Task $task;
    protected string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDomainSeed(34567);
        $this->setDomainName('task-assignments');
        $this->setupDomainIsolation();

        $this->tenant = TestDataSeeder::createTenant();
        $this->storeTestData('tenant', $this->tenant);

        $this->user = TestDataSeeder::createUser($this->tenant, [
            'name' => 'Project Manager',
            'email' => 'pm@task-assignments-test.test',
            'role' => 'pm',
            'password' => Hash::make('password123'),
        ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->user);
        $this->authToken = $this->user->createToken('test-token')->plainTextToken;

        Cache::flush();
    }

    /**
     * Test assign users to task
     */
    public function test_assign_users_to_task(): void
    {
        $users = User::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
            'users' => $users->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }

    /**
     * Test assign users respects tenant isolation
     */
    public function test_assign_users_respects_tenant_isolation(): void
    {
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, ['role' => 'member']);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
            'users' => [$otherUser->id],
        ]);

        // Should fail because user is from different tenant
        $response->assertStatus(422);
    }

    /**
     * Test get users assigned to task
     */
    public function test_get_users_assigned_to_task(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/assignments/users");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
        ]);
    }

    /**
     * Test remove user from task
     */
    public function test_remove_user_from_task(): void
    {
        $userToRemove = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        // First assign the user
        TaskAssignment::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'user_id' => $userToRemove->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/app/tasks/{$this->task->id}/assignments/users/{$userToRemove->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('task_assignments', [
            'task_id' => $this->task->id,
            'user_id' => $userToRemove->id,
        ]);
    }

    /**
     * Test reassign user - change assignee from X to Y
     */
    public function test_reassign_user_from_x_to_y(): void
    {
        $userX = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        $userY = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($this->user);

        // Step 1: Assign user X
        $response1 = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
            'users' => [$userX->id],
        ]);

        $response1->assertStatus(200);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $this->task->id,
            'user_id' => $userX->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Step 2: Remove user X
        $response2 = $this->deleteJson("/api/v1/app/tasks/{$this->task->id}/assignments/users/{$userX->id}");
        $response2->assertStatus(200);

        // Step 3: Assign user Y
        $response3 = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
            'users' => [$userY->id],
        ]);

        $response3->assertStatus(200);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $this->task->id,
            'user_id' => $userY->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify user X is no longer assigned
        $this->assertDatabaseMissing('task_assignments', [
            'task_id' => $this->task->id,
            'user_id' => $userX->id,
        ]);
    }

    /**
     * Test RBAC - user without update permission cannot assign users
     */
    public function test_user_without_update_permission_cannot_assign_users(): void
    {
        // Create a user who is NOT the creator and NOT the assignee
        $unauthorizedUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        // Ensure task is NOT created by or assigned to this user
        $this->task->update([
            'created_by' => $this->user->id, // Created by PM
            'assignee_id' => null, // Not assigned
        ]);

        $targetUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($unauthorizedUser);

        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
            'users' => [$targetUser->id],
        ]);

        // Should return 403 Forbidden
        $response->assertStatus(403);
        $response->assertJson(fn ($json) =>
            $json->where('success', false)
                ->where('error.code', 'FORBIDDEN')
                ->etc()
        );

        // Verify assignment was NOT created
        $this->assertDatabaseMissing('task_assignments', [
            'task_id' => $this->task->id,
            'user_id' => $targetUser->id,
        ]);
    }

    /**
     * Test bulk assign multiple users to task
     */
    public function test_bulk_assign_multiple_users_to_task(): void
    {
        $users = User::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
            'users' => $users->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'success' => [],
                'failed' => [],
                'skipped' => [],
            ],
        ]);

        // Verify all users are assigned
        foreach ($users as $user) {
            $this->assertDatabaseHas('task_assignments', [
                'task_id' => $this->task->id,
                'user_id' => $user->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        // Verify response contains success count
        $responseData = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($responseData['success']));
    }

    /**
     * Test assign user to task in different tenant → 422
     * (This is already covered by test_assign_users_respects_tenant_isolation,
     * but adding explicit test for clarity)
     */
    public function test_cannot_assign_user_from_different_tenant(): void
    {
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, [
            'role' => 'member',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/assignments/users", [
            'users' => [$otherUser->id],
        ]);

        // Should fail with 422 (validation error)
        $response->assertStatus(422);

        // Verify assignment was NOT created
        $this->assertDatabaseMissing('task_assignments', [
            'task_id' => $this->task->id,
            'user_id' => $otherUser->id,
        ]);
    }
}

