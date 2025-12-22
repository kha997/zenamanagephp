<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pagination Query Budget Test
 * 
 * Verifies that list endpoints use cursor-based pagination
 * and respect query budget limits.
 */
class PaginationQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'tenant_id' => 'test_tenant_' . uniqid(),
        ]);

        // Create test data
        Project::factory()->count(50)->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        Task::factory()->count(100)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => Project::where('tenant_id', $this->user->tenant_id)->first()->id,
        ]);
    }

    /**
     * Test that projects list uses cursor pagination
     */
    public function test_projects_list_uses_cursor_pagination(): void
    {
        DB::enableQueryLog();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/app/projects?limit=20');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Should have cursor pagination fields
        $this->assertArrayHasKey('next_cursor', $data);
        $this->assertArrayHasKey('has_more', $data);
        
        // Check query count
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Should use ≤ 12 queries (budget limit)
        $this->assertLessThanOrEqual(12, $queryCount, 'Query count should be within budget');
        
        // Check response header
        $this->assertNotNull($response->headers->get('X-Query-Count'));
        $this->assertNotNull($response->headers->get('X-Query-Budget'));
        
        DB::disableQueryLog();
    }

    /**
     * Test that tasks list uses cursor pagination
     */
    public function test_tasks_list_uses_cursor_pagination(): void
    {
        DB::enableQueryLog();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/app/tasks?limit=20');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Should have cursor pagination fields
        $this->assertArrayHasKey('next_cursor', $data);
        $this->assertArrayHasKey('has_more', $data);
        
        // Check query count
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Should use ≤ 12 queries (budget limit)
        $this->assertLessThanOrEqual(12, $queryCount, 'Query count should be within budget');
        
        DB::disableQueryLog();
    }

    /**
     * Test cursor pagination with next_cursor
     */
    public function test_cursor_pagination_with_next_cursor(): void
    {
        // First request
        $response1 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/app/projects?limit=10');

        $response1->assertStatus(200);
        $data1 = $response1->json();
        
        $this->assertArrayHasKey('next_cursor', $data1);
        $this->assertArrayHasKey('has_more', $data1);
        
        if ($data1['has_more'] && $data1['next_cursor']) {
            // Second request with cursor
            $response2 = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/app/projects?limit=10&cursor=' . urlencode($data1['next_cursor']));

            $response2->assertStatus(200);
            $data2 = $response2->json();
            
            // Should return different data
            $this->assertNotEquals($data1['data'][0]['id'] ?? null, $data2['data'][0]['id'] ?? null);
        }
    }

    /**
     * Test that query budget is enforced
     */
    public function test_query_budget_is_enforced(): void
    {
        DB::enableQueryLog();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/app/projects');

        $response->assertStatus(200);
        
        // Check response headers
        $queryCount = (int) $response->headers->get('X-Query-Count');
        $queryBudget = (int) $response->headers->get('X-Query-Budget');
        
        $this->assertLessThanOrEqual($queryBudget, $queryCount, 'Query count should not exceed budget');
        
        DB::disableQueryLog();
    }
}
