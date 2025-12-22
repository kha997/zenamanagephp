<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AuditLogApiTest
 * 
 * Round 235: Audit Log Framework
 * 
 * Tests for Admin Audit Log API endpoints
 */
class AuditLogApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $adminUser;
    private User $regularUser;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $tenant = \App\Models\Tenant::factory()->create();
        $this->tenantId = (string) $tenant->id;

        // Create admin user with system.audit.view permission
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'admin',
        ]);

        // Create regular user without permission
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'member',
        ]);
    }

    /**
     * Test admin can list audit logs
     */
    public function test_admin_can_list_audit_logs(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create some audit logs
        AuditLog::factory()->count(5)->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
        ]);

        $response = $this->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user',
                            'action',
                            'entity_type',
                            'entity_id',
                            'created_at',
                        ],
                    ],
                    'pagination',
                ],
            ]);
    }

    /**
     * Test filters by user
     */
    public function test_filters_by_user(): void
    {
        Sanctum::actingAs($this->adminUser);

        $otherUser = User::factory()->create(['tenant_id' => $this->tenantId]);

        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
            'action' => 'role.created',
        ]);

        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $otherUser->id,
            'action' => 'role.created',
        ]);

        $response = $this->getJson("/api/v1/admin/audit-logs?user_id={$this->adminUser->id}");

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->adminUser->id, $data[0]['user']['id']);
    }

    /**
     * Test filters by entity
     */
    public function test_filters_by_entity(): void
    {
        Sanctum::actingAs($this->adminUser);

        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
            'entity_type' => 'Role',
            'action' => 'role.created',
        ]);

        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
            'entity_type' => 'User',
            'action' => 'user.roles_updated',
        ]);

        $response = $this->getJson('/api/v1/admin/audit-logs?entity_type=Role');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Role', $data[0]['entity_type']);
    }

    /**
     * Test filters by action
     */
    public function test_filters_by_action(): void
    {
        Sanctum::actingAs($this->adminUser);

        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
            'action' => 'role.created',
        ]);

        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
            'action' => 'role.updated',
        ]);

        $response = $this->getJson('/api/v1/admin/audit-logs?action=role.created');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('role.created', $data[0]['action']);
    }

    /**
     * Test filters by date range
     */
    public function test_filters_by_date_range(): void
    {
        Sanctum::actingAs($this->adminUser);

        $yesterday = now()->subDay();
        $tomorrow = now()->addDay();

        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
            'created_at' => now()->subDays(2),
        ]);

        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/admin/audit-logs?date_from={$yesterday->toDateString()}&date_to={$tomorrow->toDateString()}");

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    /**
     * Test respects tenant isolation
     */
    public function test_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create another tenant
        $otherTenant = \App\Models\Tenant::factory()->create();

        // Create audit logs for both tenants
        AuditLog::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->adminUser->id,
        ]);

        AuditLog::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->adminUser->id,
        ]);

        $response = $this->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->tenantId, $data[0]['tenant_id'] ?? null);
    }

    /**
     * Test requires auth and permission
     */
    public function test_requires_auth_and_permission(): void
    {
        // Test unauthenticated
        $response = $this->getJson('/api/v1/admin/audit-logs');
        $response->assertStatus(401);

        // Test without permission
        Sanctum::actingAs($this->regularUser);
        $response = $this->getJson('/api/v1/admin/audit-logs');
        $response->assertStatus(403);
    }

    /**
     * Test logs created for role changes
     */
    public function test_logs_created_for_role_changes(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create a role (this should trigger audit log)
        $role = Role::create([
            'name' => 'Test Role',
            'description' => 'Test Description',
            'scope' => 'custom',
            'is_active' => true,
        ]);

        // Check audit log was created
        $auditLog = AuditLog::where('action', 'role.created')
            ->where('entity_type', 'Role')
            ->where('entity_id', $role->id)
            ->where('tenant_id', $this->tenantId)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('role.created', $auditLog->action);
        $this->assertEquals('Role', $auditLog->entity_type);
    }

    /**
     * Test logs created for user role assignment
     */
    public function test_logs_created_for_user_role_assignment(): void
    {
        Sanctum::actingAs($this->adminUser);

        $role = Role::create([
            'name' => 'Test Role',
            'description' => 'Test Description',
            'scope' => 'custom',
            'is_active' => true,
        ]);

        $targetUser = User::factory()->create(['tenant_id' => $this->tenantId]);

        // Assign role to user (this should trigger audit log)
        $response = $this->putJson("/api/v1/admin/users/{$targetUser->id}/roles", [
            'roles' => [$role->id],
        ]);

        $response->assertStatus(200);

        // Check audit log was created
        $auditLog = AuditLog::where('action', 'user.roles_updated')
            ->where('entity_type', 'User')
            ->where('entity_id', $targetUser->id)
            ->where('tenant_id', $this->tenantId)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('user.roles_updated', $auditLog->action);
    }

    /**
     * Test logs created for document version creation
     * Round 238: Document Audit Integration
     */
    public function test_logs_created_for_document_version_creation(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create project
        $project = \App\Models\Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        // Create document
        $document = \App\Models\Document::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $project->id,
        ]);

        // Simulate document version creation via service
        $projectManagementService = app(\App\Services\ProjectManagementService::class);
        
        // Create a test file
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100);
        
        try {
            $projectManagementService->uploadDocumentNewVersion(
                $project->id,
                $this->tenantId,
                $document->id,
                $file,
                ['name' => 'Updated Document', 'description' => 'Version note']
            );
        } catch (\Exception $e) {
            // If file storage fails, that's okay - we just need to verify audit log was created
        }

        // Check audit log was created
        $auditLog = AuditLog::where('action', 'document.version_created')
            ->where('entity_type', 'Document')
            ->where('entity_id', $document->id)
            ->where('project_id', $project->id)
            ->where('tenant_id', $this->tenantId)
            ->first();

        $this->assertNotNull($auditLog, 'Audit log for document version creation should exist');
        $this->assertEquals('document.version_created', $auditLog->action);
        $this->assertNotNull($auditLog->payload_after);
        $this->assertArrayHasKey('version_id', $auditLog->payload_after);
        $this->assertArrayHasKey('version_number', $auditLog->payload_after);
    }

    /**
     * Test logs created for task creation
     * Round 238: Task Audit Integration
     */
    public function test_logs_created_for_task_creation(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create project
        $project = \App\Models\Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        // Create task via service
        $taskService = app(\App\Services\ProjectTaskManagementService::class);
        
        $task = $taskService->createTaskForProject(
            $this->tenantId,
            $project->id,
            [
                'name' => 'Test Task',
                'description' => 'Test Description',
                'status' => 'pending',
                'due_date' => now()->addDays(7)->toDateString(),
            ]
        );

        // Check audit log was created
        $auditLog = AuditLog::where('action', 'task.created')
            ->where('entity_type', 'ProjectTask')
            ->where('entity_id', $task->id)
            ->where('project_id', $project->id)
            ->where('tenant_id', $this->tenantId)
            ->first();

        $this->assertNotNull($auditLog, 'Audit log for task creation should exist');
        $this->assertEquals('task.created', $auditLog->action);
        $this->assertNotNull($auditLog->payload_after);
        $this->assertArrayHasKey('name', $auditLog->payload_after);
        $this->assertArrayHasKey('status', $auditLog->payload_after);
    }

    /**
     * Test logs created for task status change
     * Round 238: Task Audit Integration
     */
    public function test_logs_created_for_task_status_change(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create project
        $project = \App\Models\Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        // Create task
        $taskService = app(\App\Services\ProjectTaskManagementService::class);
        
        $task = $taskService->createTaskForProject(
            $this->tenantId,
            $project->id,
            [
                'name' => 'Test Task',
                'status' => 'pending',
            ]
        );

        // Update task status
        $taskService->updateTaskForProject(
            $this->tenantId,
            $project,
            $task->id,
            ['status' => 'in_progress']
        );

        // Check audit log was created
        $auditLog = AuditLog::where('action', 'task.status_changed')
            ->where('entity_type', 'ProjectTask')
            ->where('entity_id', $task->id)
            ->where('project_id', $project->id)
            ->where('tenant_id', $this->tenantId)
            ->first();

        $this->assertNotNull($auditLog, 'Audit log for task status change should exist');
        $this->assertEquals('task.status_changed', $auditLog->action);
        $this->assertNotNull($auditLog->payload_before);
        $this->assertNotNull($auditLog->payload_after);
        $this->assertEquals('pending', $auditLog->payload_before['status']);
        $this->assertEquals('in_progress', $auditLog->payload_after['status']);
    }

    /**
     * Test logs created for task due date change
     * Round 238: Task Audit Integration
     */
    public function test_logs_created_for_task_due_date_change(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create project
        $project = \App\Models\Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        // Create task
        $taskService = app(\App\Services\ProjectTaskManagementService::class);
        
        $originalDueDate = now()->addDays(7);
        $task = $taskService->createTaskForProject(
            $this->tenantId,
            $project->id,
            [
                'name' => 'Test Task',
                'due_date' => $originalDueDate->toDateString(),
            ]
        );

        // Update task due date
        $newDueDate = now()->addDays(14);
        $taskService->updateTaskForProject(
            $this->tenantId,
            $project,
            $task->id,
            ['due_date' => $newDueDate->toDateString()]
        );

        // Check audit log was created
        $auditLog = AuditLog::where('action', 'task.due_date_changed')
            ->where('entity_type', 'ProjectTask')
            ->where('entity_id', $task->id)
            ->where('project_id', $project->id)
            ->where('tenant_id', $this->tenantId)
            ->first();

        $this->assertNotNull($auditLog, 'Audit log for task due date change should exist');
        $this->assertEquals('task.due_date_changed', $auditLog->action);
        $this->assertNotNull($auditLog->payload_before);
        $this->assertNotNull($auditLog->payload_after);
    }

    /**
     * Test module filter works
     * Round 238: Admin UI Enhancements
     */
    public function test_module_filter_works(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create audit logs for different modules
        AuditLog::factory()->create([
            'tenant_id' => (string) $this->tenantId,
            'user_id' => (string) $this->adminUser->id,
            'action' => 'role.created',
        ]);

        AuditLog::factory()->create([
            'tenant_id' => (string) $this->tenantId,
            'user_id' => (string) $this->adminUser->id,
            'action' => 'co.created',
        ]);

        AuditLog::factory()->create([
            'tenant_id' => (string) $this->tenantId,
            'user_id' => (string) $this->adminUser->id,
            'action' => 'document.version_created',
        ]);

        AuditLog::factory()->create([
            'tenant_id' => (string) $this->tenantId,
            'user_id' => (string) $this->adminUser->id,
            'action' => 'task.created',
        ]);

        // Test RBAC filter
        $response = $this->getJson('/api/v1/admin/audit-logs?module=RBAC');
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('role.created', $data[0]['action']);

        // Test Cost filter
        $response = $this->getJson('/api/v1/admin/audit-logs?module=Cost');
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('co.created', $data[0]['action']);

        // Test Documents filter
        $response = $this->getJson('/api/v1/admin/audit-logs?module=Documents');
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('document.version_created', $data[0]['action']);

        // Test Tasks filter
        $response = $this->getJson('/api/v1/admin/audit-logs?module=Tasks');
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('task.created', $data[0]['action']);
    }

    /**
     * Test search filter works
     * Round 238: Admin UI Enhancements
     */
    public function test_search_filter_works(): void
    {
        Sanctum::actingAs($this->adminUser);

        AuditLog::factory()->create([
            'tenant_id' => (string) $this->tenantId,
            'user_id' => (string) $this->adminUser->id,
            'action' => 'role.created',
            'entity_type' => 'Role',
        ]);

        AuditLog::factory()->create([
            'tenant_id' => (string) $this->tenantId,
            'user_id' => (string) $this->adminUser->id,
            'action' => 'task.created',
            'entity_type' => 'ProjectTask',
        ]);

        // Search by action
        $response = $this->getJson('/api/v1/admin/audit-logs?search=role');
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('role', strtolower($data[0]['action']));

        // Search by entity type
        $response = $this->getJson('/api/v1/admin/audit-logs?search=ProjectTask');
        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('ProjectTask', $data[0]['entity_type']);
    }
}
