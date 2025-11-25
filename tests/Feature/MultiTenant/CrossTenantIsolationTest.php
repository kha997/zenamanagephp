<?php declare(strict_types=1);

namespace Tests\Feature\MultiTenant;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Cross-Tenant Isolation Test
 * 
 * Tests that tenant isolation is properly enforced at the database level.
 * Verifies that:
 * - Tenant A cannot query data of Tenant B
 * - withoutGlobalScope() only works with super-admin
 * - Raw queries are still filtered by tenant_id
 * - Indexes are used efficiently (EXPLAIN plans)
 */
class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantAUser;
    private User $tenantBUser;
    private User $superAdmin;
    private string $tenantAId;
    private string $tenantBId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenants and users
        $this->tenantAId = 'tenant_a_' . uniqid();
        $this->tenantBId = 'tenant_b_' . uniqid();

        $this->tenantAUser = User::factory()->create([
            'tenant_id' => $this->tenantAId,
            'email' => 'user_a@tenant-a.com',
        ]);

        $this->tenantBUser = User::factory()->create([
            'tenant_id' => $this->tenantBId,
            'email' => 'user_b@tenant-b.com',
        ]);

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email' => 'admin@system.com',
            'is_admin' => true,
        ]);

        // Create test data
        Project::factory()->count(3)->create([
            'tenant_id' => $this->tenantAId,
        ]);

        Project::factory()->count(2)->create([
            'tenant_id' => $this->tenantBId,
        ]);

        Task::factory()->count(5)->create([
            'tenant_id' => $this->tenantAId,
            'project_id' => Project::where('tenant_id', $this->tenantAId)->first()->id,
        ]);

        Task::factory()->count(3)->create([
            'tenant_id' => $this->tenantBId,
            'project_id' => Project::where('tenant_id', $this->tenantBId)->first()->id,
        ]);
    }

    /**
     * Test that Tenant A cannot query data of Tenant B using Eloquent
     */
    public function test_tenant_a_cannot_query_tenant_b_data(): void
    {
        Auth::login($this->tenantAUser);

        // Tenant A should only see their own projects
        $projects = Project::all();
        $this->assertCount(3, $projects);
        $this->assertTrue($projects->every(fn($p) => $p->tenant_id === $this->tenantAId));

        // Tenant A should not see Tenant B's projects
        $tenantBProjects = Project::where('tenant_id', $this->tenantBId)->get();
        $this->assertCount(0, $tenantBProjects);

        // Tenant A should only see their own tasks
        $tasks = Task::all();
        $this->assertTrue($tasks->every(fn($t) => $t->tenant_id === $this->tenantAId));
    }

    /**
     * Test that withoutGlobalScope() only works with super-admin
     */
    public function test_without_global_scope_only_works_with_super_admin(): void
    {
        // Regular user cannot bypass tenant scope
        Auth::login($this->tenantAUser);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        Project::withoutGlobalScope('tenant')->get();
    }

    /**
     * Test that super-admin can bypass tenant scope
     */
    public function test_super_admin_can_bypass_tenant_scope(): void
    {
        Auth::login($this->superAdmin);

        // Super admin should see all projects
        $allProjects = Project::withoutGlobalScope('tenant')->get();
        $this->assertGreaterThanOrEqual(5, $allProjects->count());

        // Verify both tenants' data are included
        $tenantAProjects = $allProjects->where('tenant_id', $this->tenantAId);
        $tenantBProjects = $allProjects->where('tenant_id', $this->tenantBId);
        $this->assertGreaterThan(0, $tenantAProjects->count());
        $this->assertGreaterThan(0, $tenantBProjects->count());
    }

    /**
     * Test that raw queries are still filtered by tenant_id
     */
    public function test_raw_queries_are_filtered_by_tenant(): void
    {
        Auth::login($this->tenantAUser);

        // Raw query should still be filtered by tenant_id via Global Scope
        $projects = DB::table('projects')
            ->where('tenant_id', $this->tenantBId)
            ->get();

        // Even with raw query, Global Scope should prevent access
        // However, raw queries bypass Eloquent, so we need to verify manually
        // This test verifies that the application doesn't use raw queries without tenant filtering
        $this->assertCount(0, $projects, 'Raw queries should not bypass tenant isolation');
    }

    /**
     * Test that indexes are used efficiently (EXPLAIN plan)
     */
    public function test_indexes_are_used_for_tenant_queries(): void
    {
        Auth::login($this->tenantAUser);

        // Get EXPLAIN plan for a query
        $explain = DB::select("EXPLAIN SELECT * FROM projects WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10", [
            $this->tenantAId
        ]);

        $this->assertNotEmpty($explain);
        
        // Check that an index is used (key column should not be NULL)
        $explainRow = $explain[0];
        $this->assertNotNull($explainRow->key, 'Index should be used for tenant_id queries');
        
        // Verify composite index is used for (tenant_id, created_at)
        $this->assertContains('tenant', strtolower($explainRow->key ?? ''), 
            'Composite index (tenant_id, created_at) should be used');
    }

    /**
     * Test that partial unique indexes work correctly with soft deletes
     */
    public function test_partial_unique_indexes_with_soft_deletes(): void
    {
        Auth::login($this->tenantAUser);

        // Create a project with a code
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'TEST-001',
        ]);

        // Try to create another project with the same code (should fail)
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'TEST-001',
        ]);
    }

    /**
     * Test that soft-deleted records can reuse codes
     */
    public function test_soft_deleted_records_can_reuse_codes(): void
    {
        Auth::login($this->tenantAUser);

        // Create and soft-delete a project
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'TEST-002',
        ]);
        $project1->delete();

        // Should be able to create another project with the same code
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'TEST-002',
        ]);

        $this->assertNotNull($project2);
        $this->assertEquals('TEST-002', $project2->code);
    }

    /**
     * Test that different tenants can use the same code
     */
    public function test_different_tenants_can_use_same_code(): void
    {
        // Tenant A creates a project
        Auth::login($this->tenantAUser);
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'SHARED-CODE',
        ]);

        // Tenant B should be able to create a project with the same code
        Auth::login($this->tenantBUser);
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantBId,
            'code' => 'SHARED-CODE',
        ]);

        $this->assertNotNull($projectB);
        $this->assertEquals('SHARED-CODE', $projectB->code);
        $this->assertNotEquals($projectA->id, $projectB->id);
    }

    /**
     * Test composite indexes for list queries
     */
    public function test_composite_indexes_for_list_queries(): void
    {
        Auth::login($this->tenantAUser);

        // Query that should use composite index (tenant_id, created_at)
        $explain = DB::select("EXPLAIN SELECT * FROM projects WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10", [
            $this->tenantAId
        ]);

        $this->assertNotEmpty($explain);
        $explainRow = $explain[0];
        
        // Verify index is used
        $this->assertNotNull($explainRow->key, 'Composite index should be used');
    }

    /**
     * Test that cache keys include tenant isolation
     */
    public function test_cache_keys_include_tenant_isolation(): void
    {
        Auth::login($this->tenantAUser);
        
        $cacheKey = \App\Services\CacheKeyService::key('projects', '123');
        
        // Cache key should include tenant ID
        $this->assertStringContainsString($this->tenantAId, $cacheKey);
        $this->assertStringContainsString('projects', $cacheKey);
        
        // Format should be: {env}:{tenant}:{domain}:{id}
        $parts = explode(':', $cacheKey);
        $this->assertGreaterThanOrEqual(4, count($parts), 'Cache key should have format {env}:{tenant}:{domain}:{id}');
    }
}

