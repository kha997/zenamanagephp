<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Component;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Performance Unit tests
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Test N+1 query prevention in Project relationships
     */
    public function test_project_relationships_no_n1_queries(): void
    {
        // Create test data
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'code' => 'PRJ-PERF-001',
            'status' => 'active',
            'owner_id' => $this->user->id,
        ]);

        // Create tasks for the project
        for ($i = 0; $i < 5; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $project->id,
                'name' => "Task {$i}",
                'status' => 'backlog',
                'created_by' => $this->user->id,
            ]);
        }

        // Create components for the project
        for ($i = 0; $i < 3; $i++) {
            Component::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $project->id,
                'name' => "Component {$i}",
                'status' => 'planning',
                'created_by' => $this->user->id,
            ]);
        }

        // Test eager loading prevents N+1 queries
        DB::enableQueryLog();
        
        $projects = Project::with(['tasks', 'components', 'owner'])
            ->where('tenant_id', $this->tenant->id)
            ->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have limited queries (not N+1)
        $this->assertLessThan(10, count($queries), 'Too many queries detected - possible N+1 problem');
        
        // Verify relationships are loaded
        $this->assertCount(1, $projects);
        $this->assertCount(5, $projects->first()->tasks);
        $this->assertCount(3, $projects->first()->components);
        $this->assertNotNull($projects->first()->owner);
    }

    /**
     * Test Task relationships performance
     */
    public function test_task_relationships_performance(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'code' => 'PRJ-PERF-002',
            'status' => 'active',
            'owner_id' => $this->user->id,
        ]);

        // Create multiple tasks
        for ($i = 0; $i < 10; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $project->id,
                'name' => "Task {$i}",
                'status' => 'backlog',
                'created_by' => $this->user->id,
                'assignee_id' => $this->user->id,
            ]);
        }

        DB::enableQueryLog();
        
        $tasks = Task::with(['project:id,name,code', 'assignee:id,name,email', 'creator:id,name,email'])
            ->where('tenant_id', $this->tenant->id)
            ->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have limited queries
        $this->assertLessThan(5, count($queries), 'Too many queries for task relationships');
        
        // Verify relationships are loaded
        $this->assertCount(10, $tasks);
        $this->assertNotNull($tasks->first()->project);
        $this->assertNotNull($tasks->first()->assignee);
        $this->assertNotNull($tasks->first()->creator);
    }

    /**
     * Test tenant isolation performance
     */
    public function test_tenant_isolation_performance(): void
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        // Create projects for both tenants
        for ($i = 0; $i < 5; $i++) {
            Project::create([
                'tenant_id' => $this->tenant->id,
                'name' => "Project {$i}",
                'code' => "PRJ-{$i}",
                'status' => 'active',
                'owner_id' => $this->user->id,
            ]);
        }

        for ($i = 0; $i < 5; $i++) {
            Project::create([
                'tenant_id' => $otherTenant->id,
                'name' => "Other Project {$i}",
                'code' => "OTHER-{$i}",
                'status' => 'active',
                'owner_id' => $otherUser->id,
            ]);
        }

        DB::enableQueryLog();
        
        // Query should only return projects for current tenant
        $projects = Project::where('tenant_id', $this->tenant->id)->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have only 1 query
        $this->assertCount(1, $queries);
        
        // Should only return projects for current tenant
        $this->assertCount(5, $projects);
        foreach ($projects as $project) {
            $this->assertEquals($this->tenant->id, $project->tenant_id);
        }
    }

    /**
     * Test pagination performance
     */
    public function test_pagination_performance(): void
    {
        // Create many projects
        for ($i = 0; $i < 50; $i++) {
            Project::create([
                'tenant_id' => $this->tenant->id,
                'name' => "Project {$i}",
                'code' => "PRJ-{$i}",
                'status' => 'active',
                'owner_id' => $this->user->id,
            ]);
        }

        DB::enableQueryLog();
        
        // Use limit instead of paginate for testing
        $projects = Project::where('tenant_id', $this->tenant->id)
            ->limit(10)
            ->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have limited queries for pagination
        $this->assertLessThan(5, count($queries), 'Too many queries for pagination');
        
        // Verify limit works
        $this->assertCount(10, $projects);
        
        // Verify total count
        $totalCount = Project::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(50, $totalCount);
    }

    /**
     * Test search performance
     */
    public function test_search_performance(): void
    {
        // Create projects with different names
        $projectNames = [
            'Construction Project Alpha',
            'Development Project Beta',
            'Design Project Gamma',
            'Planning Project Delta',
            'Testing Project Epsilon'
        ];

        foreach ($projectNames as $name) {
            Project::create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'code' => 'PRJ-' . substr($name, 0, 3),
                'status' => 'active',
                'owner_id' => $this->user->id,
            ]);
        }

        DB::enableQueryLog();
        
        $projects = Project::where('tenant_id', $this->tenant->id)
            ->where('name', 'like', '%Project%')
            ->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have only 1 query
        $this->assertCount(1, $queries);
        
        // Should return all projects with 'Project' in name
        $this->assertCount(5, $projects);
    }
}
