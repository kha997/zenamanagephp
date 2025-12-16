<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tasks;

use App\Models\Project;
use App\Models\Task;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Feature tests for Task Alerts endpoints
 * 
 * @group tasks
 */
class TaskAlertsTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected $user;
    protected $tenant;
    protected $seedData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setDomainSeed(34567);
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        $this->tenant = $this->seedData['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        $this->user = collect($this->seedData['users'])->firstWhere('email', 'pm@tasks-test.test');
        if (!$this->user) {
            $this->user = $this->seedData['users'][0];
        }
        
        Sanctum::actingAs($this->user);
    }

    /**
     * Test tạo task với end_date < today, status != done → có entry overdue
     */
    public function test_get_alerts_returns_overdue_tasks(): void
    {
        $project = $this->seedData['projects'][0];
        
        // Create overdue task
        $overdueTask = Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'end_date' => Carbon::now()->subDays(5),
            'status' => 'in_progress',
        ]);
        
        $response = $this->getJson('/api/v1/app/tasks/alerts');
        
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
        
        // Find overdue alert for our task
        $overdueAlert = collect($alerts)->firstWhere('metadata.task_id', $overdueTask->id);
        $this->assertNotNull($overdueAlert);
        $this->assertEquals('overdue', $overdueAlert['type']);
    }

    /**
     * Test tạo task với end_date trong 24h tới → có "near deadline" alert
     */
    public function test_get_alerts_returns_near_deadline_tasks(): void
    {
        $project = $this->seedData['projects'][0];
        
        // Create task with deadline in 24 hours
        $nearDeadlineTask = Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'end_date' => Carbon::now()->addHours(20),
            'status' => 'in_progress',
        ]);
        
        $response = $this->getJson('/api/v1/app/tasks/alerts');
        
        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        // Should have near deadline alert
        $nearDeadlineAlerts = collect($alerts)->filter(function ($alert) use ($nearDeadlineTask) {
            return isset($alert['metadata']['task_id']) && 
                   $alert['metadata']['task_id'] == $nearDeadlineTask->id &&
                   ($alert['type'] === 'near_deadline' || str_contains($alert['message'], 'deadline'));
        });
        $this->assertNotEmpty($nearDeadlineAlerts);
    }

    /**
     * Test tenant isolation
     */
    public function test_get_alerts_respects_tenant_isolation(): void
    {
        $project = $this->seedData['projects'][0];
        
        // Create overdue task in current tenant
        $overdueTask = Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'end_date' => Carbon::now()->subDays(5),
            'status' => 'in_progress',
        ]);
        
        // Create another tenant and overdue task
        $otherTenant = \App\Models\Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        Task::factory()->create([
            'project_id' => $otherProject->id,
            'tenant_id' => $otherTenant->id,
            'end_date' => Carbon::now()->subDays(5),
            'status' => 'in_progress',
        ]);
        
        $response = $this->getJson('/api/v1/app/tasks/alerts');
        
        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        // Should only see alerts for current tenant
        $taskIds = collect($alerts)->pluck('metadata.task_id')->filter();
        $this->assertContains($overdueTask->id, $taskIds);
        $this->assertCount(1, $taskIds);
    }
}

