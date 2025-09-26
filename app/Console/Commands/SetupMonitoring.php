<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SetupMonitoring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:setup 
                            {--alert-email= : Email for alerts}
                            {--slack-webhook= : Slack webhook URL}
                            {--check-interval=300 : Check interval in seconds}
                            {--alert-threshold=100 : Alert threshold for failed emails}
                            {--interactive : Interactive setup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up monitoring and alerting system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📊 Setting up Monitoring and Alerting System');
        $this->newLine();

        if ($this->option('interactive')) {
            $this->interactiveSetup();
        } else {
            $this->commandLineSetup();
        }

        $this->newLine();
        $this->info('✅ Monitoring setup completed!');
        
        // Test monitoring
        if ($this->confirm('Would you like to test the monitoring system?')) {
            $this->testMonitoring();
        }
    }

    /**
     * Interactive setup
     */
    private function interactiveSetup(): void
    {
        $this->info('🔧 Interactive Monitoring Setup');
        $this->newLine();

        // Email alerts
        $alertEmail = $this->ask('Alert email address');
        $slackWebhook = $this->ask('Slack webhook URL (optional)');
        $checkInterval = $this->ask('Check interval (seconds)', '300');
        $alertThreshold = $this->ask('Alert threshold for failed emails', '100');

        $this->updateEnvironmentFile([
            'alert_email' => $alertEmail,
            'slack_webhook' => $slackWebhook,
            'check_interval' => $checkInterval,
            'alert_threshold' => $alertThreshold,
        ]);

        $this->setupCronJobs($checkInterval);
    }

    /**
     * Command line setup
     */
    private function commandLineSetup(): void
    {
        $alertEmail = $this->option('alert-email');
        $slackWebhook = $this->option('slack-webhook');
        $checkInterval = $this->option('check-interval');
        $alertThreshold = $this->option('alert-threshold');

        if (!$alertEmail) {
            $this->error('Alert email is required. Use --alert-email option.');
            return;
        }

        $this->updateEnvironmentFile([
            'alert_email' => $alertEmail,
            'slack_webhook' => $slackWebhook,
            'check_interval' => $checkInterval,
            'alert_threshold' => $alertThreshold,
        ]);

        $this->setupCronJobs($checkInterval);
    }

    /**
     * Update environment file
     */
    private function updateEnvironmentFile(array $config): void
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->error('Environment file not found. Please create .env file first.');
            return;
        }

        $envContent = File::get($envPath);
        
        // Update monitoring configuration
        $envContent = $this->updateEnvValue($envContent, 'MONITORING_ENABLED', 'true');
        $envContent = $this->updateEnvValue($envContent, 'MONITORING_EMAIL_ALERTS', 'true');
        $envContent = $this->updateEnvValue($envContent, 'MONITORING_ALERT_EMAIL', '"' . $config['alert_email'] . '"');
        
        if ($config['slack_webhook']) {
            $envContent = $this->updateEnvValue($envContent, 'MONITORING_SLACK_WEBHOOK', $config['slack_webhook']);
        }
        
        $envContent = $this->updateEnvValue($envContent, 'MONITORING_CHECK_INTERVAL', $config['check_interval']);
        $envContent = $this->updateEnvValue($envContent, 'MONITORING_ALERT_THRESHOLD', $config['alert_threshold']);

        File::put($envPath, $envContent);
        
        $this->info('Environment file updated with monitoring configuration!');
    }

    /**
     * Update environment value
     */
    private function updateEnvValue(string $content, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";
        
        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $replacement, $content);
        } else {
            return $content . "\n{$replacement}";
        }
    }

    /**
     * Setup cron jobs
     */
    private function setupCronJobs(string $checkInterval): void
    {
        $this->info('⏰ Setting up cron jobs...');
        
        $cronJobs = [
            "*/5 * * * * cd " . base_path() . " && php artisan email:monitor --send-alerts",
            "0 2 * * * cd " . base_path() . " && php artisan email:warm-cache",
            "0 3 * * * cd " . base_path() . " && php artisan queue:restart",
        ];

        $cronFile = '/tmp/zenamanage_cron';
        file_put_contents($cronFile, implode("\n", $cronJobs) . "\n");

        $this->info('Cron jobs created:');
        foreach ($cronJobs as $job) {
            $this->line("  {$job}");
        }

        $this->newLine();
        $this->warn('To install cron jobs, run:');
        $this->line("crontab {$cronFile}");
        $this->newLine();
    }

    /**
     * Test monitoring
     */
    private function testMonitoring(): void
    {
        $this->info('🧪 Testing Monitoring System...');
        
        try {
            Artisan::call('email:monitor', [
                '--send-alerts' => true
            ]);

            $output = Artisan::output();
            $this->line($output);

            if (strpos($output, 'No alerts detected') !== false) {
                $this->info('🎉 Monitoring system test successful!');
            } else {
                $this->warn('⚠️ Alerts detected during test. Check your configuration.');
            }
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
        }
    }
}