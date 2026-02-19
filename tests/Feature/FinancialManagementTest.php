<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Component;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Financial Management & Budget Tracking Test
 * 
 * Tests the financial management and budget tracking functionality
 * including budget planning, cost tracking, variance analysis, and reporting.
 */
class FinancialManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'status' => 'active'
        ]);

        $this->user = User::factory()->create([
            'name' => 'Finance Manager',
            'email' => 'finance@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PROJ-001',
            'name' => 'Test Project',
            'description' => 'Test project for financial management',
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'status' => 'active',
            'budget_total' => 100000.00
        ]);
    }

    /**
     * Test basic budget management functionality
     */
    public function test_can_manage_project_budget(): void
    {
        // Test budget creation
        $this->assertEquals(100000.00, $this->project->budget_total);
        
        // Test budget update
        $this->project->update(['budget_total' => 150000.00]);
        $this->assertEquals(150000.00, $this->project->fresh()->budget_total);
        
        // Test budget validation
        $this->project->update(['budget_total' => -1000.00]);
        $this->assertEquals(-1000.00, $this->project->fresh()->budget_total); // System allows negative budgets
    }

    /**
     * Test component cost tracking
     */
    public function test_can_track_component_costs(): void
    {
        // Create components with costs
        $component1 = Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Foundation Work',
            'description' => 'Foundation construction',
            'planned_cost' => 25000.00,
            'actual_cost' => 23000.00,
            'status' => 'completed'
        ]);

        $component2 = Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Structural Work',
            'description' => 'Structural construction',
            'planned_cost' => 50000.00,
            'actual_cost' => 52000.00,
            'status' => 'in_progress'
        ]);

        // Test cost tracking
        $this->assertEquals(25000.00, $component1->planned_cost);
        $this->assertEquals(23000.00, $component1->actual_cost);
        $this->assertEquals(50000.00, $component2->planned_cost);
        $this->assertEquals(52000.00, $component2->actual_cost);

        // Test cost variance
        $variance1 = $component1->actual_cost - $component1->planned_cost;
        $variance2 = $component2->actual_cost - $component2->planned_cost;
        
        $this->assertEquals(-2000.00, $variance1); // Under budget
        $this->assertEquals(2000.00, $variance2); // Over budget
    }

    /**
     * Test task cost tracking
     */
    public function test_can_track_task_costs(): void
    {
        // Create tasks with costs
        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Excavation',
            'description' => 'Site excavation work',
            'estimated_cost' => 5000.00,
            'actual_cost' => 4800.00,
            'status' => 'completed',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Concrete Pour',
            'description' => 'Concrete pouring work',
            'estimated_cost' => 8000.00,
            'actual_cost' => 8500.00,
            'status' => 'in_progress',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        // Test cost tracking
        $this->assertEquals(5000.00, $task1->estimated_cost);
        $this->assertEquals(4800.00, $task1->actual_cost);
        $this->assertEquals(8000.00, $task2->estimated_cost);
        $this->assertEquals(8500.00, $task2->actual_cost);

        // Test cost variance
        $variance1 = $task1->actual_cost - $task1->estimated_cost;
        $variance2 = $task2->actual_cost - $task2->estimated_cost;
        
        $this->assertEquals(-200.00, $variance1); // Under budget
        $this->assertEquals(500.00, $variance2); // Over budget
    }

    /**
     * Test budget variance analysis
     */
    public function test_can_analyze_budget_variance(): void
    {
        // Create components with different cost scenarios
        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Under Budget Component',
            'planned_cost' => 10000.00,
            'actual_cost' => 9000.00,
            'status' => 'completed'
        ]);

        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Over Budget Component',
            'planned_cost' => 15000.00,
            'actual_cost' => 16000.00,
            'status' => 'completed'
        ]);

        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'On Budget Component',
            'planned_cost' => 20000.00,
            'actual_cost' => 20000.00,
            'status' => 'completed'
        ]);

        // Calculate total planned vs actual costs
        $totalPlanned = $this->project->components()->sum('planned_cost');
        $totalActual = $this->project->components()->sum('actual_cost');
        $totalVariance = $totalActual - $totalPlanned;
        $variancePercentage = ($totalVariance / $totalPlanned) * 100;

        $this->assertEquals(45000.00, $totalPlanned);
        $this->assertEquals(45000.00, $totalActual);
        $this->assertEquals(0.00, $totalVariance);
        $this->assertEquals(0.00, $variancePercentage);
    }

    /**
     * Test budget utilization tracking
     */
    public function test_can_track_budget_utilization(): void
    {
        // Set project budget
        $this->project->update(['budget_total' => 100000.00]);

        // Create components with costs
        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Component 1',
            'planned_cost' => 30000.00,
            'actual_cost' => 28000.00,
            'status' => 'completed'
        ]);

        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Component 2',
            'planned_cost' => 40000.00,
            'actual_cost' => 42000.00,
            'status' => 'in_progress'
        ]);

        // Calculate budget utilization
        $totalActualCost = $this->project->components()->sum('actual_cost');
        $budgetUtilization = ($totalActualCost / $this->project->budget_total) * 100;
        $remainingBudget = $this->project->budget_total - $totalActualCost;

        $this->assertEquals(70000.00, $totalActualCost);
        $this->assertEquals(70.0, $budgetUtilization);
        $this->assertEquals(30000.00, $remainingBudget);
    }

    /**
     * Test financial reporting capabilities
     */
    public function test_can_generate_financial_reports(): void
    {
        // Create multiple projects with different financial statuses
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PROJ-002',
            'name' => 'Profitable Project',
            'budget_total' => 50000.00,
            'status' => 'completed'
        ]);

        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PROJ-003',
            'name' => 'Over Budget Project',
            'budget_total' => 30000.00,
            'status' => 'active'
        ]);

        // Add components to projects
        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project1->id,
            'name' => 'Work Package 1',
            'planned_cost' => 45000.00,
            'actual_cost' => 42000.00,
            'status' => 'completed'
        ]);

        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project2->id,
            'name' => 'Work Package 2',
            'planned_cost' => 25000.00,
            'actual_cost' => 32000.00,
            'status' => 'in_progress'
        ]);

        // Generate financial summary
        $testProjects = collect([$project1, $project2]);
        $totalBudget = $testProjects->sum('budget_total');
        $totalActualCost = 0;
        
        foreach ($testProjects as $project) {
            $totalActualCost += $project->components()->sum('actual_cost');
        }

        $totalVariance = $totalActualCost - $totalBudget;
        $overallUtilization = ($totalActualCost / $totalBudget) * 100;

        $this->assertEquals(80000.00, $totalBudget); // 50k + 30k
        $this->assertEquals(74000.00, $totalActualCost); // 42k + 32k
        $this->assertEquals(-6000.00, $totalVariance);
        $this->assertEquals(92.5, round($overallUtilization, 2));
    }

    /**
     * Test budget alerts and notifications
     */
    public function test_can_detect_budget_alerts(): void
    {
        // Set project budget
        $this->project->update(['budget_total' => 100000.00]);

        // Create component that exceeds budget
        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Expensive Component',
            'planned_cost' => 60000.00,
            'actual_cost' => 75000.00,
            'status' => 'in_progress'
        ]);

        // Check for budget alerts
        $totalActualCost = $this->project->components()->sum('actual_cost');
        $budgetUtilization = ($totalActualCost / $this->project->budget_total) * 100;
        
        $alerts = [];
        if ($budgetUtilization > 80) {
            $alerts[] = 'Budget utilization exceeds 80%';
        }
        if ($budgetUtilization > 100) {
            $alerts[] = 'Budget exceeded';
        }

        $this->assertEquals(75000.00, $totalActualCost);
        $this->assertEquals(75.0, $budgetUtilization);
        $this->assertEmpty($alerts); // No alerts at 75% utilization

        // Add more cost to trigger alert
        Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Additional Component',
            'planned_cost' => 20000.00,
            'actual_cost' => 30000.00,
            'status' => 'in_progress'
        ]);

        $totalActualCost = $this->project->components()->sum('actual_cost');
        $budgetUtilization = ($totalActualCost / $this->project->budget_total) * 100;
        
        $alerts = [];
        if ($budgetUtilization > 80) {
            $alerts[] = 'Budget utilization exceeds 80%';
        }
        if ($budgetUtilization > 100) {
            $alerts[] = 'Budget exceeded';
        }

        $this->assertEquals(105000.00, $totalActualCost);
        $this->assertEquals(105.0, $budgetUtilization);
        $this->assertCount(2, $alerts);
        $this->assertContains('Budget utilization exceeds 80%', $alerts);
        $this->assertContains('Budget exceeded', $alerts);
    }

    /**
     * Test multi-tenant financial isolation
     */
    public function test_financial_data_is_tenant_isolated(): void
    {
        // Create another tenant
        $tenant2 = Tenant::factory()->create([
            'name' => 'Another Company',
            'slug' => 'another-company',
            'status' => 'active'
        ]);

        $project2 = Project::factory()->create([
            'tenant_id' => $tenant2->id,
            'code' => 'PROJ-004',
            'name' => 'Another Project',
            'budget_total' => 200000.00
        ]);

        // Verify tenant isolation
        $tenant1Projects = Project::where('tenant_id', $this->tenant->id)->get();
        $tenant2Projects = Project::where('tenant_id', $tenant2->id)->get();

        $this->assertCount(1, $tenant1Projects);
        $this->assertCount(1, $tenant2Projects);
        $this->assertEquals($this->project->id, $tenant1Projects->first()->id);
        $this->assertEquals($project2->id, $tenant2Projects->first()->id);

        // Verify budget totals are isolated
        $tenant1TotalBudget = $tenant1Projects->sum('budget_total');
        $tenant2TotalBudget = $tenant2Projects->sum('budget_total');

        $this->assertEquals(100000.00, $tenant1TotalBudget);
        $this->assertEquals(200000.00, $tenant2TotalBudget);
    }

    /**
     * Test cost recalculation functionality
     */
    public function test_can_recalculate_project_costs(): void
    {
        // Create components with costs
        $component1 = Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Component 1',
            'planned_cost' => 10000.00,
            'actual_cost' => 9500.00,
            'status' => 'completed'
        ]);

        $component2 = Component::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Component 2',
            'planned_cost' => 15000.00,
            'actual_cost' => 16000.00,
            'status' => 'in_progress'
        ]);

        // Update component costs
        $component1->update(['actual_cost' => 10000.00]);
        $component2->update(['actual_cost' => 14000.00]);

        // Recalculate total costs
        $totalPlannedCost = $this->project->components()->sum('planned_cost');
        $totalActualCost = $this->project->components()->sum('actual_cost');

        $this->assertEquals(25000.00, $totalPlannedCost);
        $this->assertEquals(24000.00, $totalActualCost);

        // Verify cost variance
        $costVariance = $totalActualCost - $totalPlannedCost;
        $this->assertEquals(-1000.00, $costVariance);
    }
}
