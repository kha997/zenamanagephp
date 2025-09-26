<?php

namespace App\Console\Commands;

use App\Services\MetricsCollectionService;
use App\Services\StructuredLoggingService;
use Illuminate\Console\Command;

class CollectMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:collect {--store : Store metrics in cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect and optionally store application metrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Collecting application metrics...');
        
        try {
            $metrics = MetricsCollectionService::collectAllMetrics();
            
            if ($this->option('store')) {
                MetricsCollectionService::storeMetrics($metrics);
                $this->info('Metrics stored in cache');
            }
            
            // Log metrics collection
            StructuredLoggingService::logEvent('metrics_collected', [
                'metrics_count' => count($metrics),
                'stored' => $this->option('store'),
                'timestamp' => now()->toISOString(),
            ]);
            
            $this->info('Metrics collected successfully');
            $this->line('Application: ' . $metrics['application']['app_version']);
            $this->line('Memory Usage: ' . $metrics['application']['memory_usage_mb'] . ' MB');
            $this->line('Database: ' . $metrics['database']['connection_status']);
            $this->line('Cache: ' . $metrics['cache']['driver']);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Failed to collect metrics', $e);
            
            $this->error('Failed to collect metrics: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
