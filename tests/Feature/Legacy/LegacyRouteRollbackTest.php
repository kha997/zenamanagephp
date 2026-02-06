<?php declare(strict_types=1);

namespace Tests\Feature\Legacy;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Services\LegacyRouteMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LegacyRouteRollbackTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;
    protected $monitoringService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create admin user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin'
        ]);
        
        $this->monitoringService = app(LegacyRouteMonitoringService::class);
    }

    /**
     * Test emergency rollback procedure
     */
    public function test_emergency_rollback_procedure()
    {
        $this->apiAs($this->user, $this->tenant);

        // Record some usage before rollback
        $this->monitoringService->recordUsage('/dashboard', '/app/dashboard', [
            'user_agent' => 'Test Agent',
            'ip' => '127.0.0.1'
        ]);

        // Verify usage was recorded
        $stats = $this->monitoringService->getUsageStats('/dashboard');
        $this->assertEquals(1, $stats['total_usage']);

        // Simulate emergency rollback by clearing monitoring data
        $clearedEntries = $this->monitoringService->clearOldData(0);
        $this->assertGreaterThan(0, $clearedEntries);

        // Verify data was cleared
        $statsAfterRollback = $this->monitoringService->getUsageStats('/dashboard');
        $this->assertEquals(0, $statsAfterRollback['total_usage']);
    }

    /**
     * Test phased rollback procedure
     */
    public function test_phased_rollback_procedure()
    {
        $this->apiAs($this->user, $this->tenant);

        // Get current migration phase stats
        $phaseStats = $this->monitoringService->getMigrationPhaseStats();
        $this->assertArrayHasKey('phase_distribution', $phaseStats);
        $this->assertArrayHasKey('migration_progress', $phaseStats);

        // Simulate phased rollback by checking phase distribution
        $phaseDistribution = $phaseStats['phase_distribution'];
        $this->assertIsArray($phaseDistribution);
        $this->assertArrayHasKey('announce', $phaseDistribution);
        $this->assertArrayHasKey('redirect', $phaseDistribution);
        $this->assertArrayHasKey('remove', $phaseDistribution);

        // Verify migration progress calculation
        $migrationProgress = $phaseStats['migration_progress'];
        $this->assertArrayHasKey('completion_percentage', $migrationProgress);
        $this->assertIsNumeric($migrationProgress['completion_percentage']);
    }

    /**
     * Test rollback monitoring endpoints
     */
    public function test_rollback_monitoring_endpoints()
    {
        $this->apiAs($this->user, $this->tenant);

        // Test usage stats endpoint
        $response = $this->getJson('/api/v1/legacy-routes/usage');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'routes',
                'summary'
            ]
        ]);

        // Test migration phase endpoint
        $response = $this->getJson('/api/v1/legacy-routes/migration-phase');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'current_date',
                'phase_distribution',
                'total_routes',
                'migration_progress'
            ]
        ]);

        // Test report generation endpoint
        $response = $this->getJson('/api/v1/legacy-routes/report');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'report_type',
                'generated_at',
                'usage_statistics',
                'migration_phase_statistics',
                'recommendations'
            ]
        ]);
    }

    /**
     * Test rollback data cleanup
     */
    public function test_rollback_data_cleanup()
    {
        $this->apiAs($this->user, $this->tenant);

        // Record some test data
        $this->monitoringService->recordUsage('/dashboard', '/app/dashboard');
        $this->monitoringService->recordUsage('/projects', '/app/projects');
        $this->monitoringService->recordUsage('/tasks', '/app/tasks');

        // Verify data exists
        $stats = $this->monitoringService->getAllUsageStats();
        $this->assertGreaterThan(0, $stats['summary']['total_legacy_usage']);

        // Test cleanup endpoint
        $response = $this->postJson('/api/v1/legacy-routes/cleanup', [
            'days_to_keep' => 0
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'cleared_entries',
                'days_kept'
            ]
        ]);

        // Verify data was cleared
        $statsAfterCleanup = $this->monitoringService->getAllUsageStats();
        $this->assertEquals(0, $statsAfterCleanup['summary']['total_legacy_usage']);
    }

    /**
     * Test rollback authorization
     */
    public function test_rollback_authorization()
    {
        // Test without authentication
        $response = $this->getJson('/api/v1/legacy-routes/usage');
        $response->assertStatus(401);

        // Test with non-admin user
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member'
        ]);

        $this->apiAs($member, $this->tenant);

        $response = $this->getJson('/api/v1/legacy-routes/usage');
        $response->assertStatus(403);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);
    }

    /**
     * Test rollback error handling
     */
    public function test_rollback_error_handling()
    {
        $this->apiAs($this->user, $this->tenant);

        // Test invalid cleanup request
        $response = $this->postJson('/api/v1/legacy-routes/cleanup', [
            'days_to_keep' => 'invalid'
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);
    }

    /**
     * Test rollback performance
     */
    public function test_rollback_performance()
    {
        $this->apiAs($this->user, $this->tenant);

        // Record multiple usage entries
        for ($i = 0; $i < 100; $i++) {
            $this->monitoringService->recordUsage('/dashboard', '/app/dashboard');
        }

        // Test cleanup performance
        $startTime = microtime(true);
        
        $response = $this->postJson('/api/v1/legacy-routes/cleanup', [
            'days_to_keep' => 0
        ]);
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        
        // Performance assertion: cleanup should complete within 1 second
        $this->assertLessThan(1000, $executionTime, 
            "Rollback cleanup should complete within 1 second, took {$executionTime}ms");
    }

    /**
     * Test rollback data integrity
     */
    public function test_rollback_data_integrity()
    {
        $this->apiAs($this->user, $this->tenant);

        // Record usage with metadata
        $metadata = [
            'user_agent' => 'Test Agent',
            'ip' => '127.0.0.1',
            'referer' => 'https://example.com'
        ];

        $this->monitoringService->recordUsage('/dashboard', '/app/dashboard', $metadata);

        // Verify data integrity
        $stats = $this->monitoringService->getUsageStats('/dashboard');
        $this->assertEquals(1, $stats['total_usage']);
        $this->assertEquals('/dashboard', $stats['legacy_path']);

        // Test rollback preserves data structure
        $allStats = $this->monitoringService->getAllUsageStats();
        $this->assertArrayHasKey('routes', $allStats);
        $this->assertArrayHasKey('summary', $allStats);
        $this->assertArrayHasKey('generated_at', $allStats);
    }

    /**
     * Test rollback recommendations
     */
    public function test_rollback_recommendations()
    {
        $this->apiAs($this->user, $this->tenant);

        // Generate high usage to trigger recommendations
        for ($i = 0; $i < 150; $i++) {
            $this->monitoringService->recordUsage('/dashboard', '/app/dashboard');
        }

        // Generate report with recommendations
        $report = $this->monitoringService->generateUsageReport();
        
        $this->assertArrayHasKey('recommendations', $report);
        $this->assertIsArray($report['recommendations']);
        
        // Check for high usage warning
        $highUsageWarning = collect($report['recommendations'])
            ->first(fn($rec) => $rec['type'] === 'high_usage_warning');
        
        $this->assertNotNull($highUsageWarning);
        $this->assertEquals('high', $highUsageWarning['priority']);
    }

    /**
     * Test rollback monitoring service methods
     */
    public function test_rollback_monitoring_service_methods()
    {
        // Test recordUsage method
        $this->monitoringService->recordUsage('/dashboard', '/app/dashboard');
        
        // Test getUsageStats method
        $stats = $this->monitoringService->getUsageStats('/dashboard');
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('legacy_path', $stats);
        $this->assertArrayHasKey('total_usage', $stats);
        
        // Test getAllUsageStats method
        $allStats = $this->monitoringService->getAllUsageStats();
        $this->assertIsArray($allStats);
        $this->assertArrayHasKey('routes', $allStats);
        $this->assertArrayHasKey('summary', $allStats);
        
        // Test getMigrationPhaseStats method
        $phaseStats = $this->monitoringService->getMigrationPhaseStats();
        $this->assertIsArray($phaseStats);
        $this->assertArrayHasKey('current_date', $phaseStats);
        $this->assertArrayHasKey('phase_distribution', $phaseStats);
        
        // Test generateUsageReport method
        $report = $this->monitoringService->generateUsageReport();
        $this->assertIsArray($report);
        $this->assertArrayHasKey('report_type', $report);
        $this->assertArrayHasKey('generated_at', $report);
        
        // Test clearOldData method
        $cleared = $this->monitoringService->clearOldData(0);
        $this->assertIsInt($cleared);
    }
}
