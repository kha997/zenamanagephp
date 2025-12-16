<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

/**
 * Integration tests for Task Comments API Controller (V1)
 * 
 * Tests the new Api/V1/App/TaskCommentsController that replaced Unified/TaskCommentManagementController
 * 
 * @group task-comments
 */
class TaskCommentsControllerTest extends TestCase
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

        $this->setDomainSeed(90123);
        $this->setDomainName('task-comments');
        $this->setupDomainIsolation();

        $this->tenant = TestDataSeeder::createTenant();
        $this->storeTestData('tenant', $this->tenant);

        $this->user = TestDataSeeder::createUser($this->tenant, [
            'name' => 'Test User',
            'email' => 'user@comments-test.test',
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
     * Test get comments for task
     */
    public function test_get_comments_for_task(): void
    {
        TaskComment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/comments");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
        ]);
    }

    /**
     * Test get comments respects tenant isolation
     */
    public function test_get_comments_respects_tenant_isolation(): void
    {
        $otherTenant = TestDataSeeder::createTenant();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherTask = Task::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
        ]);

        TaskComment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'task_id' => $otherTask->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/tasks/{$otherTask->id}/comments");

        $response->assertStatus(403);
    }

    /**
     * Test create comment
     */
    public function test_create_comment(): void
    {
        Sanctum::actingAs($this->user);

        $commentData = [
            'content' => 'Test comment content',
            'is_internal' => false,
        ];

        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/comments", $commentData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'content',
            ],
        ]);
        $this->assertEquals('Test comment content', $response->json('data.content'));
    }

    /**
     * Test show comment
     */
    public function test_show_comment(): void
    {
        $comment = TaskComment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/task-comments/{$comment->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'content',
            ],
        ]);
        $this->assertEquals($comment->id, $response->json('data.id'));
    }

    /**
     * Test update comment
     */
    public function test_update_comment(): void
    {
        $comment = TaskComment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $updateData = [
            'content' => 'Updated comment content',
        ];

        $response = $this->putJson("/api/v1/app/task-comments/{$comment->id}", $updateData);

        $response->assertStatus(200);
        $this->assertEquals('Updated comment content', $response->json('data.content'));
    }

    /**
     * Test delete comment
     */
    public function test_delete_comment(): void
    {
        $comment = TaskComment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/app/task-comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
    }

    /**
     * Test toggle pin comment
     */
    public function test_toggle_pin_comment(): void
    {
        $comment = TaskComment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'is_pinned' => false,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/task-comments/{$comment->id}/toggle-pin");

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.is_pinned'));
    }
}

