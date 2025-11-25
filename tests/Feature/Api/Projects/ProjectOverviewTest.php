<?php declare(strict_types=1);

namespace Tests\Feature\Api\Projects;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Project Overview API
 * 
 * Round 67: Project Overview Cockpit
 * 
 * Tests that project overview endpoint returns combined project summary,
 * financial data, and task metrics with proper tenant isolation and RBAC.
 * 
 * @group projects
 * @group overview
 */
class ProjectOverviewTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private User $userWithoutPermission;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(67890);
        
        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create users with permissions
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'userA@test.com',
        ]);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email' => 'userB@test.com',
        ]);
        $this->userWithoutPermission = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'noperm@test.com',
        ]);
        
        // Attach users to tenants with appropriate roles
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'pm']); // PM has tenant.view_projects
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'pm']);
        $this->userWithoutPermission->tenants()->attach($this->tenantA->id, ['role' => 'member']); // Member may not have tenant.view_projects
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test happy path - same tenant, has permission
     */
    public function test_overview_returns_complete_data(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
            'client_id' => $client->id,
            'owner_id' => $this->userA->id,
            'status' => 'active',
            'priority' => 'high',
            'risk_level' => 'medium',
            'start_date' => Carbon::now()->subDays(30),
            'end_date' => Carbon::now()->addDays(30),
        ]);

        // Create contracts with budget and expenses
        $contract1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CT-001',
            'total_value' => 10000.00,
            'currency' => 'USD',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        $contract2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CT-002',
            'total_value' => 5000.00,
            'currency' => 'USD',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Budget lines
        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'total_amount' => 12000.00, // Over budget
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'total_amount' => 3000.00,
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Expenses (overrun)
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract1->id,
            'amount' => 11000.00, // Overrun: 11000 - 10000 = 1000
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract2->id,
            'amount' => 4000.00, // No overrun (4000 < 5000)
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create tasks with different statuses
        // 1 backlog, 1 in_progress (base), 1 blocked, 1 done, 1 overdue (in_progress), 1 due_soon (in_progress)
        $today = Carbon::today();
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'backlog',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'in_progress',
        ]);
        
        // Create blocked task with assignee
        $blockedTask = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'blocked',
            'priority' => 'urgent',
            'assignee_id' => $this->userA->id,
        ]);
        
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
        ]);
        
        // Create overdue task with assignee
        $overdueTask = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'name' => 'Overdue Task 1',
            'status' => 'in_progress',
            'end_date' => $today->copy()->subDays(1), // Overdue
            'priority' => 'high',
            'assignee_id' => $this->userA->id,
        ]);
        
        // Create due soon task
        $dueSoonTask = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'name' => 'Task due soon',
            'status' => 'in_progress',
            'end_date' => $today->copy()->addDays(2), // Due soon
            'priority' => 'normal',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Check project summary
        $this->assertArrayHasKey('project', $data);
        $this->assertEquals($project->id, $data['project']['id']);
        $this->assertEquals('PRJ-001', $data['project']['code']);
        $this->assertEquals('Test Project', $data['project']['name']);
        $this->assertEquals('active', $data['project']['status']);
        $this->assertNotNull($data['project']['client']);
        $this->assertEquals($client->id, $data['project']['client']['id']);

        // Check financials
        $this->assertArrayHasKey('financials', $data);
        $this->assertTrue($data['financials']['has_financial_data']);
        $this->assertEquals(2, $data['financials']['contracts_count']);
        $this->assertEquals(15000.00, $data['financials']['contracts_value_total']);
        $this->assertEquals(15000.00, $data['financials']['budget_total']); // 12000 + 3000
        $this->assertEquals(15000.00, $data['financials']['actual_total']); // 11000 + 4000
        $this->assertEquals(1000.00, $data['financials']['overrun_amount_total']); // Only contract1 overruns
        $this->assertEquals(1, $data['financials']['over_budget_contracts_count']); // contract1
        $this->assertEquals(1, $data['financials']['overrun_contracts_count']); // contract1
        $this->assertEquals('USD', $data['financials']['currency']);

        // Check tasks
        $this->assertArrayHasKey('tasks', $data);
        $this->assertEquals(6, $data['tasks']['total']);
        $this->assertEquals(1, $data['tasks']['by_status']['backlog']);
        $this->assertEquals(3, $data['tasks']['by_status']['in_progress']); // 1 base + 1 overdue + 1 due_soon
        $this->assertEquals(1, $data['tasks']['by_status']['blocked']);
        $this->assertEquals(1, $data['tasks']['by_status']['done']);
        $this->assertEquals(1, $data['tasks']['overdue']);
        $this->assertEquals(1, $data['tasks']['due_soon']);

        // Check key_tasks structure
        $this->assertArrayHasKey('key_tasks', $data['tasks']);
        $keyTasks = $data['tasks']['key_tasks'];
        $this->assertArrayHasKey('overdue', $keyTasks);
        $this->assertArrayHasKey('due_soon', $keyTasks);
        $this->assertArrayHasKey('blocked', $keyTasks);

        // Check overdue key tasks
        $this->assertNotEmpty($keyTasks['overdue']);
        $firstOverdue = $keyTasks['overdue'][0];
        $this->assertArrayHasKey('id', $firstOverdue);
        $this->assertArrayHasKey('name', $firstOverdue);
        $this->assertArrayHasKey('status', $firstOverdue);
        $this->assertArrayHasKey('priority', $firstOverdue);
        $this->assertArrayHasKey('end_date', $firstOverdue);
        $this->assertArrayHasKey('assignee', $firstOverdue);
        $this->assertEquals('Overdue Task 1', $firstOverdue['name']);

        // Check due soon key tasks
        $this->assertNotEmpty($keyTasks['due_soon']);
        $firstDueSoon = $keyTasks['due_soon'][0];
        $this->assertArrayHasKey('id', $firstDueSoon);
        $this->assertArrayHasKey('name', $firstDueSoon);
        $this->assertEquals('Task due soon', $firstDueSoon['name']);

        // Check blocked key tasks
        $this->assertNotEmpty($keyTasks['blocked']);
        $firstBlocked = $keyTasks['blocked'][0];
        $this->assertArrayHasKey('id', $firstBlocked);
        $this->assertArrayHasKey('name', $firstBlocked);
        $this->assertArrayHasKey('status', $firstBlocked);
        $this->assertEquals('blocked', $firstBlocked['status']);
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_overview_respects_tenant_isolation(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A',
            'name' => 'Tenant A Project',
        ]);

        Sanctum::actingAs($this->userB);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson("/api/v1/app/projects/{$projectA->id}/overview");

        // Should return 404 (not found) due to tenant isolation
        $response->assertStatus(404);
    }

    /**
     * Test permission required
     */
    public function test_overview_requires_permission(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        Sanctum::actingAs($this->userWithoutPermission);
        $token = $this->userWithoutPermission->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        // Should return 403 (forbidden) due to missing permission
        $response->assertStatus(403);
    }

    /**
     * Test edge case: no contracts / no tasks
     */
    public function test_overview_with_no_contracts_and_no_tasks(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Empty Project',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Check financials - should have has_financial_data = false
        $this->assertArrayHasKey('financials', $data);
        $this->assertFalse($data['financials']['has_financial_data']);
        $this->assertEquals(0, $data['financials']['contracts_count']);
        $this->assertNull($data['financials']['contracts_value_total']);
        $this->assertNull($data['financials']['budget_total']);
        $this->assertNull($data['financials']['actual_total']);
        $this->assertNull($data['financials']['overrun_amount_total']);

        // Check tasks - should have total = 0
        $this->assertArrayHasKey('tasks', $data);
        $this->assertEquals(0, $data['tasks']['total']);
        $this->assertEquals(0, $data['tasks']['overdue']);
        $this->assertEquals(0, $data['tasks']['due_soon']);

        // Check key_tasks - should be empty arrays
        $this->assertArrayHasKey('key_tasks', $data['tasks']);
        $keyTasks = $data['tasks']['key_tasks'];
        $this->assertIsArray($keyTasks['overdue']);
        $this->assertIsArray($keyTasks['due_soon']);
        $this->assertIsArray($keyTasks['blocked']);
        $this->assertCount(0, $keyTasks['overdue']);
        $this->assertCount(0, $keyTasks['due_soon']);
        $this->assertCount(0, $keyTasks['blocked']);
    }

    /**
     * Test edge case: key_tasks empty when no tasks
     */
    public function test_overview_key_tasks_empty_when_no_tasks(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Empty Project',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Check key_tasks structure exists
        $this->assertArrayHasKey('key_tasks', $data['tasks']);
        $keyTasks = $data['tasks']['key_tasks'];
        $this->assertIsArray($keyTasks['overdue']);
        $this->assertIsArray($keyTasks['due_soon']);
        $this->assertIsArray($keyTasks['blocked']);
        $this->assertCount(0, $keyTasks['overdue']);
        $this->assertCount(0, $keyTasks['due_soon']);
        $this->assertCount(0, $keyTasks['blocked']);
    }

    /**
     * Test that blocked tasks with null end_date still appear in key_tasks.blocked
     * and are NOT counted in overdue/due_soon
     */
    public function test_overview_blocked_tasks_can_have_null_end_date(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        // Create a blocked task with null end_date
        $blockedTask = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'blocked',
            'priority' => 'high',
            'end_date' => null,
        ]);

        // Create an overdue task to ensure counts work
        $today = Carbon::today();
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'in_progress',
            'end_date' => $today->copy()->subDays(1),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Check that blocked task appears in key_tasks.blocked
        $keyTasks = $data['tasks']['key_tasks'];
        $this->assertNotEmpty($keyTasks['blocked']);
        $blockedTaskInResponse = collect($keyTasks['blocked'])->firstWhere('id', (string) $blockedTask->id);
        $this->assertNotNull($blockedTaskInResponse, 'Blocked task should appear in key_tasks.blocked');
        $this->assertNull($blockedTaskInResponse['end_date'], 'Blocked task end_date should be null');

        // Check that blocked task is NOT in overdue or due_soon
        $overdueTaskIds = collect($keyTasks['overdue'])->pluck('id')->toArray();
        $dueSoonTaskIds = collect($keyTasks['due_soon'])->pluck('id')->toArray();
        $this->assertNotContains((string) $blockedTask->id, $overdueTaskIds, 'Blocked task should not be in overdue');
        $this->assertNotContains((string) $blockedTask->id, $dueSoonTaskIds, 'Blocked task should not be in due_soon');

        // Check that overdue count is 1 (only the in_progress task), not 2
        $this->assertEquals(1, $data['tasks']['overdue'], 'Overdue count should be 1, not including blocked task');
        $this->assertEquals(0, $data['tasks']['due_soon'], 'Due soon count should not include blocked task');
    }

    /**
     * Test that done and canceled tasks are excluded from overdue and due_soon
     */
    public function test_overview_excludes_done_and_canceled_tasks_from_overdue_and_due_soon(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $today = Carbon::today();

        // Task A: done with overdue end_date
        $doneTask = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
            'end_date' => $today->copy()->subDays(5), // Overdue
        ]);

        // Task B: canceled with due_soon end_date
        $canceledTask = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'canceled',
            'end_date' => $today->copy()->addDays(2), // Due soon
        ]);

        // Create actual overdue task (in_progress) to verify counts
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'in_progress',
            'end_date' => $today->copy()->subDays(1),
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Check that overdue count is 1 (only in_progress), not including done task
        $this->assertEquals(1, $data['tasks']['overdue'], 'Overdue count should not include done task');

        // Check that due_soon count is 0, not including canceled task
        $this->assertEquals(0, $data['tasks']['due_soon'], 'Due soon count should not include canceled task');

        // Check key_tasks
        $keyTasks = $data['tasks']['key_tasks'];
        $overdueTaskIds = collect($keyTasks['overdue'])->pluck('id')->toArray();
        $dueSoonTaskIds = collect($keyTasks['due_soon'])->pluck('id')->toArray();

        $this->assertNotContains((string) $doneTask->id, $overdueTaskIds, 'Done task should not be in overdue key_tasks');
        $this->assertNotContains((string) $canceledTask->id, $dueSoonTaskIds, 'Canceled task should not be in due_soon key_tasks');
    }

    /**
     * Test that key_tasks are limited to 5 and sorted correctly
     */
    public function test_overview_key_tasks_are_limited_to_five_and_sorted_correctly(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
        ]);

        $today = Carbon::today();

        // Create 6+ overdue tasks with different end_dates
        $overdueTasks = [];
        for ($i = 0; $i < 6; $i++) {
            $overdueTasks[] = Task::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'project_id' => $project->id,
                'status' => 'in_progress',
                'end_date' => $today->copy()->subDays(10 - $i), // Different dates: today-10, today-9, ..., today-5
                'name' => "Overdue Task {$i}",
            ]);
        }

        // Create 6+ blocked tasks with different priorities
        $blockedTasks = [];
        $priorities = ['urgent', 'high', 'normal', 'low', null, 'urgent']; // Mix of priorities including null
        foreach ($priorities as $index => $priority) {
            $blockedTasks[] = Task::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'project_id' => $project->id,
                'status' => 'blocked',
                'priority' => $priority,
                'end_date' => $index < 3 ? $today->copy()->addDays($index) : null, // Some with dates, some null
                'name' => "Blocked Task {$index}",
            ]);
        }

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        $keyTasks = $data['tasks']['key_tasks'];

        // Check overdue: should be limited to 5 and sorted by end_date ASC (earliest first)
        $this->assertCount(5, $keyTasks['overdue'], 'Overdue key_tasks should be limited to 5');
        $overdueEndDates = collect($keyTasks['overdue'])->pluck('end_date')->filter()->toArray();
        $sortedOverdueEndDates = $overdueEndDates;
        sort($sortedOverdueEndDates);
        $this->assertEquals($sortedOverdueEndDates, $overdueEndDates, 'Overdue tasks should be sorted by end_date ASC');

        // Check that the first overdue task has the earliest date (today-10)
        $firstOverdueEndDate = $keyTasks['overdue'][0]['end_date'];
        $expectedEarliestDate = $today->copy()->subDays(10)->toDateString();
        $this->assertEquals($expectedEarliestDate, $firstOverdueEndDate, 'First overdue task should have earliest end_date');

        // Check blocked: should be limited to 5 and sorted by priority (urgent -> high -> normal -> low -> else), then end_date
        $this->assertCount(5, $keyTasks['blocked'], 'Blocked key_tasks should be limited to 5');

        // Check priority order: first should be urgent (if exists)
        $blockedPriorities = collect($keyTasks['blocked'])->pluck('priority')->toArray();
        $firstBlockedPriority = $blockedPriorities[0];
        $this->assertEquals('urgent', $firstBlockedPriority, 'First blocked task should have urgent priority');

        // Verify priority ordering: urgent tasks come before high, high before normal, etc.
        $priorityOrder = ['urgent' => 1, 'high' => 2, 'normal' => 3, 'low' => 4];
        $previousPriorityValue = 0;
        foreach ($blockedPriorities as $priority) {
            $currentPriorityValue = $priorityOrder[$priority] ?? 5; // Unknown/null = 5
            $this->assertGreaterThanOrEqual($previousPriorityValue, $currentPriorityValue, 
                'Blocked tasks should be sorted by priority (urgent -> high -> normal -> low -> else)');
            $previousPriorityValue = $currentPriorityValue;
        }
    }

    /**
     * Test health summary - shape & basic mapping
     * 
     * Round 70: Project Health Summary
     */
    public function test_overview_includes_health_summary_with_expected_shape(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
            'client_id' => $client->id,
            'owner_id' => $this->userA->id,
        ]);

        // Create tasks: 2 done, 1 blocked, 1 backlog, 0 overdue
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'blocked',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'backlog',
        ]);

        // Create contract with budget
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CT-001',
            'total_value' => 1000000.00,
            'currency' => 'USD',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'total_amount' => 1000000.00,
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // No expenses (overrun = 0)
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Check health exists
        $this->assertArrayHasKey('health', $data);
        $health = $data['health'];

        // Check all required keys
        $this->assertArrayHasKey('tasks_completion_rate', $health);
        $this->assertArrayHasKey('blocked_tasks_ratio', $health);
        $this->assertArrayHasKey('overdue_tasks', $health);
        $this->assertArrayHasKey('schedule_status', $health);
        $this->assertArrayHasKey('cost_status', $health);
        $this->assertArrayHasKey('cost_overrun_percent', $health);
        $this->assertArrayHasKey('overall_status', $health);

        // Check values
        $this->assertEquals(0, $health['overdue_tasks']);
        $this->assertEquals('on_track', $health['schedule_status']); // overdue=0, total>0
        $this->assertEquals('on_budget', $health['cost_status']); // overrun=0
        $this->assertEquals('good', $health['overall_status']);

        // Check completion rate: 2 done / (4 total - 0 canceled) = 0.5
        $this->assertNotNull($health['tasks_completion_rate']);
        $this->assertGreaterThanOrEqual(0.49, $health['tasks_completion_rate']);
        $this->assertLessThanOrEqual(0.51, $health['tasks_completion_rate']);

        // Check blocked ratio: 1 blocked / 4 = 0.25
        $this->assertNotNull($health['blocked_tasks_ratio']);
        $this->assertGreaterThanOrEqual(0.24, $health['blocked_tasks_ratio']);
        $this->assertLessThanOrEqual(0.26, $health['blocked_tasks_ratio']);
    }

    /**
     * Test health summary - no tasks + no financials → warning
     * 
     * Round 70: Project Health Summary
     */
    public function test_overview_health_handles_no_tasks_and_no_financial_data(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Empty Project',
        ]);

        // No tasks, no contracts
        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('health', $data);
        $health = $data['health'];

        // Check null values
        $this->assertNull($health['tasks_completion_rate']);
        $this->assertNull($health['blocked_tasks_ratio']);
        $this->assertEquals(0, $health['overdue_tasks']);
        $this->assertEquals('no_tasks', $health['schedule_status']);
        $this->assertEquals('no_data', $health['cost_status']);
        $this->assertNull($health['cost_overrun_percent']);
        $this->assertEquals('warning', $health['overall_status']);
    }

    /**
     * Test health summary - delayed + over_budget → critical
     * 
     * Round 70: Project Health Summary
     */
    public function test_overview_health_marks_project_as_critical_when_delayed_and_over_budget(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Client',
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
            'name' => 'Critical Project',
            'client_id' => $client->id,
            'owner_id' => $this->userA->id,
        ]);

        $today = Carbon::today();

        // Create tasks: total >= 5, overdue >= 4, done 1-2
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'status' => 'done',
        ]);

        // Create 4+ overdue tasks
        for ($i = 0; $i < 4; $i++) {
            Task::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'project_id' => $project->id,
                'status' => 'in_progress',
                'end_date' => $today->copy()->subDays($i + 1), // Overdue
            ]);
        }

        // Create contract with budget
        $contract = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CT-001',
            'total_value' => 1000000.00,
            'currency' => 'USD',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        ContractBudgetLine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'total_amount' => 1000000.00,
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create expense that causes > 10% overrun
        // Overrun = 150000, base = 1000000, percent = 15% (> 10%)
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contract->id,
            'amount' => 1150000.00, // Overrun: 1150000 - 1000000 = 150000 (15%)
            'status' => 'recorded',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson("/api/v1/app/projects/{$project->id}/overview");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('health', $data);
        $health = $data['health'];

        // Check critical status
        $this->assertEquals('delayed', $health['schedule_status']); // overdue > 3
        $this->assertEquals('over_budget', $health['cost_status']); // overrun > 10%
        $this->assertEquals('critical', $health['overall_status']);

        // Check overrun percent
        $this->assertNotNull($health['cost_overrun_percent']);
        $this->assertGreaterThan(10, $health['cost_overrun_percent']);
    }
}

