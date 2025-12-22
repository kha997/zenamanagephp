<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskCommentApiTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create a task
        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_id' => $this->user->id,
        ]);

        // Authenticate user
        Sanctum::actingAs($this->user, ['tenant']);
    }

    protected function tearDown(): void
    {
        // Ensure proper cleanup
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_comments_for_task()
    {
        // Create some comments
        TaskComment::factory()->count(3)->create([
            'task_id' => $this->task->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/task-comments/task/{$this->task->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'content',
                            'type',
                            'user',
                            'created_at',
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ]
                ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_create_comment()
    {
        $commentData = [
            'task_id' => $this->task->id,
            'content' => 'This is a test comment',
            'type' => 'comment',
            'is_internal' => false,
        ];

        $response = $this->postJson('/api/task-comments', $commentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'content',
                        'type',
                        'user',
                        'created_at',
                    ]
                ]);

        // Check if comment was created by querying the database directly
        $commentCount = \DB::table('task_comments')
            ->where('task_id', $this->task->id)
            ->where('content', 'This is a test comment')
            ->where('type', 'comment')
            ->where('user_id', $this->user->id)
            ->where('tenant_id', $this->tenant->id)
            ->count();
            
        $this->assertEquals(1, $commentCount, 'Comment should be persisted to database');
    }

    /** @test */
    public function it_can_update_comment()
    {
        $comment = TaskComment::factory()->create([
            'task_id' => $this->task->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'content' => 'Original content',
        ]);

        $updateData = [
            'content' => 'Updated content',
            'is_pinned' => true,
        ];

        $response = $this->putJson("/api/task-comments/{$comment->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'content',
                        'is_pinned',
                    ]
                ]);

        $this->assertDatabaseHas('task_comments', [
            'id' => $comment->id,
            'content' => 'Updated content',
            'is_pinned' => true,
        ]);
    }

    /** @test */
    public function it_can_delete_comment()
    {
        $comment = TaskComment::factory()->create([
            'task_id' => $this->task->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/task-comments/{$comment->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('task_comments', [
            'id' => $comment->id,
        ]);
    }

    /** @test */
    public function it_can_toggle_comment_pin()
    {
        $comment = TaskComment::factory()->create([
            'task_id' => $this->task->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'is_pinned' => false,
        ]);

        $response = $this->patchJson("/api/task-comments/{$comment->id}/pin", ['is_pinned' => true]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'is_pinned',
                    ]
                ]);

        $this->assertDatabaseHas('task_comments', [
            'id' => $comment->id,
            'is_pinned' => true,
        ]);
    }

    /** @test */
    public function it_can_get_comment_statistics()
    {
        // Create different types of comments
        TaskComment::factory()->create([
            'task_id' => $this->task->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'comment',
            'is_pinned' => true,
            'is_internal' => false, // Explicitly set to false
        ]);

        TaskComment::factory()->create([
            'task_id' => $this->task->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'status_change',
            'is_internal' => true,
        ]);

        $response = $this->getJson("/api/task-comments/task/{$this->task->id}/stats");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total',
                        'comments',
                        'status_changes',
                        'assignments',
                        'mentions',
                        'replies',
                        'pinned',
                        'internal',
                        'public',
                    ]
                ]);

        $stats = $response->json('data');
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['comments']);
        $this->assertEquals(1, $stats['status_changes']);
        $this->assertEquals(1, $stats['pinned']);
        $this->assertEquals(1, $stats['internal']);
    }

    /** @test */
    public function it_enforces_tenant_isolation()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherTask = Task::factory()->create(['tenant_id' => $otherTenant->id]);

        // Try to access comments from another tenant
        $response = $this->getJson("/api/task-comments/task/{$otherTask->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_validates_comment_creation()
    {
        $response = $this->postJson('/api/task-comments', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['task_id', 'content']);
    }

    /** @test */
    public function it_validates_comment_update()
    {
        $comment = TaskComment::factory()->create([
            'task_id' => $this->task->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/task-comments/{$comment->id}", [
            'content' => str_repeat('a', 5001), // Too long
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['content']);
    }
}
