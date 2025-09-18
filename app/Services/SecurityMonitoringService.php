<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Security Monitoring Service
 * 
 * Monitors and alerts on security events
 */
class SecurityMonitoringService
{
    private SecureAuditService $auditService;
    private array $alertThresholds;

    public function __construct(SecureAuditService $auditService)
    {
        $this->auditService = $auditService;
        $this->alertThresholds = config('security.monitoring.thresholds', [
            'failed_logins_per_hour' => 10,
            'suspicious_requests_per_hour' => 20,
            'file_uploads_per_hour' => 50,
            'admin_actions_per_hour' => 100
        ]);
    }

    /**
     * Monitor failed login attempts
     */
    public function monitorFailedLogins(string $ip, string $email = null): void
    {
        $key = "failed_logins:{$ip}";
        $count = Cache::get($key, 0) + 1;
        
        Cache::put($key, $count, 3600); // 1 hour

        // Log failed login
        $this->auditService->logAction(
            userId: 'system',
            action: 'login_failed',
            entityType: 'Authentication',
            entityId: $email ?? $ip,
            newData: [
                'ip_address' => $ip,
                'email' => $email,
                'attempt_count' => $count
            ]
        );

        // Check threshold
        if ($count >= $this->alertThresholds['failed_logins_per_hour']) {
            $this->triggerAlert('failed_logins_threshold', [
                'ip' => $ip,
                'email' => $email,
                'count' => $count,
                'threshold' => $this->alertThresholds['failed_logins_per_hour']
            ]);
        }
    }

    /**
     * Monitor suspicious requests
     */
    public function monitorSuspiciousRequest(string $ip, string $path, array $details = []): void
    {
        $key = "suspicious_requests:{$ip}";
        $count = Cache::get($key, 0) + 1;
        
        Cache::put($key, $count, 3600); // 1 hour

        // Log suspicious request
        $this->auditService->logAction(
            userId: 'system',
            action: 'suspicious_request',
            entityType: 'Security',
            entityId: $ip,
            newData: array_merge([
                'ip_address' => $ip,
                'path' => $path,
                'attempt_count' => $count
            ], $details)
        );

        // Check threshold
        if ($count >= $this->alertThresholds['suspicious_requests_per_hour']) {
            $this->triggerAlert('suspicious_requests_threshold', [
                'ip' => $ip,
                'path' => $path,
                'count' => $count,
                'threshold' => $this->alertThresholds['suspicious_requests_per_hour'],
                'details' => $details
            ]);
        }
    }

    /**
     * Monitor file uploads
     */
    public function monitorFileUpload(string $userId, string $filename, bool $successful = true): void
    {
        $key = "file_uploads:{$userId}";
        $count = Cache::get($key, 0) + 1;
        
        Cache::put($key, $count, 3600); // 1 hour

        // Log file upload
        $this->auditService->logAction(
            userId: $userId,
            action: $successful ? 'file_upload_success' : 'file_upload_failed',
            entityType: 'File',
            entityId: $filename,
            newData: [
                'filename' => $filename,
                'upload_count' => $count,
                'successful' => $successful
            ]
        );

        // Check threshold
        if ($count >= $this->alertThresholds['file_uploads_per_hour']) {
            $this->triggerAlert('file_uploads_threshold', [
                'user_id' => $userId,
                'filename' => $filename,
                'count' => $count,
                'threshold' => $this->alertThresholds['file_uploads_per_hour']
            ]);
        }
    }

    /**
     * Monitor admin actions
     */
    public function monitorAdminAction(string $userId, string $action, array $details = []): void
    {
        $key = "admin_actions:{$userId}";
        $count = Cache::get($key, 0) + 1;
        
        Cache::put($key, $count, 3600); // 1 hour

        // Log admin action
        $this->auditService->logAction(
            userId: $userId,
            action: "admin_{$action}",
            entityType: 'Admin',
            entityId: $userId,
            newData: array_merge([
                'action' => $action,
                'action_count' => $count
            ], $details)
        );

        // Check threshold
        if ($count >= $this->alertThresholds['admin_actions_per_hour']) {
            $this->triggerAlert('admin_actions_threshold', [
                'user_id' => $userId,
                'action' => $action,
                'count' => $count,
                'threshold' => $this->alertThresholds['admin_actions_per_hour'],
                'details' => $details
            ]);
        }
    }

