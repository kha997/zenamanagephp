<?php declare(strict_types=1);

namespace Tests\Feature\Api\Projects;

use App\Models\Project;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Feature tests for Project Alerts endpoints
 * 
 * @group projects
 */
class ProjectAlertsTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected $user;
    protected $tenant;
    protected $seedData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setDomainSeed(23456);
        $this->setDomainName('projects');
        $this->setupDomainIsolation();
        
        $this->seedData = TestDataSeeder::seedProjectsDomain($this->getDomainSeed());
        $this->tenant = $this->seedData['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        $this->user = collect($this->seedData['users'])->firstWhere('email', 'pm@projects-test.test');
        if (!$this->user) {
            $this->user = $this->seedData['users'][0];
        }
        
        Sanctum::actingAs($this->user);
    }

    /**
     * Test tạo project với end_date < today, status = active → có entry overdue
     */
    public function test_get_alerts_returns_overdue_projects(): void
    {
        // Create overdue project
        $overdueProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'end_date' => Carbon::now()->subDays(5),
            'status' => 'active',
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
                            'type',
                            'source',
                        ]
                    ]
                ]);
        
        $alerts = $response->json('data');
        $this->assertNotEmpty($alerts);
        
        // Find overdue alert for our project
        $overdueAlert = collect($alerts)->firstWhere('metadata.project_id', $overdueProject->id);
        $this->assertNotNull($overdueAlert);
        $this->assertEquals('overdue', $overdueAlert['type']);
    }

    /**
     * Test tạo project với end_date >= today → không có alert
     */
    public function test_get_alerts_excludes_future_projects(): void
    {
        // Create future project
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'end_date' => Carbon::now()->addDays(5),
            'status' => 'active',
        ]);
        
        $response = $this->getJson('/api/v1/app/projects/alerts');
        
        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        // Should not have alerts for future projects
        $futureAlerts = collect($alerts)->filter(function ($alert) {
            return $alert['type'] === 'overdue';
        });
        $this->assertEmpty($futureAlerts);
    }

    /**
     * Test với multiple projects → verify tenant isolation
     */
    public function test_get_alerts_respects_tenant_isolation(): void
    {
        // Create overdue project in current tenant
        $overdueProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'end_date' => Carbon::now()->subDays(5),
            'status' => 'active',
        ]);
        
        // Create another tenant and overdue project
        $otherTenant = \App\Models\Tenant::factory()->create();
        Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'end_date' => Carbon::now()->subDays(5),
            'status' => 'active',
        ]);
        
        $response = $this->getJson('/api/v1/app/projects/alerts');
        
        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        // Should only see alerts for current tenant
        $projectIds = collect($alerts)->pluck('metadata.project_id')->filter();
        $this->assertContains($overdueProject->id, $projectIds);
        $this->assertCount(1, $projectIds);
    }
}

