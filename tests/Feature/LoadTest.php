<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\RBAC\Services\AuthService;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * Test tải với concurrent users
 *
 * @group slow
 * @group load
 */
class LoadTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private $baseUrl;
    private $httpClient;
    private AuthService $authService;
    private int $loadTestScale = 1;
    private ?string $databaseDriver = null;
    private ?string $appUrlConnectivityError = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->shouldRunLoadTests()) {
            $this->markTestSkipped('Load tests require RUN_LOAD_TESTS=1 in your environment.');
        }

        $this->databaseDriver = $this->resolveDatabaseDriver();
        $this->loadTestScale = $this->determineLoadTestScale();

        $this->authService = app(AuthService::class);
        $this->baseUrl = config('app.url');
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'http_errors' => false
        ]);

        $this->appUrlConnectivityError = $this->probeAppUrlConnectivity();

        // Seed test data using the shared helpers
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        $tenantCount = max(3, $this->scaledCount(3));
        $baseProjects = 4;
        $baseUsers = 5;
        $baseTasks = 5;
        $projectsWithTasks = max(1, $this->scaledCount(2));

        for ($index = 0; $index < $tenantCount; $index++) {
            $tenant = Tenant::factory()->create();
            $this->createTenantUser($tenant, [
                'email' => sprintf('load+tenant-%d-%s@example.com', $index, uniqid('', true)),
            ]);

            $usersPerTenant = $this->scaledCount($baseUsers);
            $additionalUsers = max(0, $usersPerTenant - 1);
            if ($additionalUsers > 0) {
                User::factory($additionalUsers)->create([
                    'tenant_id' => $tenant->id,
                ]);
            }

            $projects = Project::factory($this->scaledCount($baseProjects))->create([
                'tenant_id' => $tenant->id,
            ]);

            foreach ($projects->take(min($projects->count(), $projectsWithTasks)) as $project) {
                Task::factory($this->scaledCount($baseTasks))->create([
                    'tenant_id' => $tenant->id,
                    'project_id' => $project->id,
                ]);
            }
        }
    }

    private function scaledCount(int $base): int
    {
        return max(1, (int) ceil($base * $this->loadTestScale));
    }

    private function determineLoadTestScale(): int
    {
        $configuredScale = max(1, (int) env('LOADTEST_SCALE', 1));

        if ($this->databaseDriver === 'sqlite') {
            return min($configuredScale, 2);
        }

        return $configuredScale;
    }

    private function resolveDatabaseDriver(): ?string
    {
        $connection = config('database.default');

        if (!$connection) {
            return null;
        }

        return config("database.connections.{$connection}.driver");
    }

    private function fetchActiveDbConnections(): ?int
    {
        if ($this->databaseDriver !== 'mysql') {
            return null;
        }

        $result = DB::select('SHOW STATUS LIKE "Threads_connected"');

        if (empty($result)) {
            return null;
        }

        return (int) ($result[0]->Value ?? 0);
    }

    private function shouldRunLoadTests(): bool
    {
        return filter_var(env('RUN_LOAD_TESTS', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function createSampleFailure(mixed $status, mixed $message): array
    {
        if ($message instanceof \Throwable) {
            $message = $message->getMessage();
        }

        return [
            'status' => $status,
            'message' => Str::limit(trim((string) $message), 400, '...'),
        ];
    }

    /**
     * Test concurrent login requests
     */
    public function test_concurrent_login_requests()
    {
        $this->skipIfAppIsUnreachable();
        $users = User::limit(20)->get();
        $concurrentRequests = $users->count();
        $successCount = 0;
        $errorCount = 0;
        $sampleFailure = null;
        $startTime = microtime(true);

        foreach ($users as $user) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 200) {
                $successCount++;
            } else {
                $errorCount++;
                $sampleFailure = $sampleFailure ?? $this->createSampleFailure(
                    $statusCode,
                    $response->getContent()
                );
            }
        }

        $totalTime = microtime(true) - $startTime;

        if ($sampleFailure !== null) {
            echo "\nSample failure -> status: {$sampleFailure['status']}, body: {$sampleFailure['message']}\n";
        }
        
        // Assert performance requirements
        $this->assertGreaterThan(15, $successCount, 'At least 75% of requests should succeed');
        $this->assertLessThan(10, $totalTime, 'Total time should be less than 10 seconds');
        
        echo "\nConcurrent Login Test:\n";
        echo "Total requests: {$concurrentRequests}\n";
        echo "Successful: {$successCount}\n";
        echo "Errors: {$errorCount}\n";
        echo "Total time: " . round($totalTime, 2) . "s\n";
        echo "Average time per request: " . round($totalTime / $concurrentRequests, 3) . "s\n";
    }

    /**
     * Test concurrent API requests
     */
    public function test_concurrent_api_requests()
    {
        $this->skipIfAppIsUnreachable();
        // Tạo authenticated users
        $users = User::limit(10)->get();
        $tokens = [];
        
        foreach ($users as $user) {
            $tokens[] = $this->authService->createTokenForUser($user);
        }
        
        $concurrentRequests = 50;
        $promises = [];
        $startTime = microtime(true);
        
        // Tạo concurrent API requests
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $token = $tokens[$i % count($tokens)];
            
            $promises[] = $this->httpClient->getAsync('/api/v1/projects', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ]
            ]);
        }
        
        // Wait for all requests
        $responses = Utils::settle($promises)->wait();
        $totalTime = microtime(true) - $startTime;
        
        $successCount = 0;
        $errorCount = 0;
        $responseTimes = [];
        
        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $statusCode = $response['value']->getStatusCode();
                if ($statusCode === 200) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } else {
                $errorCount++;
            }
        }
        
        // Performance assertions
        $this->assertGreaterThan(40, $successCount, 'At least 80% of requests should succeed');
        $this->assertLessThan(15, $totalTime, 'Total time should be less than 15 seconds');
        
        echo "\nConcurrent API Test:\n";
        echo "Total requests: {$concurrentRequests}\n";
        echo "Successful: {$successCount}\n";
        echo "Errors: {$errorCount}\n";
        echo "Total time: " . round($totalTime, 2) . "s\n";
        echo "Requests per second: " . round($concurrentRequests / $totalTime, 2) . "\n";
    }

    /**
     * Test database connection pool under load
     */
    public function test_database_connection_pool_load()
    {
        $this->skipIfAppIsUnreachable();
        $concurrentQueries = 30;
        $promises = [];
        $startTime = microtime(true);
        
        // Get token for authentication
        $user = User::first();
        $token = $this->authService->createTokenForUser($user);
        
        // Simulate concurrent database operations
        for ($i = 0; $i < $concurrentQueries; $i++) {
            $promises[] = $this->httpClient->postAsync('/api/v1/projects', [
                'json' => [
                    'name' => 'Load Test Project ' . $i,
                    'description' => 'Created during load test',
                    'start_date' => '2024-01-01',
                    'end_date' => '2024-12-31'
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
        }
        
        $responses = Utils::settle($promises)->wait();
        $totalTime = microtime(true) - $startTime;
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $statusCode = $response['value']->getStatusCode();
                if ($statusCode === 201) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } else {
                $errorCount++;
            }
        }
        
        // Check database connections
        $this->assertGreaterThan(20, $successCount, 'Most database operations should succeed');
        $activeConnections = $this->fetchActiveDbConnections();
        if ($activeConnections !== null) {
            $this->assertLessThan(100, $activeConnections, 'Should not exhaust connection pool');
        }

        echo "\nDatabase Load Test:\n";
        echo "Concurrent operations: {$concurrentQueries}\n";
        echo "Successful: {$successCount}\n";
        echo "Errors: {$errorCount}\n";
        echo "Total time: " . round($totalTime, 2) . "s\n";
        if ($activeConnections !== null) {
            echo "Active DB connections: {$activeConnections}\n";
        } else {
            echo "Active DB connections: not available for {$this->databaseDriver} driver\n";
        }
    }

    /**
     * Test memory usage under concurrent load
     */
    public function test_memory_usage_under_load()
    {
        $this->skipIfAppIsUnreachable();
        $initialMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        // Simulate high memory usage scenario
        $users = User::limit(5)->get();
        $tokens = [];
        
        foreach ($users as $user) {
            $tokens[] = $this->authService->createTokenForUser($user);
        }
        
        $promises = [];
        
        // Create requests that load large datasets
        for ($i = 0; $i < 20; $i++) {
            $token = $tokens[$i % count($tokens)];
            
            $promises[] = $this->httpClient->getAsync('/api/v1/projects?per_page=100', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ]
            ]);
        }
        
        $responses = Utils::settle($promises)->wait();
        
        $finalMemory = memory_get_usage(true);
        $finalPeakMemory = memory_get_peak_usage(true);
        
        $memoryIncrease = $finalMemory - $initialMemory;
        $peakIncrease = $finalPeakMemory - $peakMemory;
        
        // Memory should not increase dramatically
        $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 'Memory increase should be less than 100MB');
        
        echo "\nMemory Load Test:\n";
        echo "Initial memory: " . round($initialMemory / 1024 / 1024, 2) . "MB\n";
        echo "Final memory: " . round($finalMemory / 1024 / 1024, 2) . "MB\n";
        echo "Memory increase: " . round($memoryIncrease / 1024 / 1024, 2) . "MB\n";
        echo "Peak memory increase: " . round($peakIncrease / 1024 / 1024, 2) . "MB\n";
    }

    private function skipIfAppIsUnreachable(): void
    {
        if ($this->appUrlConnectivityError !== null) {
            $this->markTestSkipped('dependency: LoadTest requires a running application: ' . $this->appUrlConnectivityError);
        }
    }

    private function probeAppUrlConnectivity(): ?string
    {
        $url = $this->baseUrl ?? config('app.url');

        if (!$url) {
            return 'APP_URL is not configured';
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '127.0.0.1';
        $isHttps = (($parsed['scheme'] ?? 'http') === 'https');
        $port = $parsed['port'] ?? ($isHttps ? 443 : 80);
        $timeout = 1;
        $errno = 0;
        $errstr = '';

        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($connection === false) {
            $message = $errstr ?: 'connection refused';
            return sprintf('%s (%s:%d) is unreachable: %s', $url, $host, $port, $message);
        }

        fclose($connection);

        return null;
    }
}
