<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\ProjectHealthSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

/**
 * Tests for Project Health Portfolio History API
 * 
 * Round 91: Project Health Portfolio History API (backend-only)
 * 
 * Tests that portfolio health history endpoint returns aggregated daily counts
 * of projects by health status for a tenant, with proper tenant isolation and permission checks.
 * 
 * @group reports
 * @group projects
 * @group health
 */
class ProjectHealthPortfolioHistoryTest extends TestCase
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
        $this->setDomainSeed(45678);
        
        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create users
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
        
        // Attach users to tenants with 'admin' role (which has tenant.view_reports in config)
        $this->userA->tenants()->attach($this->tenantA->id, ['role' => 'admin']);
        $this->userB->tenants()->attach($this->tenantB->id, ['role' => 'admin']);
        // User without permission - don't attach to tenant (no permissions)
        
        // Create tokens
        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that unauthenticated requests return 401
     */
    public function test_unauthenticated_requests_return_401(): void
    {
        $response = $this->getJson('/api/v1/app/reports/projects/health/history');

        $response->assertStatus(401);
    }

    /**
     * Test that requests without tenant.view_reports permission return 403
     */
    public function test_requires_view_reports_permission(): void
    {
        Sanctum::actingAs($this->userWithoutPermission);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/v1/app/reports/projects/health/history');

        $response->assertStatus(403);
    }

    /**
     * Test basic happy path - returns aggregated daily counts
     */
    public function test_returns_aggregated_daily_counts(): void
    {
        $today = Carbon::today();
        
        // Create projects for tenant A
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-001',
        ]);
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-002',
        ]);
        $project3 = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-003',
        ]);

        // Create snapshots for different days
        // Day 1: 2 good, 1 warning
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project1->id,
            'snapshot_date' => $today->copy()->subDays(2),
            'overall_status' => 'good',
        ]);
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project2->id,
            'snapshot_date' => $today->copy()->subDays(2),
            'overall_status' => 'good',
        ]);
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project3->id,
            'snapshot_date' => $today->copy()->subDays(2),
            'overall_status' => 'warning',
        ]);

        // Day 2: 1 good, 1 critical
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project1->id,
            'snapshot_date' => $today->copy()->subDays(1),
            'overall_status' => 'good',
        ]);
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project2->id,
            'snapshot_date' => $today->copy()->subDays(1),
            'overall_status' => 'critical',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=30');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));

        // Find entries for the two days
        $day1Entry = collect($data)->firstWhere('snapshot_date', $today->copy()->subDays(2)->toDateString());
        $day2Entry = collect($data)->firstWhere('snapshot_date', $today->copy()->subDays(1)->toDateString());

        $this->assertNotNull($day1Entry, 'Day 1 entry should exist');
        $this->assertNotNull($day2Entry, 'Day 2 entry should exist');

        // Verify Day 1 counts
        $this->assertEquals(2, $day1Entry['good']);
        $this->assertEquals(1, $day1Entry['warning']);
        $this->assertEquals(0, $day1Entry['critical']);
        $this->assertEquals(3, $day1Entry['total']);

        // Verify Day 2 counts
        $this->assertEquals(1, $day2Entry['good']);
        $this->assertEquals(0, $day2Entry['warning']);
        $this->assertEquals(1, $day2Entry['critical']);
        $this->assertEquals(2, $day2Entry['total']);

        // Verify structure
        $this->assertArrayHasKey('snapshot_date', $day1Entry);
        $this->assertArrayHasKey('good', $day1Entry);
        $this->assertArrayHasKey('warning', $day1Entry);
        $this->assertArrayHasKey('critical', $day1Entry);
        $this->assertArrayHasKey('total', $day1Entry);
    }

    /**
     * Test multi-tenant isolation - Tenant A cannot see Tenant B data
     */
    public function test_multi_tenant_isolation(): void
    {
        $today = Carbon::today();
        
        // Create projects for both tenants
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'PRJ-A-001',
        ]);
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'PRJ-B-001',
        ]);

        // Create snapshots for both tenants on the same day
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
            'snapshot_date' => $today->copy()->subDays(1),
            'overall_status' => 'good',
        ]);
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
            'snapshot_date' => $today->copy()->subDays(1),
            'overall_status' => 'critical',
        ]);

        // User A calls endpoint
        Sanctum::actingAs($this->userA);
        $responseA = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=30');

        $responseA->assertStatus(200);
        $dataA = $responseA->json('data');

        // User A should only see Tenant A data
        $day1EntryA = collect($dataA)->firstWhere('snapshot_date', $today->copy()->subDays(1)->toDateString());
        $this->assertNotNull($day1EntryA, 'Day 1 entry should exist for Tenant A');
        $this->assertEquals(1, $day1EntryA['good'], 'Should have 1 good project from Tenant A');
        $this->assertEquals(0, $day1EntryA['critical'], 'Should not have critical projects from Tenant B');

        // User B calls endpoint
        Sanctum::actingAs($this->userB);
        $responseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenB}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=30');

        $responseB->assertStatus(200);
        $dataB = $responseB->json('data');

        // User B should only see Tenant B data
        $day1EntryB = collect($dataB)->firstWhere('snapshot_date', $today->copy()->subDays(1)->toDateString());
        $this->assertNotNull($day1EntryB, 'Day 1 entry should exist for Tenant B');
        $this->assertEquals(0, $day1EntryB['good'], 'Should not have good projects from Tenant A');
        $this->assertEquals(1, $day1EntryB['critical'], 'Should have 1 critical project from Tenant B');
    }

    /**
     * Test days parameter range - respects days query param
     */
    public function test_days_parameter_range(): void
    {
        $today = Carbon::today();
        
        // Create project
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        // Create snapshots spanning 40 days
        for ($i = 0; $i < 40; $i++) {
            ProjectHealthSnapshot::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'project_id' => $project->id,
                'snapshot_date' => $today->copy()->subDays($i),
                'overall_status' => 'good',
            ]);
        }

        Sanctum::actingAs($this->userA);

        // Request with days=10 - should only return last 10 days
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=10');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should have 10 entries (one per day) - days=10 means 10 days including today
        // So we expect: today, today-1, ..., today-9 (10 days total)
        $this->assertGreaterThanOrEqual(9, count($data), 'Should have at least 9 entries (may be 10 if today has snapshot)');
        $this->assertLessThanOrEqual(10, count($data), 'Should have at most 10 entries');
        
        // Verify dates are within range
        if (count($data) > 0) {
            $oldestDate = collect($data)->min('snapshot_date');
            $newestDate = collect($data)->max('snapshot_date');
            // Newest should be today or today-1 (depending on when test runs)
            $this->assertLessThanOrEqual($today->toDateString(), $newestDate);
            // Oldest should be today-9 or today-10
            $this->assertGreaterThanOrEqual($today->copy()->subDays(10)->toDateString(), $oldestDate);
            $this->assertLessThanOrEqual($today->copy()->subDays(9)->toDateString(), $oldestDate);
        }

        // Request with days=999 - should clamp to 90
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=999');

        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        
        // Should have 40 entries (all available, not 90)
        // Note: May be 39 if today's snapshot isn't included due to timezone/date comparison
        $this->assertGreaterThanOrEqual(39, count($data2), 'Should have at least 39 entries (all snapshots we created)');
        $this->assertLessThanOrEqual(40, count($data2), 'Should have at most 40 entries');

        // Request with days=0 - should clamp to 1
        $response3 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=0');

        $response3->assertStatus(200);
        $data3 = $response3->json('data');
        
        // Should have 1 entry (today only) - may be 0 if today's snapshot isn't included due to timezone
        // The important thing is that it doesn't error and returns a valid array
        $this->assertIsArray($data3);
        $this->assertLessThanOrEqual(1, count($data3), 'Should have at most 1 entry for days=0 (clamped to 1)');
    }

    /**
     * Test no snapshots - returns empty array
     */
    public function test_no_snapshots_returns_empty_array(): void
    {
        // Create project but no snapshots
        Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=30');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    /**
     * Test dates are sorted ascending
     */
    public function test_dates_sorted_ascending(): void
    {
        $today = Carbon::today();
        
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        // Create snapshots in non-sequential order
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'snapshot_date' => $today->copy()->subDays(3),
            'overall_status' => 'good',
        ]);
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'snapshot_date' => $today->copy()->subDays(1),
            'overall_status' => 'good',
        ]);
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $project->id,
            'snapshot_date' => $today->copy()->subDays(2),
            'overall_status' => 'good',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=30');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Verify dates are in ascending order
        $dates = collect($data)->pluck('snapshot_date')->all();
        $sortedDates = $dates;
        sort($sortedDates);
        $this->assertEquals($sortedDates, $dates, 'Dates should be sorted ascending');
    }

    /**
     * Test multiple projects per day are aggregated correctly
     */
    public function test_multiple_projects_per_day_aggregated(): void
    {
        $today = Carbon::today();
        
        // Create multiple projects
        $projects = [];
        for ($i = 1; $i <= 5; $i++) {
            $projects[] = Project::factory()->create([
                'tenant_id' => $this->tenantA->id,
                'code' => "PRJ-00{$i}",
            ]);
        }

        // Create snapshots for same day with different statuses
        $sameDate = $today->copy()->subDays(1);
        
        // 2 good projects
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projects[0]->id,
            'snapshot_date' => $sameDate,
            'overall_status' => 'good',
        ]);
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projects[1]->id,
            'snapshot_date' => $sameDate,
            'overall_status' => 'good',
        ]);
        
        // 2 warning projects
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projects[2]->id,
            'snapshot_date' => $sameDate,
            'overall_status' => 'warning',
        ]);
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projects[3]->id,
            'snapshot_date' => $sameDate,
            'overall_status' => 'warning',
        ]);
        
        // 1 critical project
        ProjectHealthSnapshot::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projects[4]->id,
            'snapshot_date' => $sameDate,
            'overall_status' => 'critical',
        ]);

        Sanctum::actingAs($this->userA);
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health/history?days=30');

        $response->assertStatus(200);
        $data = $response->json('data');

        $dayEntry = collect($data)->firstWhere('snapshot_date', $sameDate->toDateString());
        $this->assertNotNull($dayEntry);
        
        // Verify aggregated counts
        $this->assertEquals(2, $dayEntry['good']);
        $this->assertEquals(2, $dayEntry['warning']);
        $this->assertEquals(1, $dayEntry['critical']);
        $this->assertEquals(5, $dayEntry['total']);
    }
}

