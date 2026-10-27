<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:test 
                            {--test-email= : Email address for testing}
                            {--comprehensive : Run comprehensive tests}
                            {--quick : Run quick tests only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run comprehensive system tests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 ZenaManage System Test Suite');
        $this->newLine();

        $testEmail = $this->option('test-email');
        $comprehensive = $this->option('comprehensive');
        $quick = $this->option('quick');

        $results = [];

        // Basic tests
        $results['database'] = $this->testDatabase();
        $results['redis'] = $this->testRedis();
        $results['cache'] = $this->testCache();
        $results['mail'] = $this->testMail($testEmail);
        $results['queue'] = $this->testQueue();

        if ($comprehensive) {
            $results['email_templates'] = $this->testEmailTemplates();
            $results['monitoring'] = $this->testMonitoring();
            $results['workers'] = $this->testWorkers();
            $results['performance'] = $this->testPerformance();
        }

        $this->displayResults($results);

        $passed = array_sum(array_column($results, 'passed'));
        $total = count($results);

        $this->newLine();
        if ($passed === $total) {
            $this->info("🎉 All tests passed! ({$passed}/{$total})");

            return 0;
        } else {
            $this->error("❌ Some tests failed! ({$passed}/{$total})");

            return 1;
        }
    }

    /**
     * Test database connection.
     */
    private function testDatabase(): array
    {
        $this->info('🗄️ Testing Database Connection...');

        try {
            DB::connection()->getPdo();
            $tables = DB::select('SHOW TABLES');
            $tableCount = count($tables);

            $this->line('  ✅ Database connected successfully');
            $this->line("  📊 Found {$tableCount} tables");

            return [
                'name' => 'Database Connection',
                'passed' => true,
                'details' => "Connected to database with {$tableCount} tables",
            ];
        } catch (\Exception $e) {
            $this->line('  ❌ Database connection failed: ' . $e->getMessage());

            return [
                'name' => 'Database Connection',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test Redis connection.
     */
    private function testRedis(): array
    {
        $this->info('🔴 Testing Redis Connection...');

        try {
            Redis::ping();
            $info = Redis::info();

            $this->line('  ✅ Redis connected successfully');
            $this->line('  📊 Redis version: ' . ($info['redis_version'] ?? 'Unknown'));

            return [
                'name' => 'Redis Connection',
                'passed' => true,
                'details' => 'Redis connected successfully',
            ];
        } catch (\Exception $e) {
            $this->line('  ❌ Redis connection failed: ' . $e->getMessage());

            return [
                'name' => 'Redis Connection',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test cache system.
     */
    private function testCache(): array
    {
        $this->info('💾 Testing Cache System...');

        try {
            $key = 'test_cache_' . time();
            $value = 'test_value_' . rand(1000, 9999);

            Cache::put($key, $value, 60);
            $retrieved = Cache::get($key);
            Cache::forget($key);

            if ($retrieved === $value) {
                $this->line('  ✅ Cache system working correctly');

                return [
                    'name' => 'Cache System',
                    'passed' => true,
                    'details' => 'Cache read/write operations successful',
                ];
            } else {
                $this->line('  ❌ Cache system failed: Value mismatch');

                return [
                    'name' => 'Cache System',
                    'passed' => false,
                    'details' => 'Value mismatch in cache operations',
                ];
            }
        } catch (\Exception $e) {
            $this->line('  ❌ Cache system failed: ' . $e->getMessage());

            return [
                'name' => 'Cache System',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test mail system.
     */
    private function testMail(?string $testEmail): array
    {
        $this->info('📧 Testing Mail System...');

        if (!$testEmail) {
            $this->line('  ⚠️ No test email provided, skipping mail test');

            return [
                'name' => 'Mail System',
                'passed' => true,
                'details' => 'Skipped - no test email provided',
            ];
        }

        try {
            Artisan::call('email:test', [
                'email' => $testEmail,
                '--sync' => true,
            ]);

            $output = Artisan::output();

            if (strpos($output, '✅') !== false) {
                $this->line('  ✅ Mail system working correctly');

                return [
                    'name' => 'Mail System',
                    'passed' => true,
                    'details' => 'Test email sent successfully',
                ];
            } else {
                $this->line('  ❌ Mail system failed');

                return [
                    'name' => 'Mail System',
                    'passed' => false,
                    'details' => 'Test email failed',
                ];
            }
        } catch (\Exception $e) {
            $this->line('  ❌ Mail system failed: ' . $e->getMessage());

            return [
                'name' => 'Mail System',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test queue system.
     */
    private function testQueue(): array
    {
        $this->info('🚀 Testing Queue System...');

        try {
            $queues = ['emails-high', 'emails-medium', 'emails-low', 'emails-welcome'];
            $totalPending = 0;
            $totalFailed = 0;

            foreach ($queues as $queue) {
                $pending = Redis::llen("queues:{$queue}");
                $failed = Redis::llen("queues:{$queue}:failed");
                $totalPending += $pending;
                $totalFailed += $failed;
            }

            $this->line('  ✅ Queue system accessible');
            $this->line("  📊 Total pending jobs: {$totalPending}");
            $this->line("  📊 Total failed jobs: {$totalFailed}");

            return [
                'name' => 'Queue System',
                'passed' => true,
                'details' => "Queue system accessible with {$totalPending} pending jobs",
            ];
        } catch (\Exception $e) {
            $this->line('  ❌ Queue system failed: ' . $e->getMessage());

            return [
                'name' => 'Queue System',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test email templates.
     */
    private function testEmailTemplates(): array
    {
        $this->info('📧 Testing Email Templates...');

        try {
            Artisan::call('email:warm-cache');
            $output = Artisan::output();

            if (strpos($output, 'cached successfully') !== false) {
                $this->line('  ✅ Email templates working correctly');

                return [
                    'name' => 'Email Templates',
                    'passed' => true,
                    'details' => 'Email templates cached successfully',
                ];
            } else {
                $this->line('  ❌ Email templates failed');

                return [
                    'name' => 'Email Templates',
                    'passed' => false,
                    'details' => 'Email template caching failed',
                ];
            }
        } catch (\Exception $e) {
            $this->line('  ❌ Email templates failed: ' . $e->getMessage());

            return [
                'name' => 'Email Templates',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test monitoring system.
     */
    private function testMonitoring(): array
    {
        $this->info('📊 Testing Monitoring System...');

        try {
            Artisan::call('email:monitor');
            $output = Artisan::output();

            if (strpos($output, 'Email System Health Status') !== false) {
                $this->line('  ✅ Monitoring system working correctly');

                return [
                    'name' => 'Monitoring System',
                    'passed' => true,
                    'details' => 'Monitoring system accessible',
                ];
            } else {
                $this->line('  ❌ Monitoring system failed');

                return [
                    'name' => 'Monitoring System',
                    'passed' => false,
                    'details' => 'Monitoring system failed',
                ];
            }
        } catch (\Exception $e) {
            $this->line('  ❌ Monitoring system failed: ' . $e->getMessage());

            return [
                'name' => 'Monitoring System',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test workers.
     */
    private function testWorkers(): array
    {
        $this->info('👷 Testing Workers...');

        try {
            Artisan::call('workers:status');
            $output = Artisan::output();

            if (strpos($output, 'Queue Workers Status') !== false) {
                $this->line('  ✅ Workers status accessible');

                return [
                    'name' => 'Workers',
                    'passed' => true,
                    'details' => 'Workers status accessible',
                ];
            } else {
                $this->line('  ❌ Workers test failed');

                return [
                    'name' => 'Workers',
                    'passed' => false,
                    'details' => 'Workers status failed',
                ];
            }
        } catch (\Exception $e) {
            $this->line('  ❌ Workers test failed: ' . $e->getMessage());

            return [
                'name' => 'Workers',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test performance.
     */
    private function testPerformance(): array
    {
        $this->info('⚡ Testing Performance...');

        try {
            $startTime = microtime(true);

            // Test database query performance
            $users = DB::table('users')->count();
            $invitations = DB::table('invitations')->count();

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            $this->line('  ✅ Performance test completed');
            $this->line("  📊 Database query time: {$executionTime}ms");
            $this->line("  📊 Users count: {$users}");
            $this->line("  📊 Invitations count: {$invitations}");

            return [
                'name' => 'Performance',
                'passed' => true,
                'details' => "Database queries completed in {$executionTime}ms",
            ];
        } catch (\Exception $e) {
            $this->line('  ❌ Performance test failed: ' . $e->getMessage());

            return [
                'name' => 'Performance',
                'passed' => false,
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Display test results.
     */
    private function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📋 Test Results Summary:');
        $this->newLine();

        $tableData = [];
        foreach ($results as $result) {
            $status = $result['passed'] ? '✅ Pass' : '❌ Fail';
            $tableData[] = [
                'Test' => $result['name'],
                'Status' => $status,
                'Details' => $result['details'],
            ];
        }

        $this->table(['Test', 'Status', 'Details'], $tableData);
    }
}
