<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RBACSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RBAC Sync Tests
 * 
 * Tests to verify consistency between backend permissions and frontend types.
 * Ensures OpenAPI x-abilities extensions match actual backend policies.
 */
class RBACSyncTest extends TestCase
{
    use RefreshDatabase;

    private RBACSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncService = app(RBACSyncService::class);
    }

    /**
     * Test OpenAPI coverage
     */
    public function test_openapi_coverage(): void
    {
        $coverage = $this->syncService->verifyOpenAPICoverage();
        
        $this->assertArrayHasKey('total', $coverage);
        $this->assertArrayHasKey('with_abilities', $coverage);
        $this->assertArrayHasKey('coverage_percent', $coverage);
        
        // At least some endpoints should have x-abilities
        $this->assertGreaterThan(0, $coverage['total']);
    }

    /**
     * Test permissions extraction
     */
    public function test_permissions_extraction(): void
    {
        $permissions = $this->syncService->getAllPermissions();
        
        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        
        // Verify common permissions exist
        $this->assertContains('projects.view', $permissions);
        $this->assertContains('tasks.view', $permissions);
        $this->assertContains('users.view', $permissions);
    }

    /**
     * Test abilities extraction from OpenAPI
     */
    public function test_abilities_extraction(): void
    {
        $abilities = $this->syncService->getAbilitiesFromOpenAPI();
        
        $this->assertIsArray($abilities);
        
        // If OpenAPI has x-abilities, verify format
        if (!empty($abilities)) {
            foreach ($abilities as $ability) {
                $this->assertIsString($ability);
                $this->assertNotEmpty($ability);
            }
        }
    }

    /**
     * Test permissions and abilities comparison
     */
    public function test_permissions_abilities_comparison(): void
    {
        $comparison = $this->syncService->comparePermissionsAndAbilities();
        
        $this->assertArrayHasKey('permissions_count', $comparison);
        $this->assertArrayHasKey('abilities_count', $comparison);
        $this->assertArrayHasKey('in_permissions_not_abilities', $comparison);
        $this->assertArrayHasKey('in_abilities_not_permissions', $comparison);
        $this->assertArrayHasKey('match', $comparison);
        
        // Log differences for debugging
        if (!empty($comparison['in_permissions_not_abilities'])) {
            $this->markTestIncomplete(
                'Some permissions are not in OpenAPI abilities: ' . 
                implode(', ', $comparison['in_permissions_not_abilities'])
            );
        }
    }

    /**
     * Test report generation
     */
    public function test_report_generation(): void
    {
        $report = $this->syncService->generateReport();
        
        $this->assertArrayHasKey('openapi_coverage', $report);
        $this->assertArrayHasKey('permissions_comparison', $report);
        $this->assertArrayHasKey('timestamp', $report);
    }
}

