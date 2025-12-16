<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TestQueryPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:query-performance {--tenant-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test query performance with indexes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant-id');
        
        if (!$tenantId) {
            // Get first tenant for testing
            $tenant = DB::table('tenants')->first();
            if (!$tenant) {
                $this->error('No tenants found. Please create a tenant first.');
                return 1;
            }
            $tenantId = $tenant->id;
            $this->info("Using tenant: {$tenantId}");
        }

        $this->info('Testing Query Performance with Indexes');
        $this->info('==========================================');
        $this->newLine();

        // Enable query logging
        DB::enableQueryLog();

        $results = [];

        // Test 1: Projects filtered by priority
        $this->info('Test 1: Projects filtered by priority...');
        $start = microtime(true);
        $projects = Project::where('tenant_id', $tenantId)
            ->where('priority', 'high')
            ->get();
        $time = (microtime(true) - $start) * 1000;
        $queries = count(DB::getQueryLog());
        $results[] = [
            'test' => 'Projects by priority',
            'time_ms' => round($time, 2),
            'queries' => $queries,
            'count' => $projects->count()
        ];
        DB::flushQueryLog();
        $this->line("  ✓ Time: {$time}ms, Queries: {$queries}, Results: {$projects->count()}");

        // Test 2: Projects filtered by client
        $this->info('Test 2: Projects filtered by client...');
        $start = microtime(true);
        $clientId = DB::table('clients')->where('tenant_id', $tenantId)->value('id');
        if ($clientId) {
            $projects = Project::where('tenant_id', $tenantId)
                ->where('client_id', $clientId)
                ->get();
            $time = (microtime(true) - $start) * 1000;
            $queries = count(DB::getQueryLog());
            $results[] = [
                'test' => 'Projects by client',
                'time_ms' => round($time, 2),
                'queries' => $queries,
                'count' => $projects->count()
            ];
            DB::flushQueryLog();
            $this->line("  ✓ Time: {$time}ms, Queries: {$queries}, Results: {$projects->count()}");
        } else {
            $this->warn('  ⚠ No clients found, skipping test');
        }

        // Test 3: Tasks filtered by assignee
        $this->info('Test 3: Tasks filtered by assignee...');
        $start = microtime(true);
        $userId = DB::table('users')->where('tenant_id', $tenantId)->value('id');
        if ($userId) {
            $tasks = Task::where('tenant_id', $tenantId)
                ->where('assignee_id', $userId)
                ->get();
            $time = (microtime(true) - $start) * 1000;
            $queries = count(DB::getQueryLog());
            $results[] = [
                'test' => 'Tasks by assignee',
                'time_ms' => round($time, 2),
                'queries' => $queries,
                'count' => $tasks->count()
            ];
            DB::flushQueryLog();
            $this->line("  ✓ Time: {$time}ms, Queries: {$queries}, Results: {$tasks->count()}");
        } else {
            $this->warn('  ⚠ No users found, skipping test');
        }

        // Test 4: Tasks filtered by priority
        $this->info('Test 4: Tasks filtered by priority...');
        $start = microtime(true);
        $tasks = Task::where('tenant_id', $tenantId)
            ->where('priority', 'high')
            ->get();
        $time = (microtime(true) - $start) * 1000;
        $queries = count(DB::getQueryLog());
        $results[] = [
            'test' => 'Tasks by priority',
            'time_ms' => round($time, 2),
            'queries' => $queries,
            'count' => $tasks->count()
        ];
        DB::flushQueryLog();
        $this->line("  ✓ Time: {$time}ms, Queries: {$queries}, Results: {$tasks->count()}");

        // Test 5: Overdue projects
        $this->info('Test 5: Overdue projects...');
        $start = microtime(true);
        $projects = Project::where('tenant_id', $tenantId)
            ->where('end_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get();
        $time = (microtime(true) - $start) * 1000;
        $queries = count(DB::getQueryLog());
        $results[] = [
            'test' => 'Overdue projects',
            'time_ms' => round($time, 2),
            'queries' => $queries,
            'count' => $projects->count()
        ];
        DB::flushQueryLog();
        $this->line("  ✓ Time: {$time}ms, Queries: {$queries}, Results: {$projects->count()}");

        // Test 6: Overdue tasks
        $this->info('Test 6: Overdue tasks...');
        $start = microtime(true);
        $tasks = Task::where('tenant_id', $tenantId)
            ->where('end_date', '<', now())
            ->whereNotIn('status', ['done', 'canceled'])
            ->get();
        $time = (microtime(true) - $start) * 1000;
        $queries = count(DB::getQueryLog());
        $results[] = [
            'test' => 'Overdue tasks',
            'time_ms' => round($time, 2),
            'queries' => $queries,
            'count' => $tasks->count()
        ];
        DB::flushQueryLog();
        $this->line("  ✓ Time: {$time}ms, Queries: {$queries}, Results: {$tasks->count()}");

        // Test 7: Projects with ordering (Kanban)
        $this->info('Test 7: Projects with Kanban ordering...');
        $start = microtime(true);
        $projects = Project::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('order', 'asc')
            ->orderBy('updated_at', 'desc')
            ->get();
        $time = (microtime(true) - $start) * 1000;
        $queries = count(DB::getQueryLog());
        $results[] = [
            'test' => 'Projects Kanban ordering',
            'time_ms' => round($time, 2),
            'queries' => $queries,
            'count' => $projects->count()
        ];
        DB::flushQueryLog();
        $this->line("  ✓ Time: {$time}ms, Queries: {$queries}, Results: {$projects->count()}");

        // Test 8: Active users
        $this->info('Test 8: Active users...');
        $start = microtime(true);
        $users = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();
        $time = (microtime(true) - $start) * 1000;
        $queries = count(DB::getQueryLog());
        $results[] = [
            'test' => 'Active users',
            'time_ms' => round($time, 2),
            'queries' => $queries,
            'count' => $users->count()
        ];
        DB::flushQueryLog();
        $this->line("  ✓ Time: {$time}ms, Queries: {$queries}, Results: {$users->count()}");

        // Summary
        $this->newLine();
        $this->info('Performance Summary');
        $this->info('===================');
        
        $table = [];
        foreach ($results as $result) {
            $table[] = [
                'Test' => $result['test'],
                'Time (ms)' => $result['time_ms'],
                'Queries' => $result['queries'],
                'Results' => $result['count'],
                'Status' => $result['time_ms'] < 100 ? '✅ Fast' : ($result['time_ms'] < 500 ? '⚠️ OK' : '❌ Slow')
            ];
        }
        
        $this->table(['Test', 'Time (ms)', 'Queries', 'Results', 'Status'], $table);

        // Check index usage
        $this->newLine();
        $this->info('Checking Index Usage...');
        $this->checkIndexUsage($tenantId);

        return 0;
    }

    /**
     * Check if indexes are being used
     */
    private function checkIndexUsage(string $tenantId): void
    {
        $driver = config('database.default');
        
        if ($driver === 'mysql') {
            // Test one query with EXPLAIN
            $this->info('Running EXPLAIN for projects by priority query...');
            $explain = DB::select("EXPLAIN SELECT * FROM projects WHERE tenant_id = ? AND priority = 'high'", [$tenantId]);
            
            if (!empty($explain)) {
                $row = (array) $explain[0];
                $key = $row['key'] ?? null;
                if ($key && str_contains($key, 'idx_projects_tenant_priority')) {
                    $this->line("  ✅ Index used: {$key}");
                } else {
                    $this->warn("  ⚠️ Index not used. Key: " . ($key ?? 'NULL'));
                }
            }
        } else {
            $this->warn('Index usage check only available for MySQL');
        }
    }
}

