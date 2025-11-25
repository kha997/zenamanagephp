<?php

namespace App\Console\Commands;

use App\Services\OutboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CheckProductionHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:check 
                            {--detailed : Show detailed health information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check production system health';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🏥 Checking system health...');
        $this->newLine();

        $health = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'outbox' => $this->checkOutbox(),
            'search' => $this->checkSearch(),
            'media' => $this->checkMedia(),
        ];

        $allHealthy = true;
        foreach ($health as $component => $status) {
            $icon = $status['healthy'] ? '✅' : '❌';
            $this->line("{$icon} {$component}: {$status['message']}");
            
            if (!$status['healthy']) {
                $allHealthy = false;
            }

            if ($this->option('detailed') && isset($status['details'])) {
                foreach ($status['details'] as $detail) {
                    $this->line("   - {$detail}");
                }
            }
        }

        $this->newLine();
        
        if ($allHealthy) {
            $this->info('✅ System is healthy!');
            return 0;
        } else {
            $this->error('❌ System has health issues. Review above.');
            return 1;
        }
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $tables = [
                'outbox',
                'idempotency_keys',
                'tenants',
                'projects',
                'tasks',
            ];

            $missing = [];
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    $missing[] = $table;
                }
            }

            if (!empty($missing)) {
                return [
                    'healthy' => false,
                    'message' => 'Missing tables: ' . implode(', ', $missing),
                ];
            }

            return [
                'healthy' => true,
                'message' => 'Connected and all tables exist',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value !== 'test') {
                return [
                    'healthy' => false,
                    'message' => 'Cache read/write failed',
                ];
            }

            return [
                'healthy' => true,
                'message' => 'Working correctly',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkOutbox(): array
    {
        try {
            $outboxService = app(OutboxService::class);
            $metrics = $outboxService->getMetrics();

            $healthStatus = $metrics['health_status'];
            $message = "Status: {$healthStatus}";
            
            if ($this->option('detailed')) {
                $message .= " (Pending: {$metrics['pending']}, Failed: {$metrics['failed']})";
            }

            return [
                'healthy' => $healthStatus === 'healthy',
                'message' => $message,
                'details' => [
                    "Pending: {$metrics['pending']}",
                    "Processing: {$metrics['processing']}",
                    "Completed: {$metrics['completed']}",
                    "Failed: {$metrics['failed']}",
                    "Avg processing time: {$metrics['avg_processing_time_seconds']}s",
                ],
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkSearch(): array
    {
        $driver = config('scout.driver');
        
        if ($driver !== 'meilisearch') {
            return [
                'healthy' => true,
                'message' => 'Not configured (using database search)',
            ];
        }

        $host = config('scout.meilisearch.host');
        if (empty($host)) {
            return [
                'healthy' => false,
                'message' => 'Meilisearch host not configured',
            ];
        }

        // Try to connect to Meilisearch
        try {
            $client = new \Meilisearch\Client($host, config('scout.meilisearch.key'));
            $health = $client->health();
            
            return [
                'healthy' => true,
                'message' => 'Connected to Meilisearch',
                'details' => [
                    'Status: ' . ($health['status'] ?? 'unknown'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkMedia(): array
    {
        $checks = [];
        
        if (!Schema::hasColumn('tenants', 'media_quota_mb')) {
            return [
                'healthy' => false,
                'message' => 'Media quota columns missing (run migrations)',
            ];
        }

        $virusScanEnabled = config('media.virus_scan_enabled', false);
        $checks[] = "Virus scan: " . ($virusScanEnabled ? 'enabled' : 'disabled');

        $cdnEnabled = config('media.cdn_enabled', false);
        $checks[] = "CDN: " . ($cdnEnabled ? 'enabled' : 'disabled');

        return [
            'healthy' => true,
            'message' => 'Configuration OK',
            'details' => $checks,
        ];
    }
}

