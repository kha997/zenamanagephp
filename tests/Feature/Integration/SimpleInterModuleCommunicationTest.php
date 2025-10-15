<?php declare(strict_types=1);

namespace Tests\Feature\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use App\Models\ChangeRequest;
use App\Models\Tenant;

/**
 * Simple Integration Tests cho Inter-module Communication
 * 
 * Kiểm tra communication và data consistency giữa các modules cơ bản
 */
class SimpleInterModuleCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test Project-Task Integration
     * Kiểm tra tích hợp giữa Project và Task modules
     */
    public function test_project_task_integration(): void
    {
        // Tạo task cho project
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Integration Test Task',
            'status' => 'pending',
            'created_by' => $this->user->id
        ]);

        // Kiểm tra relationship
        $this->assertTrue($this->project->tasks->contains($task));
        $this->assertEquals($this->project->id, $task->project_id);
        $this->assertEquals($this->tenant->id, $task->tenant_id);

        // Kiểm tra tenant isolation
        $this->assertEquals($this->tenant->id, $this->project->tenant_id);
        $this->assertEquals($this->tenant->id, $task->tenant_id);
    }

    /**
     * Test Project-Document Integration
     * Kiểm tra tích hợp giữa Project và Document modules
     */
    public function test_project_document_integration(): void
    {
        // Authenticate user để TenantScope hoạt động đúng
        $this->actingAs($this->user);

        // Tạo document cho project
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Integration Test Document',
            'uploaded_by' => $this->user->id
        ]);

        // Debug: Kiểm tra document có được tạo đúng không
        $this->assertNotNull($document);
        $this->assertEquals($this->project->id, $document->project_id);
        $this->assertEquals($this->tenant->id, $document->tenant_id);

        // Reload project để load relationships
        $this->project->refresh();
        $this->project->load('documents');

        // Debug: Kiểm tra documents collection
        $this->assertNotNull($this->project->documents);
        $this->assertGreaterThan(0, $this->project->documents->count());

        // Kiểm tra relationship
        $this->assertTrue($this->project->documents->contains($document));

        // Kiểm tra tenant isolation
        $this->assertEquals($this->tenant->id, $this->project->tenant_id);
        $this->assertEquals($this->tenant->id, $document->tenant_id);
    }

    /**
     * Test Project-ChangeRequest Integration
     * Kiểm tra tích hợp giữa Project và ChangeRequest modules
     */
    public function test_project_change_request_integration(): void
    {
        // Tạo change request cho project
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Integration Test Change Request',
            'status' => 'draft',
            'created_by' => $this->user->id
        ]);

        // Kiểm tra relationship
        $this->assertTrue($this->project->changeRequests->contains($changeRequest));
        $this->assertEquals($this->project->id, $changeRequest->project_id);
        $this->assertEquals($this->tenant->id, $changeRequest->tenant_id);

        // Kiểm tra tenant isolation
        $this->assertEquals($this->tenant->id, $this->project->tenant_id);
        $this->assertEquals($this->tenant->id, $changeRequest->tenant_id);
    }

    /**
     * Test User-Project Integration
     * Kiểm tra tích hợp giữa User và Project modules
     */
    public function test_user_project_integration(): void
    {
        // Kiểm tra user có thể access project của tenant mình
        $this->assertEquals($this->tenant->id, $this->user->tenant_id);
        $this->assertEquals($this->tenant->id, $this->project->tenant_id);

        // Tạo thêm project cho cùng tenant
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second Project'
        ]);

        // Kiểm tra cả 2 projects đều thuộc cùng tenant
        $this->assertEquals($this->tenant->id, $project2->tenant_id);
        $this->assertNotEquals($this->project->id, $project2->id);
    }

    /**
     * Test Cross-Module Data Consistency
     * Kiểm tra tính nhất quán dữ liệu giữa các modules
     */
    public function test_cross_module_data_consistency(): void
    {
        // Authenticate user để TenantScope hoạt động đúng
        $this->actingAs($this->user);

        // Tạo task và document cho cùng project
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Consistency Test Task',
            'created_by' => $this->user->id
        ]);

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Consistency Test Document',
            'uploaded_by' => $this->user->id
        ]);

        // Reload project để load relationships
        $this->project->refresh();
        $this->project->load(['tasks', 'documents']);

        // Kiểm tra tất cả entities đều có cùng tenant_id
        $this->assertEquals($this->tenant->id, $this->project->tenant_id);
        $this->assertEquals($this->tenant->id, $task->tenant_id);
        $this->assertEquals($this->tenant->id, $document->tenant_id);

        // Kiểm tra tất cả entities đều có cùng project_id
        $this->assertEquals($this->project->id, $task->project_id);
        $this->assertEquals($this->project->id, $document->project_id);

        // Kiểm tra relationships hoạt động đúng
        $this->assertTrue($this->project->tasks->contains($task));
        $this->assertTrue($this->project->documents->contains($document));
    }

    /**
     * Test Tenant Isolation Across Modules
     * Kiểm tra tenant isolation được maintain across modules
     */
    public function test_tenant_isolation_across_modules(): void
    {
        // Tạo tenant khác
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id
        ]);
        $projectB = Project::factory()->create([
            'tenant_id' => $tenantB->id
        ]);

        // Tạo data cho tenant B
        $taskB = Task::factory()->create([
            'tenant_id' => $tenantB->id,
            'project_id' => $projectB->id,
            'name' => 'Tenant B Task',
            'created_by' => $userB->id
        ]);

        $documentB = Document::factory()->create([
            'tenant_id' => $tenantB->id,
            'project_id' => $projectB->id,
            'name' => 'Tenant B Document',
            'uploaded_by' => $userB->id
        ]);

        // Reload projects để load relationships
        $this->project->refresh();
        $this->project->load(['tasks', 'documents']);
        $projectB->refresh();
        $projectB->load(['tasks', 'documents']);

        // Kiểm tra tenant A không thể thấy data của tenant B
        $this->assertNotEquals($this->tenant->id, $tenantB->id);
        $this->assertNotEquals($this->project->id, $projectB->id);

        // Kiểm tra queries chỉ trả về data của tenant đúng
        $tenantATasks = Task::where('tenant_id', $this->tenant->id)->get();
        $tenantBTasks = Task::where('tenant_id', $tenantB->id)->get();

        $this->assertFalse($tenantATasks->contains($taskB));
        $this->assertTrue($tenantBTasks->contains($taskB));

        // Kiểm tra project relationships chỉ show data của tenant đúng
        $this->assertFalse($this->project->tasks->contains($taskB));
        $this->assertTrue($projectB->tasks->contains($taskB));
    }
}
