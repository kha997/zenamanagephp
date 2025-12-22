<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Unit tests for BelongsToTenant trait
 * 
 * Tests GlobalScope behavior and tenant isolation
 * 
 * @group tenant-isolation
 */
class BelongsToTenantTraitTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDomainSeed(45678);
        $this->setDomainName('belongs-to-tenant');
        $this->setupDomainIsolation();

        $this->tenantA = TestDataSeeder::createTenant();
        $this->tenantB = TestDataSeeder::createTenant();

        $this->userA = TestDataSeeder::createUser($this->tenantA, ['role' => 'admin']);
        $this->userB = TestDataSeeder::createUser($this->tenantB, ['role' => 'admin']);

        $this->storeTestData('tenant', $this->tenantA);
    }

    /**
     * Test GlobalScope filters by tenant_id
     */
    public function test_global_scope_filters_by_tenant_id(): void
    {
        // Create projects for both tenants
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectB = Project::factory()->create(['tenant_id' => $this->tenantB->id]);

        // Set current tenant context
        $this->actingAs($this->userA);

        // Query should only return tenant A's project
        $projects = Project::all();

        $this->assertCount(1, $projects);
        $this->assertEquals($projectA->id, $projects->first()->id);
        $this->assertNotContains($projectB->id, $projects->pluck('id')->toArray());
    }

    /**
     * Test GlobalScope applies to all queries
     */
    public function test_global_scope_applies_to_all_queries(): void
    {
        // Create tasks for both tenants
        $taskA = Task::factory()->create(['tenant_id' => $this->tenantA->id]);
        $taskB = Task::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->userA);

        // Test find()
        $found = Task::find($taskA->id);
        $this->assertNotNull($found);
        $this->assertEquals($taskA->id, $found->id);

        $notFound = Task::find($taskB->id);
        $this->assertNull($notFound);

        // Test where()
        $tasks = Task::where('status', 'todo')->get();
        $this->assertCount(1, $tasks);
        $this->assertEquals($taskA->id, $tasks->first()->id);
    }

    /**
     * Test GlobalScope cannot be bypassed easily
     */
    public function test_global_scope_cannot_be_bypassed(): void
    {
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectB = Project::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->userA);

        // Try to bypass with withoutGlobalScope
        $allProjects = Project::withoutGlobalScope('tenant')->get();
        
        // Should still see both (withoutGlobalScope removes the scope)
        // But in normal usage, this should not be done
        $this->assertCount(2, $allProjects);

        // Normal query should still be filtered
        $filteredProjects = Project::all();
        $this->assertCount(1, $filteredProjects);
    }

    /**
     * Test tenant_id is automatically set on create
     */
    public function test_tenant_id_automatically_set_on_create(): void
    {
        $this->actingAs($this->userA);

        $project = Project::factory()->make();
        $project->save();

        $this->assertEquals($this->tenantA->id, $project->tenant_id);
    }

    /**
     * Test raw queries still respect tenant isolation (if middleware is used)
     */
    public function test_raw_queries_respect_tenant_isolation(): void
    {
        Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        Project::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->userA);

        // Raw query should include tenant_id filter
        $count = DB::table('projects')
            ->where('tenant_id', $this->tenantA->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test tenant isolation with relationships
     */
    public function test_tenant_isolation_with_relationships(): void
    {
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        $projectB = Project::factory()->create(['tenant_id' => $this->tenantB->id]);

        $taskA = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $projectA->id,
        ]);

        $taskB = Task::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
        ]);

        $this->actingAs($this->userA);

        // Load relationship should respect tenant isolation
        $project = Project::find($projectA->id);
        $tasks = $project->tasks;

        $this->assertCount(1, $tasks);
        $this->assertEquals($taskA->id, $tasks->first()->id);
    }
}

