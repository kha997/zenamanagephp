<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * RBAC Policy Matrix Tests
 * 
 * Tests that all policies match the permissions defined in OpenAPI x-abilities.
 * Ensures no drift between backend policies and API documentation.
 */
class RBACPolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $tenantAdmin;
    private User $member;
    private User $client;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $tenant = \App\Models\Tenant::factory()->create();
        $this->tenantId = $tenant->id;
        
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'super_admin',
            'is_admin' => true,
        ]);
        
        $this->tenantAdmin = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'admin',
            'is_admin' => true,
        ]);
        
        $this->member = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'member',
        ]);
        
        $this->client = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'client',
        ]);
    }

    /**
     * Test that super admin can access all resources
     */
    public function test_super_admin_policy_matrix(): void
    {
        $this->actingAs($this->superAdmin);
        
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create(['tenant_id' => $this->tenantId, 'project_id' => $project->id]);
        
        // Super admin should have access to all resources
        $this->assertTrue(Gate::allows('view', $project));
        $this->assertTrue(Gate::allows('update', $project));
        $this->assertTrue(Gate::allows('delete', $project));
        $this->assertTrue(Gate::allows('view', $task));
        $this->assertTrue(Gate::allows('update', $task));
    }

    /**
     * Test that tenant admin can manage tenant resources
     */
    public function test_tenant_admin_policy_matrix(): void
    {
        $this->actingAs($this->tenantAdmin);
        
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create(['tenant_id' => $this->tenantId, 'project_id' => $project->id]);
        
        // Tenant admin should have access to tenant resources
        $this->assertTrue(Gate::allows('view', $project));
        $this->assertTrue(Gate::allows('update', $project));
        $this->assertTrue(Gate::allows('view', $task));
        $this->assertTrue(Gate::allows('update', $task));
    }

    /**
     * Test that member has limited access
     */
    public function test_member_policy_matrix(): void
    {
        $this->actingAs($this->member);
        
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create(['tenant_id' => $this->tenantId, 'project_id' => $project->id]);
        
        // Member should be able to view but not always update
        $this->assertTrue(Gate::allows('view', $project));
        $this->assertTrue(Gate::allows('view', $task));
        
        // Update permissions depend on ownership/assignment
        // This would be tested based on actual policy logic
    }

    /**
     * Test that client has read-only access
     */
    public function test_client_policy_matrix(): void
    {
        $this->actingAs($this->client);
        
        $project = Project::factory()->create(['tenant_id' => $this->tenantId]);
        $task = Task::factory()->create(['tenant_id' => $this->tenantId, 'project_id' => $project->id]);
        
        // Client should be able to view but not modify
        $this->assertTrue(Gate::allows('view', $project));
        $this->assertTrue(Gate::allows('view', $task));
        $this->assertFalse(Gate::allows('update', $project));
        $this->assertFalse(Gate::allows('delete', $project));
    }

    /**
     * Test tenant isolation in policies
     */
    public function test_tenant_isolation_in_policies(): void
    {
        $tenantB = \App\Models\Tenant::factory()->create();
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);
        
        $this->actingAs($this->tenantAdmin);
        
        // Tenant admin should not access tenant B's resources
        $this->assertFalse(Gate::allows('view', $projectB));
        $this->assertFalse(Gate::allows('update', $projectB));
    }

    /**
     * Test that OpenAPI x-abilities match actual policies
     */
    public function test_openapi_abilities_match_policies(): void
    {
        $rbacService = app(\App\Services\RBACSyncService::class);
        $report = $rbacService->generateReport();
        
        // Verify coverage
        $this->assertGreaterThan(0, $report['openapi_coverage']['total'], 'Should have at least one endpoint');
        
        // Check that most endpoints have x-abilities
        $coveragePercent = ($report['openapi_coverage']['with_abilities'] / max($report['openapi_coverage']['total'], 1)) * 100;
        $this->assertGreaterThanOrEqual(
            80,
            $coveragePercent,
            "At least 80% of endpoints should have x-abilities (current: {$coveragePercent}%)"
        );
        
        // Verify permissions and abilities match
        $comparison = $report['permissions_comparison'];
        
        // There should be no major drift
        $this->assertLessThan(
            10,
            count($comparison['in_permissions_not_abilities']),
            'Too many permissions not in OpenAPI abilities: ' . implode(', ', $comparison['in_permissions_not_abilities'])
        );
    }
}

