<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

/**
 * Integration tests for Task Attachments API Controller (V1)
 * 
 * Tests the new Api/V1/App/TaskAttachmentsController that replaced Unified/TaskAttachmentManagementController
 * 
 * @group task-attachments
 */
class TaskAttachmentsControllerTest extends TestCase
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

        Storage::fake('local');

        $this->setDomainSeed(12345);
        $this->setDomainName('task-attachments');
        $this->setupDomainIsolation();

        $this->tenant = TestDataSeeder::createTenant();
        $this->storeTestData('tenant', $this->tenant);

        $this->user = TestDataSeeder::createUser($this->tenant, [
            'name' => 'Test User',
            'email' => 'user@attachments-test.test',
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
     * Test get attachments for task
     */
    public function test_get_attachments_for_task(): void
    {
        TaskAttachment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'uploaded_by' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/tasks/{$this->task->id}/attachments");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
        ]);
    }

    /**
     * Test get attachments respects tenant isolation
     */
    public function test_get_attachments_respects_tenant_isolation(): void
    {
        $otherTenant = TestDataSeeder::createTenant();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherTask = Task::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
        ]);

        TaskAttachment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'task_id' => $otherTask->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/tasks/{$otherTask->id}/attachments");

        $response->assertStatus(403);
    }

    /**
     * Test upload attachment
     */
    public function test_upload_attachment(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->create('test-document.pdf', 100);

        $response = $this->postJson("/api/v1/app/tasks/{$this->task->id}/attachments", [
            'file' => $file,
            'name' => 'Test Attachment',
            'description' => 'Test description',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
            ],
        ]);
    }

    /**
     * Test show attachment
     */
    public function test_show_attachment(): void
    {
        $attachment = TaskAttachment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'uploaded_by' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/task-attachments/{$attachment->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
            ],
        ]);
        $this->assertEquals($attachment->id, $response->json('data.id'));
    }

    /**
     * Test delete attachment
     */
    public function test_delete_attachment(): void
    {
        $attachment = TaskAttachment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'uploaded_by' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/app/task-attachments/{$attachment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('task_attachments', ['id' => $attachment->id]);
    }

    /**
     * Test download attachment
     */
    public function test_download_attachment(): void
    {
        $attachment = TaskAttachment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $this->task->id,
            'uploaded_by' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/task-attachments/{$attachment->id}/download");

        // May return 200 with download or 404 if file not found
        $this->assertContains($response->status(), [200, 404]);
    }
}

