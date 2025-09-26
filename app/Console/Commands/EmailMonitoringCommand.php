<?php

namespace App\Console\Commands;

use App\Models\EmailTracking;
use App\Services\EmailService;
use App\Services\QueueManagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailMonitoringCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:monitor 
                            {--alert-threshold=100 : Alert threshold for failed emails}
                            {--check-interval=300 : Check interval in seconds}
                            {--send-alerts : Send actual alerts (not just log)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor email system health and send alerts';

    protected $emailService;
    protected $queueService;

    public function __construct(EmailService $emailService, QueueManagementService $queueService)
    {
        parent::__construct();
        $this->emailService = $emailService;
        $this->queueService = $queueService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting email system monitoring...');

        $alertThreshold = $this->option('alert-threshold');
        $checkInterval = $this->option('check-interval');
        $sendAlerts = $this->option('send-alerts');

        try {
            // Get system health
            $health = $this->checkSystemHealth();
            
            // Display health status
            $this->displayHealthStatus($health);

            // Check for alerts
            $alerts = $this->checkForAlerts($health, $alertThreshold);

            if (!empty($alerts)) {
                $this->warn('Alerts detected:');
                foreach ($alerts as $alert) {
                    $this->warn("  - {$alert}");
                }

                if ($sendAlerts) {
                    $this->sendAlerts($alerts);
                }
            } else {
                $this->info('No alerts detected. System is healthy.');
            }

            // Log monitoring results
            $this->logMonitoringResults($health, $alerts);

            return 0;
        } catch (\Exception $e) {
            $this->error('Monitoring failed: ' . $e->getMessage());
            Log::error('Email monitoring failed', [
                'error' => $e->getMessage(),
            ]);
            return 1;
        }
    }

    /**
     * Check system health
     */
    private function checkSystemHealth(): array
    {
        $health = [
            'timestamp' => now()->toISOString(),
            'email_stats' => $this->getEmailStats(),
            'queue_stats' => $this->queueService->getQueueStats(),
            'queue_health' => $this->queueService->getHealthStatus(),
            'system_metrics' => $this->getSystemMetrics(),
        ];

        return $health;
    }

    /**
     * Get email statistics
     */
    private function getEmailStats(): array
    {
        $now = now();
        $last24Hours = $now->copy()->subHours(24);
        $lastHour = $now->copy()->subHour();

        $stats = [
            'last_24_hours' => [
                'total_sent' => EmailTracking::where('created_at', '>=', $last24Hours)->count(),
                'total_delivered' => EmailTracking::where('created_at', '>=', $last24Hours)
                    ->where('status', 'delivered')->count(),
                'total_failed' => EmailTracking::where('created_at', '>=', $last24Hours)
                    ->where('status', 'failed')->count(),
                'total_bounced' => EmailTracking::where('created_at', '>=', $last24Hours)
                    ->where('status', 'bounced')->count(),
            ],
            'last_hour' => [
                'total_sent' => EmailTracking::where('created_at', '>=', $lastHour)->count(),
                'total_delivered' => EmailTracking::where('created_at', '>=', $lastHour)
                    ->where('status', 'delivered')->count(),
                'total_failed' => EmailTracking::where('created_at', '>=', $lastHour)
                    ->where('status', 'failed')->count(),
                'total_bounced' => EmailTracking::where('created_at', '>=', $lastHour)
                    ->where('status', 'bounced')->count(),
            ],
            'delivery_rate' => $this->calculateDeliveryRate($last24Hours),
            'failure_rate' => $this->calculateFailureRate($last24Hours),
        ];

        return $stats;
    }

    /**
     * Calculate delivery rate
     */
    private function calculateDeliveryRate(Carbon $since): float
    {
        $totalSent = EmailTracking::where('created_at', '>=', $since)->count();
        $totalDelivered = EmailTracking::where('created_at', '>=', $since)
            ->where('status', 'delivered')->count();

        if ($totalSent === 0) {
            return 0;
        }

        return round(($totalDelivered / $totalSent) * 100, 2);
    }

    /**
     * Calculate failure rate
     */
    private function calculateFailureRate(Carbon $since): float
    {
        $totalSent = EmailTracking::where('created_at', '>=', $since)->count();
        $totalFailed = EmailTracking::where('created_at', '>=', $since)
            ->whereIn('status', ['failed', 'bounced'])->count();

        if ($totalSent === 0) {
            return 0;
        }

        return round(($totalFailed / $totalSent) * 100, 2);
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'disk_free_space' => disk_free_space('/'),
            'load_average' => sys_getloadavg(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * Check for alerts
     */
    private function checkForAlerts(array $health, int $alertThreshold): array
    {
        $alerts = [];

        // Check delivery rate
        if ($health['email_stats']['delivery_rate'] < 90) {
            $alerts[] = "Low delivery rate: {$health['email_stats']['delivery_rate']}%";
        }

        // Check failure rate
        if ($health['email_stats']['failure_rate'] > 10) {
            $alerts[] = "High failure rate: {$health['email_stats']['failure_rate']}%";
        }

        // Check failed emails count
        if ($health['email_stats']['last_24_hours']['total_failed'] > $alertThreshold) {
            $alerts[] = "High number of failed emails: {$health['email_stats']['last_24_hours']['total_failed']}";
        }

        // Check queue health
        if ($health['queue_health']['status'] !== 'healthy') {
            $alerts[] = "Queue health issue: {$health['queue_health']['status']}";
        }

        // Check queue pending jobs
        if ($health['queue_stats']['total_jobs'] > 1000) {
            $alerts[] = "High number of pending jobs: {$health['queue_stats']['total_jobs']}";
        }

        // Check memory usage
        $memoryUsageMB = round($health['system_metrics']['memory_usage'] / 1024 / 1024, 2);
        if ($memoryUsageMB > 512) {
            $alerts[] = "High memory usage: {$memoryUsageMB}MB";
        }

        return $alerts;
    }

    /**
     * Send alerts
     */
    private function sendAlerts(array $alerts): void
    {
        $alertEmail = config('monitoring.alert_email');
        $slackWebhook = config('monitoring.slack_webhook');

        if ($alertEmail) {
            $this->sendEmailAlert($alertEmail, $alerts);
        }

        if ($slackWebhook) {
            $this->sendSlackAlert($slackWebhook, $alerts);
        }
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert(string $email, array $alerts): void
    {
        try {
            $subject = 'ZenaManage Email System Alert';
            $message = "Email system alerts detected:\n\n";
            
            foreach ($alerts as $alert) {
                $message .= "• {$alert}\n";
            }
            
            $message .= "\nTime: " . now()->toDateTimeString();
            $message .= "\nSystem: " . config('app.name');

            Mail::raw($message, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });

            $this->info("Email alert sent to: {$email}");
        } catch (\Exception $e) {
            $this->error("Failed to send email alert: " . $e->getMessage());
            Log::error('Failed to send email alert', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send Slack alert
     */
    private function sendSlackAlert(string $webhook, array $alerts): void
    {
        try {
            $message = "🚨 *ZenaManage Email System Alert*\n\n";
            
            foreach ($alerts as $alert) {
                $message .= "• {$alert}\n";
            }
            
            $message .= "\n_Time: " . now()->toDateTimeString() . "_";
            $message .= "\n_System: " . config('app.name') . "_";

            $payload = [
                'text' => $message,
                'username' => 'ZenaManage Monitor',
                'icon_emoji' => ':warning:',
            ];

            Http::post($webhook, $payload);

            $this->info('Slack alert sent successfully');
        } catch (\Exception $e) {
            $this->error("Failed to send Slack alert: " . $e->getMessage());
            Log::error('Failed to send Slack alert', [
                'webhook' => $webhook,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display health status
     */
    private function displayHealthStatus(array $health): void
    {
        $this->info('Email System Health Status:');
        $this->newLine();

        // Email Statistics
        $this->info('📧 Email Statistics (Last 24 Hours):');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Sent', $health['email_stats']['last_24_hours']['total_sent']],
                ['Total Delivered', $health['email_stats']['last_24_hours']['total_delivered']],
                ['Total Failed', $health['email_stats']['last_24_hours']['total_failed']],
                ['Total Bounced', $health['email_stats']['last_24_hours']['total_bounced']],
                ['Delivery Rate', $health['email_stats']['delivery_rate'] . '%'],
                ['Failure Rate', $health['email_stats']['failure_rate'] . '%'],
            ]
        );

        // Queue Statistics
        $this->info('🚀 Queue Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Connection', $health['queue_stats']['connection']],
                ['Total Jobs', $health['queue_stats']['total_jobs']],
                ['Total Failed', $health['queue_stats']['total_failed']],
                ['Active Workers', count($health['queue_stats']['workers'])],
            ]
        );

        // Queue Health
        $this->info('💚 Queue Health:');
        $statusColor = $health['queue_health']['status'] === 'healthy' ? 'green' : 'red';
        $this->line("Status: <fg={$statusColor}>{$health['queue_health']['status']}</>");
        
        if (!empty($health['queue_health']['issues'])) {
            $this->warn('Issues:');
            foreach ($health['queue_health']['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
        }

        // System Metrics
        $this->info('💻 System Metrics:');
        $memoryUsageMB = round($health['system_metrics']['memory_usage'] / 1024 / 1024, 2);
        $memoryPeakMB = round($health['system_metrics']['memory_peak'] / 1024 / 1024, 2);
        $diskFreeGB = round($health['system_metrics']['disk_free_space'] / 1024 / 1024 / 1024, 2);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Memory Usage', $memoryUsageMB . 'MB'],
                ['Memory Peak', $memoryPeakMB . 'MB'],
                ['Disk Free Space', $diskFreeGB . 'GB'],
                ['PHP Version', $health['system_metrics']['php_version']],
                ['Laravel Version', $health['system_metrics']['laravel_version']],
            ]
        );
    }

    /**
     * Log monitoring results
     */
    private function logMonitoringResults(array $health, array $alerts): void
    {
        Log::info('Email system monitoring completed', [
            'health' => $health,
            'alerts' => $alerts,
            'alert_count' => count($alerts),
        ]);
    }
}