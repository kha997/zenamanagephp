<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\TaskTemplate;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * TaskTemplate API Test
 * 
 * Round 200: Task Template Vertical MVP
 * 
 * Tests for task template API endpoints with tenant isolation and CRUD operations
 * 
 * @group task-templates
 * @group api-v1
 */
class TaskTemplateApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(200001);
        $this->setDomainName('task-templates-api');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users with tenant_id
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        // Attach users to tenants via pivot table with is_default = true
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        // Refresh users and load tenants relationship
        $this->userA->refresh();
        $this->userA->load('tenants');
        $this->userB->refresh();
        $this->userB->load('tenants');
    }

    /**
     * Test listing task templates for template of current tenant
     */
    public function test_it_lists_task_templates_for_template_of_current_tenant(): void
    {
        // Create template for Tenant A
        $templateA = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Template A',
            'category' => 'project',
            'created_by' => $this->userA->id,
        ]);

        // Create task templates for Template A
        $taskTemplateA1 = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'template_id' => $templateA->id,
            'name' => 'Task A1',
            'order_index' => 1,
            'created_by' => $this->userA->id,
        ]);

        $taskTemplateA2 = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'template_id' => $templateA->id,
            'name' => 'Task A2',
            'order_index' => 2,
            'created_by' => $this->userA->id,
        ]);

        // Create template for Tenant B
        $templateB = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Template B',
            'category' => 'project',
            'created_by' => $this->userB->id,
        ]);

        // Create task template for Template B (should not appear in Tenant A's results)
        $taskTemplateB = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'template_id' => $templateB->id,
            'name' => 'Task B1',
            'order_index' => 1,
            'created_by' => $this->userB->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        $response = $this->getJson("/api/v1/app/templates/{$templateA->id}/task-templates");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'order_index', 'estimated_hours', 'is_required', 'created_at']
                ]
            ]);

        $responseData = $response->json();
        $data = $responseData['data'] ?? [];
        $taskIds = array_map('strval', array_column($data, 'id'));

        // Assert only Tenant A's task templates for Template A are returned
        $this->assertContains((string) $taskTemplateA1->id, $taskIds);
        $this->assertContains((string) $taskTemplateA2->id, $taskIds);
        $this->assertNotContains((string) $taskTemplateB->id, $taskIds);
    }

    /**
     * Test creating task template for template of current tenant
     */
    public function test_it_creates_task_template_for_template_of_current_tenant(): void
    {
        // Create template for Tenant A
        $templateA = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Template A',
            'category' => 'project',
            'created_by' => $this->userA->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        $payload = [
            'name' => 'New Task Template',
            'description' => 'Test description',
            'order_index' => 1,
            'estimated_hours' => 8.5,
            'is_required' => true,
            'metadata' => ['key' => 'value'],
        ];

        $response = $this->postJson("/api/v1/app/templates/{$templateA->id}/task-templates", $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'order_index', 'estimated_hours', 'is_required', 'metadata']
            ])
            ->assertJsonPath('data.name', 'New Task Template')
            ->assertJsonPath('data.is_required', true);

        $taskTemplateId = $response->json('data.id');

        // Assert task template exists in database with correct tenant_id and template_id
        $this->assertDatabaseHas('task_templates', [
            'id' => $taskTemplateId,
            'tenant_id' => $this->tenantA->id,
            'template_id' => $templateA->id,
            'name' => 'New Task Template',
            'is_required' => true,
            'created_by' => $this->userA->id,
        ]);
    }

    /**
     * Test validation of required fields on create
     */
    public function test_it_validates_required_fields_on_create(): void
    {
        // Create template for Tenant A
        $templateA = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Template A',
            'category' => 'project',
            'created_by' => $this->userA->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Missing name
        $response = $this->postJson("/api/v1/app/templates/{$templateA->id}/task-templates", [
            'order_index' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test updating task template for template of current tenant
     */
    public function test_it_updates_task_template_for_template_of_current_tenant(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        $this->assertNotNull($resolvedTenantId);
        $this->assertEquals((string) $this->tenantA->id, (string) $resolvedTenantId);
        
        // Create template
        $template = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Template',
            'category' => 'project',
            'created_by' => $this->userA->id,
        ]);

        // Create task template
        $taskTemplate = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Original Name',
            'order_index' => 1,
            'is_required' => true,
            'created_by' => $this->userA->id,
        ]);

        $response = $this->patchJson("/api/v1/app/templates/{$template->id}/task-templates/{$taskTemplate->id}", [
            'name' => 'Updated Name',
            'is_required' => false,
            'estimated_hours' => 10.5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.estimated_hours', '10.50');

        $this->assertDatabaseHas('task_templates', [
            'id' => $taskTemplate->id,
            'name' => 'Updated Name',
            'is_required' => false,
        ]);
    }

    /**
     * Test soft deleting task template
     */
    public function test_it_soft_deletes_task_template_for_template_of_current_tenant(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create template
        $template = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Template',
            'category' => 'project',
            'status' => 'draft',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Refresh to ensure it's persisted
        $template->refresh();

        // Create task template
        $taskTemplate = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Task Template to Delete',
            'order_index' => 1,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Refresh to ensure it's persisted
        $taskTemplate->refresh();

        $response = $this->deleteJson("/api/v1/app/templates/{$template->id}/task-templates/{$taskTemplate->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Task template deleted successfully');

        // Assert soft delete (deleted_at set)
        $this->assertSoftDeleted('task_templates', [
            'id' => $taskTemplate->id,
        ]);

        // Assert task template no longer shows up in list
        $listResponse = $this->getJson("/api/v1/app/templates/{$template->id}/task-templates");
        $listResponse->assertStatus(200);
        
        $responseData = $listResponse->json();
        $data = $responseData['data'] ?? [];
        $taskIds = array_map('strval', array_column($data, 'id'));
        $this->assertNotContains((string) $taskTemplate->id, $taskIds);
    }

    /**
     * Test that task templates of other tenants cannot be accessed
     */
    public function test_it_does_not_allow_cross_tenant_access_to_task_templates(): void
    {
        // Create template for Tenant B
        $templateB = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Template B',
            'category' => 'project',
            'status' => 'draft',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->userB->id,
            'updated_by' => $this->userB->id,
        ]);

        // Create task template for Template B
        $taskTemplateB = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'template_id' => $templateB->id,
            'name' => 'Tenant B Task Template',
            'order_index' => 1,
            'created_by' => $this->userB->id,
            'updated_by' => $this->userB->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Try to UPDATE task template from Tenant B
        $response = $this->patchJson("/api/v1/app/templates/{$templateB->id}/task-templates/{$taskTemplateB->id}", [
            'name' => 'Hacked Name',
        ]);
        $response->assertStatus(404);

        // Try to DELETE task template from Tenant B
        $response = $this->deleteJson("/api/v1/app/templates/{$templateB->id}/task-templates/{$taskTemplateB->id}");
        $response->assertStatus(404);

        // Verify task template still exists and unchanged
        $this->assertDatabaseHas('task_templates', [
            'id' => $taskTemplateB->id,
            'name' => 'Tenant B Task Template',
        ]);
    }
}

