<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Events\ProjectHealthPortfolioGenerated;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Tests for Project Health Portfolio Monitoring
 * 
 * Round 83: Project Health Observability & Perf Baseline
 * 
 * Tests that project health portfolio generation emits events with performance metrics.
 * 
 * @group reports
 * @group projects
 * @group health
 * @group monitoring
 */
class ProjectHealthMonitoringTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        
        // Create tenant
        $this->tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
        
        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@test.com',
        ]);
        
        // Attach user to tenant with 'admin' role (which has tenant.view_reports)
        $this->user->tenants()->attach($this->tenant->id, ['role' => 'admin']);
        
        // Create token
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that project health portfolio generation emits event with correct data
     */
    public function test_project_health_portfolio_emits_event_with_metrics(): void
    {
        // Create test data
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Client',
        ]);

        // Create project 1
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-001',
            'name' => 'Project 1',
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        // Create contract for project 1
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project1->id,
            'code' => 'CT-001',
            'total_value' => 10000.00,
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract1->id,
            'total_amount' => 12000.00,
            'status' => 'active',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract1->id,
            'amount' => 11000.00,
            'status' => 'recorded',
            'created_by_id' => $this->user->id,
            'updated_by_id' => $this->user->id,
        ]);

        // Create tasks for project 1
        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project1->id,
            'status' => 'done',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project1->id,
            'status' => 'in_progress',
        ]);

        // Create project 2
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-002',
            'name' => 'Project 2',
            'status' => 'planning',
        ]);

        // Fake events
        Event::fake([ProjectHealthPortfolioGenerated::class]);

        // Call the endpoint
        Sanctum::actingAs($this->user);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        // Assert response is successful
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));

        // Assert event was dispatched
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, function ($event) {
            return $event->tenantId === (string) $this->tenant->id
                && $event->projectCount > 0
                && $event->durationMs > 0;
        });

        // Assert event was dispatched exactly once
        Event::assertDispatchedTimes(ProjectHealthPortfolioGenerated::class, 1);
    }

    /**
     * Test that event contains correct tenant ID
     */
    public function test_event_contains_correct_tenant_id(): void
    {
        // Create minimal test data
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        Event::fake([ProjectHealthPortfolioGenerated::class]);

        Sanctum::actingAs($this->user);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response->assertStatus(200);

        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, function ($event) {
            return $event->tenantId === (string) $this->tenant->id;
        });
    }

    /**
     * Test that event contains correct project count
     */
    public function test_event_contains_correct_project_count(): void
    {
        // Create 3 projects
        Project::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Event::fake([ProjectHealthPortfolioGenerated::class]);

        Sanctum::actingAs($this->user);
        $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, function ($event) {
            return $event->projectCount === 3;
        });
    }

    /**
     * Test that event contains positive duration
     */
    public function test_event_contains_positive_duration(): void
    {
        // Create test data
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Event::fake([ProjectHealthPortfolioGenerated::class]);

        Sanctum::actingAs($this->user);
        $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, function ($event) {
            return $event->durationMs > 0 && is_float($event->durationMs);
        });
    }

    /**
     * Test that event is not dispatched when no projects exist
     */
    public function test_event_is_dispatched_even_with_zero_projects(): void
    {
        Event::fake([ProjectHealthPortfolioGenerated::class]);

        Sanctum::actingAs($this->user);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response->assertStatus(200);

        // Event should still be dispatched with projectCount = 0
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, function ($event) {
            return $event->tenantId === (string) $this->tenant->id
                && $event->projectCount === 0
                && $event->durationMs >= 0;
        });
    }
}

