<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Subtask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

/**
 * Integration tests for Subtasks API Controller (V1)
 * 
 * Tests the new Api/V1/App/SubtasksController that replaced Unified/SubtaskManagementController
 * 
 * @group subtasks
 */
class SubtasksControllerTest extends TestCase
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

        $this->setDomainSeed(89012);
        $this->setDomainName('subtasks');
        $this->setupDomainIsolation();

        $this->tenant = TestDataSeeder::createTenant();
        $this->storeTestData('tenant', $this->tenant);

        $this->user = TestDataSeeder::createUser($this->tenant, [
            'name' => 'Test User',
            'email' => 'user@subtasks-test.test',
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
     * Test get subtasks for task
     */
    public function test_get_subtasks_for_task(): void
    {
        // Create subtasks
        Subtask::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/subtasks");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data' => [],
                'meta' => [
                    'total',
                    'task_id',
                ],
            ],
        ]);
        $this->assertCount(3, $response->json('data.data'));
    }

    /**
     * Test get subtasks respects tenant isolation
     */
    public function test_get_subtasks_respects_tenant_isolation(): void
    {
        $otherTenant = TestDataSeeder::createTenant();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherTask = Task::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
        ]);

        Subtask::factory()->create([
            'tenant_id' => $otherTenant->id,
            'task_id' => $otherTask->id,
        ]);

        Sanctum::actingAs($this->user);

        // Should not see other tenant's subtasks
        $response = $this->getJson("/api/v1/app/tasks/{$otherTask->id}/subtasks");

        $response->assertStatus(403);
    }

    /**
     * Test create subtask
     */
    public function test_create_subtask(): void
    {
        Sanctum::actingAs($this->user);

        $subtaskData = [
            'task_id' => $this->task->id,
            'title' => 'Test Subtask',
            'description' => 'Test description',
            'status' => 'todo',
            'priority' => 'normal',
        ];

        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/subtasks", $subtaskData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'title',
                'status',
            ],
        ]);
        $this->assertEquals('Test Subtask', $response->json('data.title'));
    }

    /**
     * Test show subtask
     */
    public function test_show_subtask(): void
    {
        $subtask = Subtask::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/subtasks/{$subtask->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'title',
            ],
        ]);
        $this->assertEquals($subtask->id, $response->json('data.id'));
    }

    /**
     * Test update subtask
     */
    public function test_update_subtask(): void
    {
        $subtask = Subtask::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
        ]);

        Sanctum::actingAs($this->user);

        $updateData = [
            'title' => 'Updated Subtask',
            'status' => 'in_progress',
        ];

        $response = $this->putJson("/api/v1/app/subtasks/{$subtask->id}", $updateData);

        $response->assertStatus(200);
        $this->assertEquals('Updated Subtask', $response->json('data.title'));
        $this->assertEquals('in_progress', $response->json('data.status'));
    }

    /**
     * Test delete subtask
     */
    public function test_delete_subtask(): void
    {
        $subtask = Subtask::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/app/subtasks/{$subtask->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('subtasks', ['id' => $subtask->id]);
    }

    /**
     * Test update subtask progress
     */
    public function test_update_subtask_progress(): void
    {
        $subtask = Subtask::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'progress' => 0,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->patchJson("/api/v1/app/subtasks/{$subtask->id}/progress", [
            'progress' => 50,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(50, $response->json('data.progress'));
    }
}

