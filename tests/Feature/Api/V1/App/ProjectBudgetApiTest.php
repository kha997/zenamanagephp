<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * ProjectBudgetApiTest
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * Tests for budget lines API endpoints with tenant isolation and CRUD operations
 * 
 * @group project-budget
 * @group api-v1
 */
class ProjectBudgetApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;
    protected Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(219001);
        $this->setDomainName('project-budget-api');
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

        // Attach users to tenants
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);
    }

    public function test_it_lists_budget_lines_for_project(): void
    {
        Sanctum::actingAs($this->userA);

        // Create budget lines
        ProjectBudgetLine::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
        ]);

        $response = $this->getJson("/api/v1/app/projects/{$this->projectA->id}/budget-lines");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'project_id',
                        'description',
                        'amount_budget',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_it_creates_budget_line_for_project(): void
    {
        Sanctum::actingAs($this->userA);

        $data = [
            'description' => 'Test Budget Line',
            'amount_budget' => 1000000,
            'cost_category' => 'structure',
            'cost_code' => 'STR-001',
        ];

        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/budget-lines",
            $data
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'description',
                    'amount_budget',
                ]
            ]);

        $this->assertDatabaseHas('project_budget_lines', [
            'project_id' => $this->projectA->id,
            'description' => 'Test Budget Line',
            'amount_budget' => 1000000,
        ]);
    }

    public function test_it_updates_budget_line_for_project(): void
    {
        Sanctum::actingAs($this->userA);

        $budgetLine = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'description' => 'Original Description',
            'amount_budget' => 500000,
        ]);

        $data = [
            'description' => 'Updated Description',
            'amount_budget' => 1500000,
        ];

        $response = $this->patchJson(
            "/api/v1/app/projects/{$this->projectA->id}/budget-lines/{$budgetLine->id}",
            $data
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('project_budget_lines', [
            'id' => $budgetLine->id,
            'description' => 'Updated Description',
            'amount_budget' => 1500000,
        ]);
    }

    public function test_it_soft_deletes_budget_line_for_project(): void
    {
        Sanctum::actingAs($this->userA);

        $budgetLine = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/app/projects/{$this->projectA->id}/budget-lines/{$budgetLine->id}"
        );

        $response->assertStatus(200);

        $this->assertSoftDeleted('project_budget_lines', [
            'id' => $budgetLine->id,
        ]);
    }

    public function test_it_enforces_tenant_isolation_for_budget_lines(): void
    {
        Sanctum::actingAs($this->userA);

        // Create budget line in tenant B's project
        $budgetLineB = ProjectBudgetLine::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
        ]);

        // Try to access tenant B's budget line from tenant A
        $response = $this->getJson(
            "/api/v1/app/projects/{$this->projectB->id}/budget-lines"
        );

        // Should return 404 because project doesn't belong to tenant A
        $response->assertStatus(404);

        // Try to update tenant B's budget line
        $response = $this->patchJson(
            "/api/v1/app/projects/{$this->projectB->id}/budget-lines/{$budgetLineB->id}",
            ['description' => 'Hacked']
        );

        $response->assertStatus(404);
    }
}
