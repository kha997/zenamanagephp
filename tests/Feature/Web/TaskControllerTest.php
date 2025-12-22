<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Task;
use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

/**
 * Feature tests for Web Task Controller
 * 
 * Tests tenant isolation, error handling, and new methods (documents, history)
 * 
 * @group tasks
 */
class TaskControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker, DomainTestIsolation;

    protected User $user;
    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected Task $task;
    protected Project $project;
    protected array $seedData;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(34567);
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        // Seed tasks domain test data
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        $this->tenant = $this->seedData['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Use project manager user from seed data
        $this->user = collect($this->seedData['users'])->firstWhere('email', 'pm@tasks-test.test');
        if (!$this->user) {
            $this->user = $this->seedData['users'][0];
        }
        
        // Use project from seed data
        $this->project = $this->seedData['projects'][0];
        
        // Create a task for testing
        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'status' => 'in_progress',
        ]);
        
        // Create other tenant for isolation tests
        $this->otherTenant = Tenant::factory()->create();
        
        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test getTenantId() helper method with authenticated user
     */
    public function test_get_tenant_id_from_authenticated_user(): void
    {
        $this->actingAs($this->user);
        
        $response = $this->get('/app/tasks');
        
        $response->assertStatus(200);
        // Verify tenant isolation is working
        $response->assertViewHas('tasks');
    }

    /**
     * Test getTenantId() helper method with test route
     */
    public function test_get_tenant_id_from_test_route(): void
    {
        // Test route should use fallback tenant ID
        $response = $this->get('/test-tasks');
        
        $response->assertStatus(200);
        $response->assertViewIs('app.tasks.index');
    }

    /**
     * Test documents() method renders view
     */
    public function test_documents_renders_view(): void
    {
        $this->actingAs($this->user);
        
        $response = $this->get("/app/tasks/{$this->task->id}/documents");
        
        $response->assertStatus(200);
        $response->assertViewIs('app.tasks.documents');
        $response->assertViewHas('task');
        $response->assertViewHas('documents');
    }

    /**
     * Test documents() method enforces tenant isolation
     */
    public function test_documents_enforces_tenant_isolation(): void
    {
        // Create task in different tenant
        $otherTask = Task::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'project_id' => $this->project->id,
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get("/app/tasks/{$otherTask->id}/documents");
        
        // Should return 404 to prevent information leakage
        $response->assertStatus(404);
    }

    /**
     * Test history() method renders view with audit logs
     */
    public function test_history_renders_view_with_audit_logs(): void
    {
        // Create audit logs for the task
        AuditLog::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'Task',
            'entity_id' => $this->task->id,
            'user_id' => $this->user->id,
            'action' => 'created',
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get("/app/tasks/{$this->task->id}/history");
        
        $response->assertStatus(200);
        $response->assertViewIs('app.tasks.history');
        $response->assertViewHas('task');
        $response->assertViewHas('history');
        $response->assertViewHas('pagination');
        
        // Verify history contains audit logs
        $history = $response->viewData('history');
        $this->assertCount(3, $history);
    }

    /**
     * Test history() method with pagination
     */
    public function test_history_pagination(): void
    {
        // Create more audit logs than per page (20)
        AuditLog::factory()->count(25)->create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'Task',
            'entity_id' => $this->task->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get("/app/tasks/{$this->task->id}/history");
        
        $response->assertStatus(200);
        $pagination = $response->viewData('pagination');
        $this->assertEquals(25, $pagination->total());
        $this->assertEquals(20, $pagination->perPage());
        $this->assertTrue($pagination->hasMorePages());
    }

    /**
     * Test history() method enforces tenant isolation
     */
    public function test_history_enforces_tenant_isolation(): void
    {
        // Create task in different tenant
        $otherTask = Task::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'project_id' => $this->project->id,
        ]);
        
        // Create audit log for other tenant's task
        AuditLog::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'entity_type' => 'Task',
            'entity_id' => $otherTask->id,
            'action' => 'created',
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get("/app/tasks/{$otherTask->id}/history");
        
        // Should return 404 to prevent information leakage
        $response->assertStatus(404);
    }

    /**
     * Test history() method with empty history
     */
    public function test_history_with_empty_history(): void
    {
        $this->actingAs($this->user);
        
        $response = $this->get("/app/tasks/{$this->task->id}/history");
        
        $response->assertStatus(200);
        $response->assertViewIs('app.tasks.history');
        $history = $response->viewData('history');
        $this->assertCount(0, $history);
    }

    /**
     * Test error handling for non-existent task
     */
    public function test_edit_handles_non_existent_task(): void
    {
        $this->actingAs($this->user);
        
        $nonExistentId = '01K00000000000000000000000';
        $response = $this->get("/app/tasks/{$nonExistentId}/edit");
        
        $response->assertStatus(404);
    }

    /**
     * Test error handling for tenant isolation violation in show()
     */
    public function test_show_handles_tenant_isolation_violation(): void
    {
        // Create task in different tenant
        $otherTask = Task::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'project_id' => $this->project->id,
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get("/app/tasks/{$otherTask->id}");
        
        // Should return 404 to prevent information leakage
        $response->assertStatus(404);
    }

    /**
     * Test stats calculation in index() method
     */
    public function test_index_includes_stats(): void
    {
        // Create tasks with different statuses
        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => 'done',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => 'in_progress',
        ]);
        
        $this->actingAs($this->user);
        
        $response = $this->get('/app/tasks');
        
        $response->assertStatus(200);
        $response->assertViewHas('stats');
        
        $stats = $response->viewData('stats');
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('in_progress', $stats);
        $this->assertArrayHasKey('done', $stats);
        $this->assertArrayHasKey('overdue', $stats);
    }

    /**
     * Test pagination metadata in index() method
     */
    public function test_index_includes_pagination_metadata(): void
    {
        $this->actingAs($this->user);
        
        $response = $this->get('/app/tasks');
        
        $response->assertStatus(200);
        $response->assertViewHas('meta');
        
        $meta = $response->viewData('meta');
        $this->assertArrayHasKey('current_page', $meta);
        $this->assertArrayHasKey('last_page', $meta);
        $this->assertArrayHasKey('total', $meta);
        $this->assertArrayHasKey('from', $meta);
        $this->assertArrayHasKey('to', $meta);
    }
}