    /**
     * Monitor unusual login patterns
     */
    public function monitorUnusualLogin(string $userId, array $loginData): void
    {
        $user = \App\Models\User::find($userId);
        if (!$user) return;

        $unusualPatterns = [];

        // Check for new IP
        $recentLogins = DB::table('audit_logs')
            ->where('user_id', $userId)
            ->where('action', 'login_success')
            ->where('created_at', '>', Carbon::now()->subDays(30))
            ->get();

        $knownIps = $recentLogins->pluck('new_data->ip_address')->unique()->filter();
        if (!empty($loginData['ip_address']) && !$knownIps->contains($loginData['ip_address'])) {
            $unusualPatterns[] = 'new_ip_address';
        }

        // Check for unusual time
        $hour = Carbon::now()->hour;
        if ($hour < 6 || $hour > 22) {
            $unusualPatterns[] = 'unusual_time';
        }

        // Check for new location (if available)
        if (!empty($loginData['country']) && !empty($loginData['city'])) {
            $knownLocations = $recentLogins->pluck('new_data->country')->unique()->filter();
            if (!$knownLocations->contains($loginData['country'])) {
                $unusualPatterns[] = 'new_location';
            }
        }

        if (!empty($unusualPatterns)) {
            $this->triggerAlert('unusual_login_pattern', [
                'user_id' => $userId,
                'user_email' => $user->email,
                'patterns' => $unusualPatterns,
                'login_data' => $loginData
            ]);
        }
    }

    /**
     * Generate security report
     */
    public function generateSecurityReport(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        $report = [
            'period' => [
                'start' => $startDate,
                'end' => Carbon::now(),
                'days' => $days
            ],
            'summary' => [],
            'alerts' => [],
            'recommendations' => []
        ];

        // Failed logins
        $failedLogins = DB::table('audit_logs')
            ->where('action', 'login_failed')
            ->where('created_at', '>=', $startDate)
            ->count();

        $report['summary']['failed_logins'] = $failedLogins;

        // Suspicious requests
        $suspiciousRequests = DB::table('audit_logs')
            ->where('action', 'suspicious_request')
            ->where('created_at', '>=', $startDate)
            ->count();

        $report['summary']['suspicious_requests'] = $suspiciousRequests;

        // File uploads
        $fileUploads = DB::table('audit_logs')
            ->whereIn('action', ['file_upload_success', 'file_upload_failed'])
            ->where('created_at', '>=', $startDate)
            ->count();

        $report['summary']['file_upload_attempts'] = $fileUploads;

        // Admin actions
        $adminActions = DB::table('audit_logs')
            ->where('action', 'like', 'admin_%')
            ->where('created_at', '>=', $startDate)
            ->count();

        $report['summary']['admin_actions'] = $adminActions;

        // Generate recommendations
        $report['recommendations'] = $this->generateRecommendations($report['summary']);

        return $report;
    }

    /**
     * Trigger security alert
     */
    private function triggerAlert(string $alertType, array $data): void
    {
        $alert = [
            'type' => $alertType,
            'severity' => $this->getAlertSeverity($alertType),
            'timestamp' => Carbon::now(),
            'data' => $data
        ];

        // Log alert
        Log::critical('Security Alert Triggered', $alert);

        // Store in cache for dashboard
        $alerts = Cache::get('security_alerts', []);
        $alerts[] = $alert;
        
        // Keep only last 100 alerts
        $alerts = array_slice($alerts, -100);
        Cache::put('security_alerts', $alerts, 86400); // 24 hours

        // Send notification (implement based on your notification system)
        $this->sendAlertNotification($alert);
    }

    /**
     * Get alert severity
     */
    private function getAlertSeverity(string $alertType): string
    {
        $severityMap = [
            'failed_logins_threshold' => 'medium',
            'suspicious_requests_threshold' => 'high',
            'file_uploads_threshold' => 'low',
            'admin_actions_threshold' => 'medium',
            'unusual_login_pattern' => 'high'
        ];

        return $severityMap[$alertType] ?? 'low';
    }

    /**
     * Send alert notification
     */
    private function sendAlertNotification(array $alert): void
    {
        // Implement notification logic (email, Slack, etc.)
        // This is a placeholder
        Log::info('Alert notification sent', $alert);
    }

    /**
     * Generate security recommendations
     */
    private function generateRecommendations(array $summary): array
    {
        $recommendations = [];

        if ($summary['failed_logins'] > 50) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'high',
                'message' => 'High number of failed login attempts detected. Consider implementing additional security measures.'
            ];
        }

        if ($summary['suspicious_requests'] > 20) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'high',
                'message' => 'Multiple suspicious requests detected. Review and strengthen input validation.'
            ];
        }

        if ($summary['file_upload_attempts'] > 100) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => 'High file upload activity. Monitor storage usage and consider implementing quotas.'
            ];
        }

        return $recommendations;
    }
}
