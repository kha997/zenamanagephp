<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tenant;
use App\Models\Project;
use App\Models\ProjectHealthSnapshot;
use App\Models\User;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Carbon\Carbon;

/**
 * Tests for Project Health Snapshot Daily Command
 * 
 * Round 88: Daily Project Health Snapshots (command + schedule)
 * 
 * Tests that the daily snapshot command:
 * - Creates snapshots for all projects in a tenant
 * - Is idempotent (can run multiple times per day)
 * - Respects tenant isolation
 * - Supports dry-run mode
 * 
 * @group projects
 * @group health
 * @group reports
 * @group console
 */
class ProjectHealthSnapshotDailyCommandTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Project $projectA1;
    private Project $projectA2;
    private Project $projectB1;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        $this->setDomainName('project-health-snapshot-daily');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'status' => 'active',
        ]);
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'status' => 'active',
        ]);
        
        // Create client for projects
        $clientA = Client::factory()->create(['tenant_id' => $this->tenantA->id]);
        $clientB = Client::factory()->create(['tenant_id' => $this->tenantB->id]);
        
        // Create projects for tenant A
        $this->projectA1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A1',
            'status' => 'active',
        ]);
        $this->projectA2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A2',
            'status' => 'active',
        ]);
        
        // Create project for tenant B
        $this->projectB1 = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B1',
            'status' => 'active',
        ]);
        
        // Create contracts and expenses to ensure ProjectOverviewService can calculate health
        $contractA1 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA1->id,
            'client_id' => $clientA->id,
            'status' => 'active',
            'total_value' => 1000000.00,
        ]);
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractA1->id,
            'amount' => 500000.00,
        ]);
        
        $contractA2 = Contract::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA2->id,
            'client_id' => $clientA->id,
            'status' => 'active',
            'total_value' => 2000000.00,
        ]);
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'contract_id' => $contractA2->id,
            'amount' => 1000000.00,
        ]);
        
        $contractB1 = Contract::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB1->id,
            'client_id' => $clientB->id,
            'status' => 'active',
            'total_value' => 1500000.00,
        ]);
        ContractExpense::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'contract_id' => $contractB1->id,
            'amount' => 750000.00,
        ]);
        
        // Create some tasks to ensure health metrics are calculated
        Task::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA1->id,
            'status' => 'completed',
        ]);
        Task::factory()->count(2)->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA2->id,
            'status' => 'in_progress',
        ]);
        Task::factory()->count(1)->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB1->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test snapshot all projects for one tenant
     */
    public function test_snapshot_all_projects_for_one_tenant(): void
    {
        $today = now(config('app.timezone'))->toDateString();
        
        // Initially no snapshots
        $this->assertEquals(0, ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)->count());
        
        // Run command for tenant A
        $result = Artisan::call('project-health:snapshot-daily', [
            '--tenant' => $this->tenantA->id,
        ]);
        
        $this->assertEquals(0, $result, 'Command should return success');
        
        // Verify snapshots were created for both projects
        $snapshots = ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)
            ->where('snapshot_date', $today)
            ->get();
        
        $this->assertCount(2, $snapshots, 'Should have 2 snapshots for tenant A');
        
        $projectIds = $snapshots->pluck('project_id')->toArray();
        $this->assertContains($this->projectA1->id, $projectIds);
        $this->assertContains($this->projectA2->id, $projectIds);
        
        // Verify snapshot data is present
        foreach ($snapshots as $snapshot) {
            $this->assertNotNull($snapshot->overall_status);
            $this->assertNotNull($snapshot->schedule_status);
            $this->assertNotNull($snapshot->cost_status);
        }
    }

    /**
     * Test idempotency - running twice on same day should not create duplicates
     */
    public function test_idempotency_per_day(): void
    {
        $today = now(config('app.timezone'))->toDateString();
        
        // Run command first time
        Artisan::call('project-health:snapshot-daily', [
            '--tenant' => $this->tenantA->id,
        ]);
        
        $firstRunCount = ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)
            ->where('snapshot_date', $today)
            ->count();
        
        $this->assertEquals(2, $firstRunCount, 'First run should create 2 snapshots');
        
        // Run command second time
        Artisan::call('project-health:snapshot-daily', [
            '--tenant' => $this->tenantA->id,
        ]);
        
        $secondRunCount = ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)
            ->where('snapshot_date', $today)
            ->count();
        
        // Should still be 2 (updated, not duplicated)
        $this->assertEquals(2, $secondRunCount, 'Second run should not create duplicates');
        
        // Verify each project has exactly one snapshot for today
        $projectA1Snapshots = ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)
            ->where('project_id', $this->projectA1->id)
            ->where('snapshot_date', $today)
            ->count();
        
        $projectA2Snapshots = ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)
            ->where('project_id', $this->projectA2->id)
            ->where('snapshot_date', $today)
            ->count();
        
        $this->assertEquals(1, $projectA1Snapshots, 'Project A1 should have exactly 1 snapshot');
        $this->assertEquals(1, $projectA2Snapshots, 'Project A2 should have exactly 1 snapshot');
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_multi_tenant_isolation(): void
    {
        $today = now(config('app.timezone'))->toDateString();
        
        // Run command without --tenant option (all tenants)
        $result = Artisan::call('project-health:snapshot-daily');
        
        $this->assertEquals(0, $result, 'Command should return success');
        
        // Verify tenant A snapshots
        $tenantASnapshots = ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)
            ->where('snapshot_date', $today)
            ->get();
        
        $this->assertCount(2, $tenantASnapshots, 'Tenant A should have 2 snapshots');
        
        // Verify tenant B snapshots
        $tenantBSnapshots = ProjectHealthSnapshot::where('tenant_id', $this->tenantB->id)
            ->where('snapshot_date', $today)
            ->get();
        
        $this->assertCount(1, $tenantBSnapshots, 'Tenant B should have 1 snapshot');
        
        // Verify no cross-tenant contamination
        $allSnapshots = ProjectHealthSnapshot::where('snapshot_date', $today)->get();
        $this->assertCount(3, $allSnapshots, 'Total should be 3 snapshots');
        
        foreach ($tenantASnapshots as $snapshot) {
            $this->assertEquals($this->tenantA->id, $snapshot->tenant_id);
            $this->assertContains($snapshot->project_id, [$this->projectA1->id, $this->projectA2->id]);
        }
        
        foreach ($tenantBSnapshots as $snapshot) {
            $this->assertEquals($this->tenantB->id, $snapshot->tenant_id);
            $this->assertEquals($this->projectB1->id, $snapshot->project_id);
        }
    }

    /**
     * Test dry run behavior
     */
    public function test_dry_run_behavior(): void
    {
        $today = now(config('app.timezone'))->toDateString();
        
        // Initially no snapshots
        $this->assertEquals(0, ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)->count());
        
        // Run command with --dry-run
        $result = Artisan::call('project-health:snapshot-daily', [
            '--tenant' => $this->tenantA->id,
            '--dry-run' => true,
        ]);
        
        $this->assertEquals(0, $result, 'Command should return success');
        
        // Verify no snapshots were created
        $snapshots = ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)
            ->where('snapshot_date', $today)
            ->count();
        
        $this->assertEquals(0, $snapshots, 'Dry run should not create snapshots');
        
        // Verify output contains dry run message
        $output = Artisan::output();
        $this->assertStringContainsString('DRY RUN MODE', $output);
    }

    /**
     * Test command handles non-existent tenant gracefully
     */
    public function test_command_handles_non_existent_tenant(): void
    {
        $nonExistentTenantId = '01HZ0000000000000000000000';
        
        $result = Artisan::call('project-health:snapshot-daily', [
            '--tenant' => $nonExistentTenantId,
        ]);
        
        $this->assertEquals(1, $result, 'Command should return failure for non-existent tenant');
        
        $output = Artisan::output();
        $this->assertStringContainsString('not found', $output);
    }

    /**
     * Test command skips soft-deleted projects
     */
    public function test_command_skips_soft_deleted_projects(): void
    {
        $today = now(config('app.timezone'))->toDateString();
        
        // Soft delete one project
        $this->projectA2->delete();
        
        // Run command
        $result = Artisan::call('project-health:snapshot-daily', [
            '--tenant' => $this->tenantA->id,
        ]);
        
        $this->assertEquals(0, $result, 'Command should return success');
        
        // Verify only non-deleted project has snapshot
        $snapshots = ProjectHealthSnapshot::where('tenant_id', $this->tenantA->id)
            ->where('snapshot_date', $today)
            ->get();
        
        $this->assertCount(1, $snapshots, 'Should have 1 snapshot (deleted project skipped)');
        $this->assertEquals($this->projectA1->id, $snapshots->first()->project_id);
    }

    /**
     * Test that tenant with no projects is handled gracefully
     * 
     * Round 94: Project Health Hardening (schema + caching TTL + snapshot command)
     */
    public function test_tenant_with_no_projects_is_handled_gracefully(): void
    {
        // Create a tenant with no projects
        $tenantNoProjects = Tenant::factory()->create([
            'name' => 'Tenant No Projects',
            'status' => 'active',
        ]);

        // Initially no snapshots
        $this->assertEquals(0, ProjectHealthSnapshot::where('tenant_id', $tenantNoProjects->id)->count());

        // Run command for tenant with no projects
        $result = Artisan::call('project-health:snapshot-daily', [
            '--tenant' => $tenantNoProjects->id,
        ]);

        // Command should exit successfully
        $this->assertEquals(0, $result, 'Command should return success for tenant with no projects');

        // Verify no snapshots were created
        $snapshots = ProjectHealthSnapshot::where('tenant_id', $tenantNoProjects->id)->count();
        $this->assertEquals(0, $snapshots, 'Should have 0 snapshots for tenant with no projects');

        // Verify output does not contain error messages
        // The main requirement is that command exits successfully (exit code 0) and no snapshots are created
        $output = Artisan::output();
        if (!empty($output)) {
            $this->assertStringNotContainsString('error', strtolower($output), 'Output should not contain error messages');
            $this->assertStringNotContainsString('failed', strtolower($output), 'Output should not contain failure messages');
        }
    }
}

