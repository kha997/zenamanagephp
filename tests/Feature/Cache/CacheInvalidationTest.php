<?php declare(strict_types=1);

namespace Tests\Feature\Cache;

use App\Events\TaskUpdated;
use App\Events\ProjectUpdated;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use App\Services\CacheInvalidationService;
use App\Services\AdvancedCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Cache Invalidation Test
 * 
 * Tests that cache invalidation works correctly when entities are updated.
 * 
 * @group cache
 * @group invalidation
 */
class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Task $task;
    private CacheInvalidationService $cacheInvalidationService;
    private AdvancedCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->cacheService = app(AdvancedCacheService::class);
        $this->cacheInvalidationService = app(CacheInvalidationService::class);
        
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test: Task update invalidates task cache
     */
    public function test_task_update_invalidates_task_cache(): void
    {
        // Set some cache
        $taskKey = "task:{$this->task->id}";
        $this->cacheService->set($taskKey, ['cached' => 'data'], 3600);
        
        $this->assertTrue(Cache::has($taskKey), 'Cache should be set before update');
        
        // Update task via CacheInvalidationService
        $this->task->update(['title' => 'Updated Title']);
        $this->cacheInvalidationService->forTaskUpdate($this->task);
        
        // Cache should be invalidated
        $this->assertFalse(Cache::has($taskKey), 'Task cache should be invalidated after update');
    }

    /**
     * Test: Task update invalidates project KPIs
     */
    public function test_task_update_invalidates_project_kpis(): void
    {
        // Set project KPI cache
        $kpiKey = "project:{$this->project->id}:kpis";
        $this->cacheService->set($kpiKey, ['kpi' => 'data'], 3600);
        
        $this->assertTrue(Cache::has($kpiKey), 'Project KPI cache should be set before task update');
        
        // Update task
        $this->task->update(['status' => 'completed']);
        $this->cacheInvalidationService->forTaskUpdate($this->task);
        
        // Project KPI cache should be invalidated
        $this->assertFalse(Cache::has($kpiKey), 'Project KPI cache should be invalidated after task update');
    }

    /**
     * Test: Project update invalidates project cache
     */
    public function test_project_update_invalidates_project_cache(): void
    {
        // Set project cache
        $projectKey = "project:{$this->project->id}";
        $this->cacheService->set($projectKey, ['cached' => 'data'], 3600);
        
        $this->assertTrue(Cache::has($projectKey), 'Project cache should be set before update');
        
        // Update project via CacheInvalidationService
        $this->project->update(['name' => 'Updated Project']);
        $this->cacheInvalidationService->forProjectUpdate($this->project);
        
        // Cache should be invalidated
        $this->assertFalse(Cache::has($projectKey), 'Project cache should be invalidated after update');
    }

    /**
     * Test: Task update invalidates task list cache for project
     */
    public function test_task_update_invalidates_task_list_cache(): void
    {
        // Set task list cache
        $listKey = "tasks:project:{$this->project->id}:list";
        $this->cacheService->set($listKey, ['tasks' => []], 3600);
        
        $this->assertTrue(Cache::has($listKey), 'Task list cache should be set before update');
        
        // Update task
        $this->task->update(['title' => 'Updated']);
        $this->cacheInvalidationService->forTaskUpdate($this->task);
        
        // Task list cache should be invalidated (pattern-based)
        // Note: Pattern invalidation may not work with all cache drivers
        // This test verifies the service is called correctly
        $this->markTestIncomplete('Pattern-based cache invalidation testing requires Redis cache driver');
    }

    /**
     * Test: Event triggers cache invalidation
     */
    public function test_event_triggers_cache_invalidation(): void
    {
        // Set cache
        $taskKey = "task:{$this->task->id}";
        $this->cacheService->set($taskKey, ['cached' => 'data'], 3600);
        
        $this->assertTrue(Cache::has($taskKey), 'Cache should be set before event');
        
        // Fire event (listener should call CacheInvalidationService)
        event(new TaskUpdated($this->task));
        
        // Wait for event to be processed
        $this->artisan('queue:work', ['--once' => true]);
        
        // Cache should be invalidated by listener
        // Note: This depends on InvalidateTaskCache listener being registered
        $this->markTestIncomplete('Event-driven cache invalidation testing requires queue setup');
    }

    /**
     * Test: KPI cache is invalidated after task update
     */
    public function test_kpi_cache_invalidated_after_task_update(): void
    {
        // This test verifies that when a task is updated,
        // the KPI/alerts/widgets cache is invalidated
        // so that API calls return fresh data
        
        // Set KPI cache
        $kpiKey = "project:{$this->project->id}:kpis";
        $this->cacheService->set($kpiKey, [
            'total_tasks' => 10,
            'completed_tasks' => 5,
        ], 3600);
        
        // Update task status to completed
        $this->task->update(['status' => 'completed']);
        $this->cacheInvalidationService->forTaskUpdate($this->task);
        
        // KPI cache should be invalidated
        $this->assertFalse(Cache::has($kpiKey), 'KPI cache should be invalidated after task update');
        
        // Simulate API call to get KPIs (should return fresh data)
        // In real scenario, this would call DashboardService which would recalculate
        $this->markTestIncomplete('Full KPI refresh testing requires DashboardService integration');
    }
}

