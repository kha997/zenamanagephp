<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MetricsCollector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class CollectMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:collect 
                            {--format=json : Output format (json, prometheus, file)}
                            {--output= : Output file path}
                            {--log : Log metrics to Laravel log}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect and export application metrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Collecting application metrics...');
        
        try {
            $metricsCollector = new MetricsCollector();
            $format = $this->option('format');
            $output = $this->option('output');
            $log = $this->option('log');

            switch ($format) {
                case 'prometheus':
                    $data = $metricsCollector->exportPrometheusFormat();
                    $this->outputPrometheus($data, $output);
                    break;
                    
                case 'file':
                    $data = $metricsCollector->collectAll();
                    $this->outputToFile($data, $output);
                    break;
                    
                case 'json':
                default:
                    $data = $metricsCollector->collectAll();
                    $this->outputJson($data, $output);
                    break;
            }

            if ($log) {
                Log::info('Metrics collected', $data);
                $this->info('📝 Metrics logged to Laravel log');
            }

            $this->info('✅ Metrics collection completed successfully');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to collect metrics: ' . $e->getMessage());
            Log::error('Metrics collection failed', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }

    private function outputJson(array $data, ?string $output): void
    {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT);
        
        if ($output) {
            File::put($output, $jsonData);
            $this->info("📄 Metrics saved to: {$output}");
        } else {
            $this->line($jsonData);
        }
    }

    private function outputPrometheus(string $data, ?string $output): void
    {
        if ($output) {
            File::put($output, $data);
            $this->info("📄 Prometheus metrics saved to: {$output}");
        } else {
            $this->line($data);
        }
    }

    private function outputToFile(array $data, ?string $output): void
    {
        $filename = $output ?: storage_path('logs/metrics_' . date('Y-m-d_H-i-s') . '.json');
        
        File::put($filename, json_encode($data, JSON_PRETTY_PRINT));
        $this->info("📄 Metrics saved to: {$filename}");
    }
}
