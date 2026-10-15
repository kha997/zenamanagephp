<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PerformanceMetricsService;
use Illuminate\Console\Command;

/**
 * Command để monitor và log performance metrics
 * Chạy định kỳ để theo dõi hiệu suất hệ thống.
 */
class PerformanceMonitorCommand extends Command
{
    protected $signature = 'performance:monitor {--log : Log metrics to file}';

    protected $description = 'Monitor system performance metrics';

    public function __construct(
        private PerformanceMetricsService $metricsService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Collecting performance metrics...');

        $metrics = $this->metricsService->getMetrics();

        // Display metrics in console
        $this->displayMetrics($metrics);

        // Log to file if requested
        if ($this->option('log')) {
            $this->metricsService->logMetrics();
            $this->info('📝 Metrics logged to file.');
        }

        return Command::SUCCESS;
    }

    /**
     * Display metrics in formatted table.
     *
     * @param array $metrics
     * @return void
     */
    private function displayMetrics(array $metrics): void
    {
        // Database metrics
        $this->info('📊 Database Metrics:');
        $dbData = [];
        foreach ($metrics['database'] as $key => $value) {
            $dbData[] = [ucwords(str_replace('_', ' ', $key)), $value];
        }
        $this->table(['Metric', 'Value'], $dbData);

        // Cache metrics
        $this->info('🗄️ Cache Metrics:');
        $cacheData = [];
        foreach ($metrics['cache'] as $key => $value) {
            $cacheData[] = [ucwords(str_replace('_', ' ', $key)), $value];
        }
        $this->table(['Metric', 'Value'], $cacheData);

        // Memory metrics
        $this->info('💾 Memory Metrics:');
        $memoryData = [];
        foreach ($metrics['memory'] as $key => $value) {
            if (str_ends_with($key, '_formatted')) {
                $memoryData[] = [ucwords(str_replace(['_formatted', '_'], [' ', ' '], $key)), $value];
            }
        }
        $this->table(['Metric', 'Value'], $memoryData);
    }
}
