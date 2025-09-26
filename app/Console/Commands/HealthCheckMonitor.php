<?php

namespace App\Console\Commands;

use App\Services\HealthCheckService;
use App\Services\StructuredLoggingService;
use Illuminate\Console\Command;

class HealthCheckMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:check {--detailed : Show detailed health information} {--log : Log health check results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform comprehensive health checks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Performing health checks...');
        
        try {
            $startTime = microtime(true);
            $health = HealthCheckService::performHealthChecks();
            $duration = microtime(true) - $startTime;
            
            // Display overall status
            $this->displayOverallStatus($health);
            
            // Display detailed checks if requested
            if ($this->option('detailed')) {
                $this->displayDetailedChecks($health);
            }
            
            // Display summary
            $this->displaySummary($health, $duration);
            
            // Log results if requested
            if ($this->option('log')) {
                StructuredLoggingService::logEvent('health_check_monitor', [
                    'status' => $health['status'],
                    'duration_ms' => round($duration * 1000, 2),
                    'summary' => $health['summary'],
                ]);
                $this->info('Health check results logged');
            }
            
            return $health['status'] === 'healthy' ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Health check monitor failed', $e);
            
            $this->error('Health check failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display overall health status
     */
    protected function displayOverallStatus(array $health): void
    {
        $status = $health['status'];
        $statusColor = match($status) {
            'healthy' => 'green',
            'degraded' => 'yellow',
            'unhealthy' => 'red',
            default => 'white'
        };
        
        $this->line('');
        $this->line("Overall Status: <fg={$statusColor}>{$status}</>");
        $this->line("Timestamp: {$health['timestamp']}");
        $this->line("Version: {$health['version']}");
        $this->line("Environment: {$health['environment']}");
        $this->line('');
    }

    /**
     * Display detailed health checks
     */
    protected function displayDetailedChecks(array $health): void
    {
        $this->line('Detailed Health Checks:');
        $this->line('======================');
        
        foreach ($health['checks'] as $checkName => $check) {
            $status = $check['status'];
            $statusColor = match($status) {
                'healthy' => 'green',
                'warning' => 'yellow',
                'unhealthy' => 'red',
                'skipped' => 'blue',
                default => 'white'
            };
            
            $this->line("");
            $this->line("<fg={$statusColor}>{$checkName}: {$status}</>");
            $this->line("  Message: {$check['message']}");
            
            if (isset($check['error'])) {
                $this->line("  Error: {$check['error']}");
            }
            
            if (isset($check['details']) && is_array($check['details'])) {
                $this->line("  Details:");
                foreach ($check['details'] as $key => $value) {
                    if (is_array($value)) {
                        $this->line("    {$key}: " . json_encode($value));
                    } else {
                        $this->line("    {$key}: {$value}");
                    }
                }
            }
        }
    }

    /**
     * Display health check summary
     */
    protected function displaySummary(array $health, float $duration): void
    {
        $summary = $health['summary'];
        
        $this->line('');
        $this->line('Health Check Summary:');
        $this->line('====================');
        $this->line("Total Checks: {$summary['total_checks']}");
        $this->line("Healthy: <fg=green>{$summary['healthy']}</>");
        $this->line("Unhealthy: <fg=red>{$summary['unhealthy']}</>");
        $this->line("Warning: <fg=yellow>{$summary['warning']}</>");
        $this->line("Skipped: <fg=blue>{$summary['skipped']}</>");
        $this->line("Health Percentage: {$summary['health_percentage']}%");
        $this->line("Check Duration: " . round($duration * 1000, 2) . "ms");
        $this->line('');
        
        // Display recommendations
        $this->displayRecommendations($health);
    }

    /**
     * Display health check recommendations
     */
    protected function displayRecommendations(array $health): void
    {
        $recommendations = [];
        
        foreach ($health['checks'] as $checkName => $check) {
            if ($check['status'] === 'unhealthy') {
                $recommendations[] = "Fix {$checkName}: {$check['message']}";
            } elseif ($check['status'] === 'warning') {
                $recommendations[] = "Monitor {$checkName}: {$check['message']}";
            }
        }
        
        if (!empty($recommendations)) {
            $this->line('Recommendations:');
            $this->line('================');
            foreach ($recommendations as $recommendation) {
                $this->line("• {$recommendation}");
            }
            $this->line('');
        }
    }
}
