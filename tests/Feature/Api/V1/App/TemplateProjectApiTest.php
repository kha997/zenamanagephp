<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * TemplateProject API Test
 * 
 * Round 195: Project from Template API Tests
 * 
 * Tests for creating projects from templates
 * 
 * @group templates
 * @group projects
 * @group api-v1
 */
class TemplateProjectApiTest extends TestCase
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
        $this->setDomainSeed(195001);
        $this->setDomainName('template-project-api');
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

        // Create users
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
        
        // Refresh users to ensure pivot data is available
        $this->userA->refresh();
        $this->userB->refresh();
    }

    /**
     * Test creating project from project template for current tenant
     */
    public function test_it_creates_project_from_project_template_for_current_tenant(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID using canonical resolution (same as controller does)
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $authenticatedUser->load('tenants');
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create a project-type template with explicit tenant binding
        // TenantScope doesn't auto-set tenant_id, so we must set it explicitly
        $template = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Project Template',
            'category' => 'project',
            'is_active' => true,
            'created_by' => $this->userA->id,
            'metadata' => [
                'project_defaults' => [
                    'status' => 'planning',
                    'priority' => 'normal',
                ],
            ],
        ]);

        $payload = [
            'name' => 'New Project from Template',
            'description' => 'Project created from template',
            'code' => 'PROJ-TEST-001',
            'status' => 'active',
            'priority' => 'high',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
        ];

        $response = $this->postJson("/api/v1/app/templates/{$template->id}/projects", $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'template_id',
                    'tenant_id',
                ],
                'message',
            ])
            ->assertJsonPath('data.name', 'New Project from Template')
            ->assertJsonPath('data.template_id', $template->id);

        $projectId = $response->json('data.id');

        // Verify project exists in database with correct tenant_id and template_id
        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'New Project from Template',
            'template_id' => $template->id,
            'tenant_id' => (string) $this->tenantA->id,
            'status' => 'active', // Request data overrides template default
            'priority' => 'high', // Request data overrides template default
        ]);
    }

    /**
     * Test that creating project from template of another tenant is rejected
     */
    public function test_it_rejects_creating_project_from_template_of_another_tenant(): void
    {
        // Create template for Tenant B with explicit tenant binding
        // TenantScope doesn't auto-set tenant_id, so we must set it explicitly
        $templateB = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $this->tenantB->id,
            'name' => 'Tenant B Template',
            'category' => 'project',
            'is_active' => true,
            'created_by' => $this->userB->id,
        ]);

        // Authenticate as Tenant A user
        Sanctum::actingAs($this->userA, [], 'sanctum');

        $payload = [
            'name' => 'Hacked Project',
            'description' => 'Trying to use other tenant template',
        ];

        $response = $this->postJson("/api/v1/app/templates/{$templateB->id}/projects", $payload);

        // Should return 404 (template not found for this tenant)
        // Template lookup fails because template belongs to different tenant
        $response->assertStatus(404);

        // Verify no project was created
        $this->assertDatabaseMissing('projects', [
            'name' => 'Hacked Project',
        ]);
    }

    /**
     * Test that creating project from non-project template is rejected
     */
    public function test_it_rejects_creating_project_from_non_project_template(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID using canonical resolution (same as controller does)
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $authenticatedUser->load('tenants');
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create a task-type template (not project) with explicit tenant binding
        // TenantScope doesn't auto-set tenant_id, so we must set it explicitly
        $template = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Task Template',
            'category' => 'task', // Not 'project'
            'is_active' => true,
            'created_by' => $this->userA->id,
        ]);

        $payload = [
            'name' => 'Project from Task Template',
            'description' => 'Should fail',
        ];

        $response = $this->postJson("/api/v1/app/templates/{$template->id}/projects", $payload);

        // Should return 422 (validation error - wrong template type)
        // The service throws abort(422) which becomes HttpException
        $response->assertStatus(422);

        // Verify no project was created
        $this->assertDatabaseMissing('projects', [
            'name' => 'Project from Task Template',
        ]);
    }
}

