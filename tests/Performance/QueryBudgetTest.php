<?php declare(strict_types=1);

namespace Tests\Performance;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Query Budget Tests
 * 
 * Ensures API endpoints don't exceed query budget (N+1 detection).
 * Tracks number of queries per endpoint to catch performance regressions.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $tenant = \App\Models\Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($this->user);
    }

    /**
     * Test projects list endpoint query budget
     */
    public function test_projects_list_query_budget(): void
    {
        // Create test data
        Project::factory()->count(20)->create(['tenant_id' => $this->user->tenant_id]);
        
        DB::enableQueryLog();
        
        $response = $this->getJson('/api/v1/app/projects?per_page=15');
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Budget: max 5 queries for projects list (1 main query + 4 for relationships/counts)
        $maxQueries = 5;
        
        $this->assertLessThanOrEqual(
            $maxQueries,
            $queryCount,
            "Projects list exceeded query budget: {$queryCount} queries (max: {$maxQueries})"
        );
        
        $response->assertStatus(200);
    }

    /**
     * Test tasks list endpoint query budget
     */
    public function test_tasks_list_query_budget(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->user->tenant_id]);
        Task::factory()->count(20)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $project->id,
        ]);
        
        DB::enableQueryLog();
        
        $response = $this->getJson('/api/v1/app/tasks?per_page=15');
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Budget: max 5 queries for tasks list
        $maxQueries = 5;
        
        $this->assertLessThanOrEqual(
            $maxQueries,
            $queryCount,
            "Tasks list exceeded query budget: {$queryCount} queries (max: {$maxQueries})"
        );
        
        $response->assertStatus(200);
    }

    /**
     * Test cursor pagination query budget
     */
    public function test_cursor_pagination_query_budget(): void
    {
        Project::factory()->count(30)->create(['tenant_id' => $this->user->tenant_id]);
        
        DB::enableQueryLog();
        
        $response = $this->getJson('/api/v1/app/projects?cursor=&limit=15');
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Cursor pagination should be even more efficient (no count query)
        $maxQueries = 3;
        
        $this->assertLessThanOrEqual(
            $maxQueries,
            $queryCount,
            "Cursor pagination exceeded query budget: {$queryCount} queries (max: {$maxQueries})"
        );
        
        $response->assertStatus(200);
        $this->assertArrayHasKey('pagination', $response->json('data'));
    }

    /**
     * Test project detail endpoint query budget
     */
    public function test_project_detail_query_budget(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->user->tenant_id]);
        Task::factory()->count(10)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $project->id,
        ]);
        
        DB::enableQueryLog();
        
        $response = $this->getJson("/api/v1/app/projects/{$project->id}");
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Budget: max 10 queries for project detail (with relationships)
        $maxQueries = 10;
        
        $this->assertLessThanOrEqual(
            $maxQueries,
            $queryCount,
            "Project detail exceeded query budget: {$queryCount} queries (max: {$maxQueries})"
        );
        
        $response->assertStatus(200);
    }

    /**
     * Test N+1 query detection
     */
    public function test_n_plus_one_detection(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->user->tenant_id]);
        Task::factory()->count(20)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $project->id,
        ]);
        
        DB::enableQueryLog();
        
        // Access tasks with relationships (potential N+1)
        $tasks = Task::where('project_id', $project->id)->get();
        foreach ($tasks as $task) {
            // Accessing relationship without eager loading
            $task->project->name; // This would cause N+1
        }
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // With N+1, we'd have 1 + 20 = 21 queries
        // This test should fail if N+1 exists
        $this->assertLessThan(
            21,
            $queryCount,
            "N+1 query detected: {$queryCount} queries (expected < 21)"
        );
    }
}

