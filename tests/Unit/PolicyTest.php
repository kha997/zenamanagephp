<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Task;
use App\Models\Project;
use App\Models\Component;
use App\Models\Document;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\DocumentPolicy;
use Mockery;

/**
 * Policy Unit tests
 */
class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;
    protected $project;
    protected $task;
    protected $document;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        
        // Create real user first, then mock the hasAnyRole method
        $realUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = Mockery::mock(User::class)->makePartial();
        $this->user->id = $realUser->id;
        $this->user->tenant_id = $this->tenant->id;
        $this->user->shouldReceive('hasAnyRole')->andReturn(true);
        
        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'code' => 'PRJ-POLICY-001',
            'status' => 'active',
            'owner_id' => $this->user->id,
        ]);
        
        $this->task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'status' => 'backlog',
            'created_by' => $this->user->id,
        ]);
        
        $this->document = Document::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Document',
            'original_name' => 'test.pdf',
            'file_path' => '/uploads/test.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'test-hash-123',
            'uploaded_by' => $this->user->id,
        ]);
    }

    /**
     * Test ProjectPolicy
     */
    public function test_project_policy(): void
    {
        $policy = new ProjectPolicy();
        
        // User can view any projects with tenant
        $this->assertTrue($policy->viewAny($this->user));
        
        // User can view own project
        $this->assertTrue($policy->view($this->user, $this->project));
        
        // User can create projects
        $this->assertTrue($policy->create($this->user));
        
        // User can update own project
        $this->assertTrue($policy->update($this->user, $this->project));
        
        // User can delete own project
        $this->assertTrue($policy->delete($this->user, $this->project));
    }

    /**
     * Test TaskPolicy
     */
    public function test_task_policy(): void
    {
        $policy = new TaskPolicy();
        
        // User can view any tasks
        $this->assertTrue($policy->viewAny($this->user));
        
        // User can view task in their project
        $this->assertTrue($policy->view($this->user, $this->task));
        
        // User can create tasks
        $this->assertTrue($policy->create($this->user));
        
        // User can update task they created
        $this->assertTrue($policy->update($this->user, $this->task));
        
        // User can delete task they created
        $this->assertTrue($policy->delete($this->user, $this->task));
    }

    /**
     * Test DocumentPolicy
     */
    public function test_document_policy(): void
    {
        $policy = new DocumentPolicy();
        
        // User can view any documents with tenant
        $this->assertTrue($policy->viewAny($this->user));
        
        // User can view document in their project
        $this->assertTrue($policy->view($this->user, $this->document));
        
        // User can create documents
        $this->assertTrue($policy->create($this->user));
        
        // User can update document they uploaded
        $this->assertTrue($policy->update($this->user, $this->document));
        
        // User can delete document they uploaded
        $this->assertTrue($policy->delete($this->user, $this->document));
        
        // User can download document they can view
        $this->assertTrue($policy->download($this->user, $this->document));
        
        // User can share document they can update
        $this->assertTrue($policy->share($this->user, $this->document));
    }

    /**
     * Test tenant isolation in policies
     */
    public function test_tenant_isolation_policies(): void
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        
        $projectPolicy = new ProjectPolicy();
        $taskPolicy = new TaskPolicy();
        $documentPolicy = new DocumentPolicy();
        
        // Other user cannot view this tenant's project
        $this->assertFalse($projectPolicy->view($otherUser, $this->project));
        
        // Other user cannot update this tenant's project
        $this->assertFalse($projectPolicy->update($otherUser, $this->project));
        
        // Other user cannot view this tenant's task
        $this->assertFalse($taskPolicy->view($otherUser, $this->task));
        
        // Other user cannot view this tenant's document
        $this->assertFalse($documentPolicy->view($otherUser, $this->document));
    }
}