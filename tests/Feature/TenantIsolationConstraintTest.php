<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;

/**
 * Tenant Isolation Constraint Test
 * 
 * Tests that database constraints and policies enforce tenant isolation.
 * Verifies that tenant A cannot access tenant B's data.
 */
class TenantIsolationConstraintTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setDomainSeed(12345); // Fixed seed for reproducibility
    }

    /**
     * Test that tenant_id NOT NULL constraint prevents creating records without tenant
     */
    public function test_cannot_create_project_without_tenant_id(): void
    {
        // This test verifies the NOT NULL constraint
        // In practice, the BelongsToTenant trait should auto-set tenant_id
        // But the constraint ensures data integrity at DB level
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        // Try to create project without tenant_id (should fail with NOT NULL constraint)
        Project::withoutGlobalScopes()->create([
            'code' => 'TEST001',
            'name' => 'Test Project',
            'tenant_id' => null, // This should fail
        ]);
    }

    /**
     * Test that composite unique (tenant_id, code) prevents duplicate codes per tenant
     */
    public function test_cannot_create_duplicate_project_code_per_tenant(): void
    {
        $tenant1 = Tenant::factory()->create(['id' => 'tenant1']);
        $tenant2 = Tenant::factory()->create(['id' => 'tenant2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $this->actingAs($user1);
        
        // Create first project with code 'PROJ001'
        Project::create([
            'code' => 'PROJ001',
            'name' => 'Project 1',
            'tenant_id' => $tenant1->id,
        ]);
        
        // Try to create another project with same code in same tenant (should fail)
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Project::create([
            'code' => 'PROJ001', // Same code
            'name' => 'Project 2',
            'tenant_id' => $tenant1->id, // Same tenant
        ]);
    }

    /**
     * Test that same code can exist in different tenants
     */
    public function test_can_create_same_code_in_different_tenants(): void
    {
        $tenant1 = Tenant::factory()->create(['id' => 'tenant1']);
        $tenant2 = Tenant::factory()->create(['id' => 'tenant2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $this->actingAs($user1);
        
        // Create project in tenant1
        $project1 = Project::create([
            'code' => 'PROJ001',
            'name' => 'Project 1',
            'tenant_id' => $tenant1->id,
        ]);
        
        $this->actingAs($user2);
        
        // Create project with same code in tenant2 (should succeed)
        $project2 = Project::create([
            'code' => 'PROJ001', // Same code
            'name' => 'Project 2',
            'tenant_id' => $tenant2->id, // Different tenant
        ]);
        
        $this->assertNotEquals($project1->id, $project2->id);
        $this->assertEquals('PROJ001', $project1->code);
        $this->assertEquals('PROJ001', $project2->code);
    }

    /**
     * Test that Policy enforces tenant isolation for viewing
     */
    public function test_policy_prevents_viewing_other_tenant_project(): void
    {
        $tenant1 = Tenant::factory()->create(['id' => 'tenant1']);
        $tenant2 = Tenant::factory()->create(['id' => 'tenant2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $this->actingAs($user1);
        
        // Create project in tenant1
        $project = Project::create([
            'code' => 'PROJ001',
            'name' => 'Project 1',
            'tenant_id' => $tenant1->id,
        ]);
        
        // User1 can view their tenant's project
        $this->assertTrue($user1->can('view', $project));
        
        // User2 cannot view tenant1's project
        $this->actingAs($user2);
        $this->assertFalse($user2->can('view', $project));
    }

    /**
     * Test that Policy enforces tenant isolation for updating
     */
    public function test_policy_prevents_updating_other_tenant_project(): void
    {
        $tenant1 = Tenant::factory()->create(['id' => 'tenant1']);
        $tenant2 = Tenant::factory()->create(['id' => 'tenant2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $this->actingAs($user1);
        
        $project = Project::create([
            'code' => 'PROJ001',
            'name' => 'Project 1',
            'tenant_id' => $tenant1->id,
        ]);
        
        // User1 can update their tenant's project
        $this->assertTrue($user1->can('update', $project));
        
        // User2 cannot update tenant1's project
        $this->actingAs($user2);
        $this->assertFalse($user2->can('update', $project));
    }

    /**
     * Test that Global Scope filters by tenant_id automatically
     */
    public function test_global_scope_filters_by_tenant(): void
    {
        $tenant1 = Tenant::factory()->create(['id' => 'tenant1']);
        $tenant2 = Tenant::factory()->create(['id' => 'tenant2']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        // Create projects in both tenants
        Project::withoutGlobalScopes()->create([
            'code' => 'PROJ001',
            'name' => 'Project 1',
            'tenant_id' => $tenant1->id,
        ]);
        
        Project::withoutGlobalScopes()->create([
            'code' => 'PROJ002',
            'name' => 'Project 2',
            'tenant_id' => $tenant2->id,
        ]);
        
        // User1 should only see tenant1's projects
        $this->actingAs($user1);
        $projects = Project::all();
        $this->assertCount(1, $projects);
        $this->assertEquals($tenant1->id, $projects->first()->tenant_id);
        
        // User2 should only see tenant2's projects
        $this->actingAs($user2);
        $projects = Project::all();
        $this->assertCount(1, $projects);
        $this->assertEquals($tenant2->id, $projects->first()->tenant_id);
    }

    /**
     * Test that soft-deleted records don't violate unique constraints
     */
    public function test_soft_deleted_records_allow_recreating_same_code(): void
    {
        $tenant = Tenant::factory()->create(['id' => 'tenant1']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->actingAs($user);
        
        // Create project
        $project = Project::create([
            'code' => 'PROJ001',
            'name' => 'Project 1',
            'tenant_id' => $tenant->id,
        ]);
        
        // Soft delete it
        $project->delete();
        
        // Should be able to create another project with same code
        // (MySQL allows this because deleted_at is NULL for new record)
        $project2 = Project::create([
            'code' => 'PROJ001',
            'name' => 'Project 2',
            'tenant_id' => $tenant->id,
        ]);
        
        $this->assertNotEquals($project->id, $project2->id);
        $this->assertTrue($project->trashed());
        $this->assertFalse($project2->trashed());
    }

    /**
     * Test composite indexes improve query performance
     */
    public function test_composite_indexes_exist(): void
    {
        $connection = \Illuminate\Support\Facades\Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        // Check that composite index exists
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SELECT index_name 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = 'projects' 
             AND index_name LIKE '%tenant%'",
            [$databaseName]
        );
        
        $this->assertNotEmpty($indexes, 'Composite tenant indexes should exist');
    }
}

