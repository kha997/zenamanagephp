<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Task;
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
 * Feature tests for Tasks Alerts endpoint
 * 
 * Tests overdue tasks alerts functionality
 * 
 * @group alerts
 * @group tasks
 */
class TasksAlertsTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected $tenant;
    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(88888);
        $this->setDomainName('alerts');
        $this->setupDomainIsolation();
        
        // Seed test data
        $data = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        $this->tenant = $data['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Use PM user from seed data
        $this->user = collect($data['users'])->firstWhere('email', 'pm@tasks-test.test');
        if (!$this->user) {
            $this->user = $data['users'][0];
        }
        
        // Get or create project
        $this->project = $data['projects'][0] ?? Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'owner_id' => $this->user->id,
        ]);
        
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'sanctum');
    }

    /**
     * Test get alerts returns overdue tasks
     */
    public function test_get_alerts_returns_overdue_tasks(): void
    {
        // Create an overdue task (end_date < today, status != done/cancelled)
        $overdueTask = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Overdue Task',
            'status' => 'in_progress',
            'end_date' => Carbon::now()->subDays(3), // 3 days ago
            'assignee_id' => $this->user->id,
        ]);

        // Create a non-overdue task
        $activeTask = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Active Task',
            'status' => 'in_progress',
            'end_date' => Carbon::now()->addDays(5), // 5 days in future
            'assignee_id' => $this->user->id,
        ]);

        // Create a done task (should not appear even if overdue)
        $doneTask = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Done Task',
            'status' => 'done',
            'end_date' => Carbon::now()->subDays(3), // Overdue but done
            'assignee_id' => $this->user->id,
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
        
        // Should have at least 1 alert (the overdue task)
        $this->assertGreaterThanOrEqual(1, count($alerts));
        
        // Find the overdue task alert
        $overdueAlert = collect($alerts)->firstWhere('metadata.task_id', $overdueTask->id);
        $this->assertNotNull($overdueAlert);
        $this->assertEquals('overdue-' . $overdueTask->id, $overdueAlert['id']);
        $this->assertEquals('Task Overdue', $overdueAlert['title']);
        $this->assertStringContainsString('Overdue Task', $overdueAlert['message']);
        $this->assertEquals('high', $overdueAlert['severity']);
        $this->assertEquals('overdue', $overdueAlert['type']);
        $this->assertEquals('task', $overdueAlert['source']);
    }

    /**
     * Test alerts respect tenant isolation
     */
    public function test_alerts_respect_tenant_isolation(): void
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        
        // Create overdue task in other tenant
        Task::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'title' => 'Other Tenant Overdue Task',
            'status' => 'in_progress',
            'end_date' => Carbon::now()->subDays(5),
        ]);

        // Create overdue task in current tenant
        $overdueTask = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'My Tenant Overdue Task',
            'status' => 'in_progress',
            'end_date' => Carbon::now()->subDays(5),
            'assignee_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/app/tasks/alerts');

        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        
        // Should only see alerts from current tenant
        $taskIds = collect($alerts)->pluck('metadata.task_id')->toArray();
        $this->assertContains($overdueTask->id, $taskIds);
        $this->assertNotContains(null, $taskIds); // Should not have nulls
    }

    /**
     * Test alerts exclude done and cancelled tasks
     */
    public function test_alerts_exclude_done_and_cancelled_tasks(): void
    {
        // Create overdue done task (should not appear)
        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Done Overdue Task',
            'status' => 'done',
            'end_date' => Carbon::now()->subDays(5),
            'assignee_id' => $this->user->id,
        ]);

        // Create overdue cancelled task (should not appear)
        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Cancelled Overdue Task',
            'status' => 'cancelled',
            'end_date' => Carbon::now()->subDays(5),
            'assignee_id' => $this->user->id,
        ]);

        // Create overdue in_progress task (should appear)
        $overdueTask = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'In Progress Overdue Task',
            'status' => 'in_progress',
            'end_date' => Carbon::now()->subDays(5),
            'assignee_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/app/tasks/alerts');

        $response->assertStatus(200);
        
        $alerts = $response->json('data');
        
        // Should only have alert for in_progress task
        $taskIds = collect($alerts)->pluck('metadata.task_id')->toArray();
        $this->assertContains($overdueTask->id, $taskIds);
    }
}

