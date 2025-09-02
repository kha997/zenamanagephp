<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\InteractionLogs\Models\InteractionLog;
use Src\CoreProject\Models\Task;
use App\Models\User;
use App\Models\Tenant;
use Src\RBAC\Services\AuthService; // Sửa từ Src\Auth\Services\AuthService
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Test hiệu suất với tập dữ liệu lớn
 * 
 * Kiểm tra khả năng xử lý của hệ thống với:
 * - Large datasets (1000+ records)
 * - Memory usage optimization
 * - Database query performance
 * - Cache effectiveness
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected string $authToken;
    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant và user cho test
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // Sử dụng AuthService để tạo token
        $this->authService = app(AuthService::class);
        $loginResult = $this->authService->login([
            'email' => $this->user->email,
            'password' => 'password' // Default password từ UserFactory
        ]);
        
        $this->authToken = $loginResult['token'];
        
        // Set authorization header cho các request
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authToken,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Test hiệu suất với dataset lớn - 1000 projects
     */
    public function test_large_projects_dataset_performance(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Tạo 1000 projects
        $projects = Project::factory(1000)->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $creationTime = microtime(true) - $startTime;
        
        // Test API performance với pagination
        $response = $this->getJson('/api/v1/projects?per_page=50');
        
        $apiTime = microtime(true) - $startTime - $creationTime;
        $memoryUsed = memory_get_usage() - $startMemory;
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'data',
                        'current_page',
                        'per_page',
                        'total'
                    ]
                ]);
        
        // Performance assertions
        $this->assertLessThan(30, $creationTime, 'Tạo 1000 projects không được vượt quá 30 giây');
        $this->assertLessThan(2, $apiTime, 'API response time không được vượt quá 2 giây');
        $this->assertLessThan(256 * 1024 * 1024, $memoryUsed, 'Memory usage không được vượt quá 256MB');
        
        echo "\n=== LARGE PROJECTS PERFORMANCE ===\n";
        echo "Creation time: {$creationTime}s\n";
        echo "API response time: {$apiTime}s\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
    }

    /**
     * Test hiệu suất với 5000 interaction logs
     */
    public function test_large_interaction_logs_performance(): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Tạo project trước
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // Tạo 5000 interaction logs
        $logs = InteractionLog::factory(5000)->create([
            'project_id' => $project->id,
            'created_by' => $this->user->id
        ]);
        
        $creationTime = microtime(true) - $startTime;
        
        // Test API performance
        $response = $this->getJson("/api/v1/projects/{$project->id}/interaction-logs?per_page=100");
        
        $apiTime = microtime(true) - $startTime - $creationTime;
        $memoryUsed = memory_get_usage() - $startMemory;
        
        $response->assertStatus(200);
        
        // Performance assertions
        $this->assertLessThan(60, $creationTime, 'Tạo 5000 logs không được vượt quá 60 giây');
        $this->assertLessThan(3, $apiTime, 'API response time không được vượt quá 3 giây');
        
        echo "\n=== LARGE INTERACTION LOGS PERFORMANCE ===\n";
        echo "Creation time: {$creationTime}s\n";
        echo "API response time: {$apiTime}s\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
    }

    /**
     * Test memory usage với large dataset
     */
    public function test_memory_usage_with_large_dataset(): void
    {
        $initialMemory = memory_get_usage();
        
        // Tạo dataset lớn
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $components = Component::factory(500)->create(['project_id' => $project->id]);
        $tasks = Task::factory(1000)->create(['project_id' => $project->id]);
        
        $afterCreationMemory = memory_get_usage();
        
        // Test query với eager loading
        $startQueryTime = microtime(true);
        $projectWithRelations = Project::with(['components', 'tasks'])
            ->find($project->id);
        $queryTime = microtime(true) - $startQueryTime;
        
        $afterQueryMemory = memory_get_usage();
        
        // Memory assertions
        $creationMemoryUsage = $afterCreationMemory - $initialMemory;
        $queryMemoryUsage = $afterQueryMemory - $afterCreationMemory;
        
        $this->assertLessThan(512 * 1024 * 1024, $creationMemoryUsage, 'Memory cho tạo data không được vượt quá 512MB');
        $this->assertLessThan(128 * 1024 * 1024, $queryMemoryUsage, 'Memory cho query không được vượt quá 128MB');
        $this->assertLessThan(1, $queryTime, 'Query time với eager loading không được vượt quá 1 giây');
        
        echo "\n=== MEMORY USAGE TEST ===\n";
        echo "Creation memory: " . round($creationMemoryUsage / 1024 / 1024, 2) . "MB\n";
        echo "Query memory: " . round($queryMemoryUsage / 1024 / 1024, 2) . "MB\n";
        echo "Query time: {$queryTime}s\n";
    }

    /**
     * Test database query optimization
     */
    public function test_database_query_optimization(): void
    {
        // Tạo test data
        $projects = Project::factory(100)->create(['tenant_id' => $this->tenant->id]);
        
        foreach ($projects as $project) {
            Component::factory(5)->create(['project_id' => $project->id]);
            Task::factory(10)->create(['project_id' => $project->id]);
        }
        
        // Test N+1 query problem
        DB::enableQueryLog();
        
        $startTime = microtime(true);
        
        // Query với eager loading (optimized)
        $optimizedProjects = Project::with(['components', 'tasks'])
            ->where('tenant_id', $this->tenant->id)
            ->limit(50)
            ->get();
        
        $optimizedTime = microtime(true) - $startTime;
        $optimizedQueries = count(DB::getQueryLog());
        
        DB::flushQueryLog();
        
        $startTime = microtime(true);
        
        // Query không có eager loading (non-optimized)
        $nonOptimizedProjects = Project::where('tenant_id', $this->tenant->id)
            ->limit(50)
            ->get();
        
        // Access relations để trigger lazy loading
        foreach ($nonOptimizedProjects as $project) {
            $project->components->count();
            $project->tasks->count();
        }
        
        $nonOptimizedTime = microtime(true) - $startTime;
        $nonOptimizedQueries = count(DB::getQueryLog());
        
        // Assertions
        $this->assertLessThan($nonOptimizedQueries, $optimizedQueries, 'Eager loading phải giảm số lượng queries');
        $this->assertLessThan($nonOptimizedTime, $optimizedTime, 'Eager loading phải nhanh hơn lazy loading');
        
        echo "\n=== DATABASE QUERY OPTIMIZATION ===\n";
        echo "Optimized queries: {$optimizedQueries}, time: {$optimizedTime}s\n";
        echo "Non-optimized queries: {$nonOptimizedQueries}, time: {$nonOptimizedTime}s\n";
        echo "Query reduction: " . round((1 - $optimizedQueries / $nonOptimizedQueries) * 100, 2) . "%\n";
        echo "Time improvement: " . round((1 - $optimizedTime / $nonOptimizedTime) * 100, 2) . "%\n";
    }

    /**
     * Test cache performance
     */
    public function test_cache_performance(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        Component::factory(50)->create(['project_id' => $project->id]);
        
        $cacheKey = "project_stats_{$project->id}";
        
        // Test without cache
        $startTime = microtime(true);
        $stats = [
            'total_components' => $project->components()->count(),
            'completed_components' => $project->components()->where('progress_percent', 100)->count(),
            'total_cost' => $project->components()->sum('actual_cost'),
        ];
        $noCacheTime = microtime(true) - $startTime;
        
        // Cache the result
        Cache::put($cacheKey, $stats, 3600);
        
        // Test with cache
        $startTime = microtime(true);
        $cachedStats = Cache::get($cacheKey);
        $cacheTime = microtime(true) - $startTime;
        
        // Assertions
        $this->assertEquals($stats, $cachedStats);
        $this->assertLessThan($noCacheTime, $cacheTime, 'Cache phải nhanh hơn query trực tiếp');
        $this->assertLessThan(0.01, $cacheTime, 'Cache access phải dưới 10ms');
        
        $speedImprovement = round(($noCacheTime / $cacheTime), 2);
        
        echo "\n=== CACHE PERFORMANCE ===\n";
        echo "No cache time: {$noCacheTime}s\n";
        echo "Cache time: {$cacheTime}s\n";
        echo "Speed improvement: {$speedImprovement}x faster\n";
        
        // Cleanup
        Cache::forget($cacheKey);
    }
}

/*
 * Ghi chú về factory namespaces:
 * - Cần thay đổi từ: Database\Factories\Src\InteractionLogs\Models\InteractionLogFactory
 * - Thành: Database\Factories\InteractionLogFactory
 * - Tương tự cho các factory khác
 */