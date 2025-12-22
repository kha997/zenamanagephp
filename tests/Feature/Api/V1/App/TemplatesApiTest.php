<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Templates API Test
 * 
 * Round 192: Templates Vertical MVP
 * 
 * Tests for templates API endpoints with tenant isolation and CRUD operations
 * 
 * @group templates
 * @group api-v1
 */
class TemplatesApiTest extends TestCase
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
        $this->setDomainSeed(192001);
        $this->setDomainName('templates-api');
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
        // This ensures TenancyService.resolveActiveTenantId() returns the correct tenant
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        // Refresh users and load tenants relationship to ensure pivot data is available
        // This ensures TenancyService.resolveActiveTenantId() can access defaultTenant()
        $this->userA->refresh();
        $this->userA->load('tenants');
        $this->userB->refresh();
        $this->userB->load('tenants');
    }

    /**
     * Test listing templates scoped to current tenant
     */
    public function test_it_lists_templates_scoped_to_current_tenant(): void
    {
        // Create templates for Tenant A
        $templateA1 = Template::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Template A1',
            'category' => 'project',
            'created_by' => $this->userA->id,
        ]);

        $templateA2 = Template::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Template A2',
            'category' => 'task',
            'created_by' => $this->userA->id,
        ]);

        // Create templates for Tenant B
        $templateB1 = Template::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Template B1',
            'category' => 'project',
            'created_by' => $this->userB->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        $response = $this->getJson('/api/v1/app/templates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'category', 'description', 'is_active', 'created_at']
                ]
            ]);

        $responseData = $response->json();
        $data = $responseData['data'] ?? [];
        $templateIds = array_map('strval', array_column($data, 'id'));

        // Assert only Tenant A templates are returned
        $this->assertContains((string) $templateA1->id, $templateIds);
        $this->assertContains((string) $templateA2->id, $templateIds);
        $this->assertNotContains((string) $templateB1->id, $templateIds);
    }

    /**
     * Test creating template for current tenant
     */
    public function test_it_creates_template_for_current_tenant(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        $payload = [
            'name' => 'New Template',
            'type' => 'project',
            'description' => 'Test description',
            'is_active' => true,
            'metadata' => ['key' => 'value'],
        ];

        $response = $this->postJson('/api/v1/app/templates', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'category', 'description', 'is_active', 'metadata']
            ])
            ->assertJsonPath('data.name', 'New Template')
            ->assertJsonPath('data.is_active', true);

        $templateId = $response->json('data.id');

        // Assert template exists in database with correct tenant_id
        $this->assertDatabaseHas('templates', [
            'id' => $templateId,
            'tenant_id' => $this->tenantA->id,
            'name' => 'New Template',
            'category' => 'project', // type 'project' maps to category 'project'
            'is_active' => true,
            'created_by' => $this->userA->id,
        ]);
    }

    /**
     * Test validation of required fields on create
     */
    public function test_it_validates_required_fields_on_create(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Missing name
        $response = $this->postJson('/api/v1/app/templates', [
            'type' => 'project',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Missing type
        $response = $this->postJson('/api/v1/app/templates', [
            'name' => 'Test Template',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        // Invalid type
        $response = $this->postJson('/api/v1/app/templates', [
            'name' => 'Test Template',
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /**
     * Test updating template for current tenant
     */
    public function test_it_updates_template_for_current_tenant(): void
    {
        // Authenticate user - pivot attachment in setUp ensures TenancyService resolves correct tenant
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID using canonical resolution (same as controller does)
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        $this->assertNotNull($resolvedTenantId, 'TenancyService should resolve tenant ID');
        $this->assertEquals((string) $this->tenantA->id, (string) $resolvedTenantId, 'Resolved tenant should match tenantA');
        
        // Create template with explicit tenant binding
        // TenantScope doesn't auto-set tenant_id, so we must set it explicitly
        $template = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Original Name',
            'category' => 'project',
            'is_active' => true,
            'created_by' => $this->userA->id,
        ]);

        // Verify template exists in database with correct tenant_id
        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $response = $this->patchJson("/api/v1/app/templates/{$template->id}", [
            'name' => 'Updated Name',
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
            'is_active' => false,
        ]);
    }

    /**
     * Test that templates of other tenants cannot be accessed
     */
    public function test_it_does_not_allow_access_to_templates_of_other_tenants(): void
    {
        // Create template for Tenant B
        $templateB = Template::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Template',
            'category' => 'project',
            'created_by' => $this->userB->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Try to GET template from Tenant B
        $response = $this->getJson("/api/v1/app/templates/{$templateB->id}");
        $response->assertStatus(404);

        // Try to UPDATE template from Tenant B
        $response = $this->patchJson("/api/v1/app/templates/{$templateB->id}", [
            'name' => 'Hacked Name',
        ]);
        $response->assertStatus(404);

        // Try to DELETE template from Tenant B
        $response = $this->deleteJson("/api/v1/app/templates/{$templateB->id}");
        $response->assertStatus(404);

        // Verify template still exists and unchanged
        $this->assertDatabaseHas('templates', [
            'id' => $templateB->id,
            'name' => 'Tenant B Template',
        ]);
    }

    /**
     * Test soft deleting templates
     */
    public function test_it_soft_deletes_templates(): void
    {
        // Authenticate user - pivot attachment in setUp ensures TenancyService resolves correct tenant
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID using canonical resolution (same as controller does)
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        $this->assertNotNull($resolvedTenantId, 'TenancyService should resolve tenant ID');
        $this->assertEquals((string) $this->tenantA->id, (string) $resolvedTenantId, 'Resolved tenant should match tenantA');
        
        // Create template with explicit tenant binding
        // TenantScope doesn't auto-set tenant_id, so we must set it explicitly
        $template = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Template to Delete',
            'category' => 'project',
            'created_by' => $this->userA->id,
        ]);

        // Verify template exists in database with correct tenant_id
        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $response = $this->deleteJson("/api/v1/app/templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Template deleted successfully');

        // Assert soft delete (deleted_at set)
        $this->assertSoftDeleted('templates', [
            'id' => $template->id,
        ]);

        // Assert template no longer shows up in list
        $listResponse = $this->getJson('/api/v1/app/templates');
        $listResponse->assertStatus(200);
        
        $responseData = $listResponse->json();
        $data = $responseData['data'] ?? [];
        $templateIds = array_map('strval', array_column($data, 'id'));
        $this->assertNotContains((string) $template->id, $templateIds);
    }

    /**
     * Test filtering templates by type
     */
    public function test_it_filters_templates_by_type(): void
    {
        Template::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project Template',
            'category' => 'project',
            'created_by' => $this->userA->id,
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Task Template',
            'category' => 'task',
            'created_by' => $this->userA->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        $response = $this->getJson('/api/v1/app/templates?type=project');

        $response->assertStatus(200);
        $responseData = $response->json();
        $data = $responseData['data'] ?? [];
        
        $this->assertCount(1, $data);
        $this->assertEquals('Project Template', $data[0]['name']);
    }

    /**
     * Test filtering templates by is_active
     */
    public function test_it_filters_templates_by_is_active(): void
    {
        Template::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Active Template',
            'is_active' => true,
            'created_by' => $this->userA->id,
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Inactive Template',
            'is_active' => false,
            'created_by' => $this->userA->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        $response = $this->getJson('/api/v1/app/templates?is_active=true');

        $response->assertStatus(200);
        $responseData = $response->json();
        $data = $responseData['data'] ?? [];
        
        $this->assertCount(1, $data);
        $this->assertEquals('Active Template', $data[0]['name']);
    }

    /**
     * Test searching templates by name and description
     */
    public function test_it_searches_templates_by_name_and_description(): void
    {
        Template::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Architecture Template',
            'description' => 'For architecture projects',
            'created_by' => $this->userA->id,
        ]);

        Template::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Construction Template',
            'description' => 'For construction projects',
            'created_by' => $this->userA->id,
        ]);

        Sanctum::actingAs($this->userA, [], 'sanctum');

        $response = $this->getJson('/api/v1/app/templates?search=Architecture');

        $response->assertStatus(200);
        $responseData = $response->json();
        $data = $responseData['data'] ?? [];
        
        $this->assertCount(1, $data);
        $this->assertEquals('Architecture Template', $data[0]['name']);
    }
}

