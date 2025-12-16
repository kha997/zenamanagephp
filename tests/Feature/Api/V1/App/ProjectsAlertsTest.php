<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Carbon\Carbon;

/**
 * Feature tests for Projects Alerts endpoint
 * 
 * Tests overdue projects alerts functionality
 * 
 * @group alerts
 * @group projects
 */
class ProjectsAlertsTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(99999);
        $this->setDomainName('alerts');
        $this->setupDomainIsolation();
        
        // Seed test data
        $data = TestDataSeeder::seedProjectsDomain($this->getDomainSeed());
        $this->tenant = $data['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Use PM user from seed data
        $this->user = collect($data['users'])->firstWhere('email', 'pm@projects-test.test');
        if (!$this->user) {
            $this->user = $data['users'][0];
        }
        
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'sanctum');
    }

    /**
     * Test get alerts returns overdue projects
     */
    public function test_get_alerts_returns_overdue_projects(): void
    {
        // Create an overdue project (end_date < today, status = active)
        $overdueProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Overdue Project',
            'status' => 'active',
            'end_date' => Carbon::now()->subDays(5), // 5 days ago
            'pm_id' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        // Create a non-overdue project (end_date in future)
        $activeProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Project',
            'status' => 'active',
            'end_date' => Carbon::now()->addDays(10), // 10 days in future
            'pm_id' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        // Create a completed project (should not appear even if overdue)
        $completedProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Completed Project',
            'status' => 'completed',
            'end_date' => Carbon::now()->subDays(5), // Overdue but completed
            'pm_id' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/app/projects/alerts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'message',
                        'severity',
                        'status',
                        'type',
                        'source',
                        'createdAt',
                        'metadata',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true
            ]);

        $alerts = $response->json('data');
        
        // Should have exactly 1 alert (only the overdue active project)
        $this->assertCount(1, $alerts);
        
        // Verify alert content
        $alert = $alerts[0];
        $this->assertEquals('overdue-' . $overdueProject->id, $alert['id']);
        $this->assertEquals('Project Overdue', $alert['title']);
        $this->assertStringContainsString('Overdue Project', $alert['message']);
        $this->assertEquals('high', $alert['severity']);
        $this->assertEquals('overdue', $alert['type']);
        $this->assertEquals('project', $alert['source']);
        $this->assertEquals($overdueProject->id, $alert['metadata']['project_id']);
    }

    /**
     * Test alerts respect tenant isolation
     */
    public function test_alerts_respect_tenant_isolation(): void
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create();
        
        // Create overdue project in other tenant
        Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Overdue Project',
            'status' => 'active',
            'end_date' => Carbon::now()->subDays(5),
        ]);

        // Create overdue project in current tenant
        $overdueProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'My Tenant Overdue Project',
            'status' => 'active',
            'end_date' => Carbon::now()->subDays(5),
            'pm_id' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/app/projects/alerts');

        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        
        // Should only see alert from current tenant
        $this->assertCount(1, $alerts);
        $this->assertEquals($overdueProject->id, $alerts[0]['metadata']['project_id']);
    }

    /**
     * Test alerts include on_hold projects
     */
    public function test_alerts_include_on_hold_overdue_projects(): void
    {
        // Create overdue on_hold project
        $onHoldProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'On Hold Overdue Project',
            'status' => 'on_hold',
            'end_date' => Carbon::now()->subDays(3),
            'pm_id' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/app/projects/alerts');

        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        
        // Should include on_hold overdue project
        $this->assertCount(1, $alerts);
        $this->assertEquals($onHoldProject->id, $alerts[0]['metadata']['project_id']);
    }

    /**
     * Test alerts exclude planning projects even if overdue
     */
    public function test_alerts_exclude_planning_projects(): void
    {
        // Create overdue planning project (should not appear)
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Planning Overdue Project',
            'status' => 'planning',
            'end_date' => Carbon::now()->subDays(5),
            'pm_id' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/app/projects/alerts');

        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        
        // Should have no alerts (planning projects are not considered overdue)
        $this->assertCount(0, $alerts);
    }
}

