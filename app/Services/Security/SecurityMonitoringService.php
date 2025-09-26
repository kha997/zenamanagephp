<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SecurityMonitoringService
{
    /**
     * Monitor security events.
     */
    public function monitorSecurityEvents(): array
    {
        $monitoringResults = [
            'timestamp' => now()->toISOString(),
            'events' => [],
            'alerts' => []
        ];

        // Monitor failed login attempts
        $monitoringResults['events']['failed_logins'] = $this->monitorFailedLogins();
        
        // Monitor suspicious activities
        $monitoringResults['events']['suspicious_activities'] = $this->monitorSuspiciousActivities();
        
        // Monitor privilege escalations
        $monitoringResults['events']['privilege_escalations'] = $this->monitorPrivilegeEscalations();
        
        // Monitor data access patterns
        $monitoringResults['events']['data_access'] = $this->monitorDataAccess();
        
        // Generate alerts
        $monitoringResults['alerts'] = $this->generateSecurityAlerts($monitoringResults['events']);

        Log::channel('security')->info('Security monitoring completed', $monitoringResults);

        return $monitoringResults;
    }

    /**
     * Monitor failed login attempts.
     */
    protected function monitorFailedLogins(): array
    {
        $events = [];
        $alertThreshold = 5; // Failed attempts per hour

        try {
            // Get failed login attempts from logs
            $logFiles = glob(storage_path('logs/laravel-*.log'));
            $failedAttempts = [];

            foreach ($logFiles as $logFile) {
                $content = file_get_contents($logFile);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line) {
                    if (strpos($line, 'Failed login attempt') !== false) {
                        preg_match('/\[(.*?)\].*Failed login attempt for (.*?) from (.*?)/', $line, $matches);
                        if (count($matches) >= 4) {
                            $timestamp = $matches[1];
                            $email = $matches[2];
                            $ip = $matches[3];
                            
                            $failedAttempts[] = [
                                'timestamp' => $timestamp,
                                'email' => $email,
                                'ip' => $ip
                            ];
                        }
                    }
                }
            }

            // Group by IP and email
            $ipAttempts = [];
            $emailAttempts = [];
            
            foreach ($failedAttempts as $attempt) {
                $ip = $attempt['ip'];
                $email = $attempt['email'];
                
                $ipAttempts[$ip] = ($ipAttempts[$ip] ?? 0) + 1;
                $emailAttempts[$email] = ($emailAttempts[$email] ?? 0) + 1;
            }

            // Check for suspicious patterns
            foreach ($ipAttempts as $ip => $count) {
                if ($count >= $alertThreshold) {
                    $events[] = [
                        'type' => 'failed_login_ip',
                        'severity' => 'high',
                        'ip' => $ip,
                        'count' => $count,
                        'message' => "Multiple failed login attempts from IP: {$ip}"
                    ];
                }
            }

            foreach ($emailAttempts as $email => $count) {
                if ($count >= $alertThreshold) {
                    $events[] = [
                        'type' => 'failed_login_email',
                        'severity' => 'high',
                        'email' => $email,
                        'count' => $count,
                        'message' => "Multiple failed login attempts for email: {$email}"
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Error monitoring failed logins', ['error' => $e->getMessage()]);
        }

        return [
            'events' => $events,
            'count' => count($events),
            'threshold' => $alertThreshold
        ];
    }

    /**
     * Monitor suspicious activities.
     */
    protected function monitorSuspiciousActivities(): array
    {
        $events = [];

        try {
            // Monitor unusual access patterns
            $unusualAccess = $this->detectUnusualAccessPatterns();
            $events = array_merge($events, $unusualAccess);

            // Monitor privilege changes
            $privilegeChanges = $this->detectPrivilegeChanges();
            $events = array_merge($events, $privilegeChanges);

            // Monitor data exports
            $dataExports = $this->detectDataExports();
            $events = array_merge($events, $dataExports);

        } catch (\Exception $e) {
            Log::error('Error monitoring suspicious activities', ['error' => $e->getMessage()]);
        }

        return [
            'events' => $events,
            'count' => count($events)
        ];
    }

    /**
     * Monitor privilege escalations.
     */
    protected function monitorPrivilegeEscalations(): array
    {
        $events = [];

        try {
            // Check for role changes
            $roleChanges = DB::table('user_roles')
                ->where('created_at', '>=', now()->subHours(24))
                ->get();

            foreach ($roleChanges as $change) {
                $user = DB::table('users')->where('id', $change->user_id)->first();
                $role = DB::table('roles')->where('id', $change->role_id)->first();
                
                if ($role && in_array($role->name, ['admin', 'super_admin'])) {
                    $events[] = [
                        'type' => 'privilege_escalation',
                        'severity' => 'high',
                        'user_id' => $change->user_id,
                        'user_email' => $user->email ?? 'Unknown',
                        'role' => $role->name,
                        'message' => "User {$user->email} assigned admin role"
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Error monitoring privilege escalations', ['error' => $e->getMessage()]);
        }

        return [
            'events' => $events,
            'count' => count($events)
        ];
    }

    /**
     * Monitor data access patterns.
     */
    protected function monitorDataAccess(): array
    {
        $events = [];

        try {
            // Monitor bulk data access
            $bulkAccess = $this->detectBulkDataAccess();
            $events = array_merge($events, $bulkAccess);

            // Monitor cross-tenant access
            $crossTenantAccess = $this->detectCrossTenantAccess();
            $events = array_merge($events, $crossTenantAccess);

        } catch (\Exception $e) {
            Log::error('Error monitoring data access', ['error' => $e->getMessage()]);
        }

        return [
            'events' => $events,
            'count' => count($events)
        ];
    }

    /**
     * Detect unusual access patterns.
     */
    protected function detectUnusualAccessPatterns(): array
    {
        $events = [];

        try {
            // Check for access from unusual locations
            $recentLogins = DB::table('users')
                ->where('last_login_at', '>=', now()->subHours(24))
                ->get();

            foreach ($recentLogins as $user) {
                // Check for access from different countries (simplified)
                $ip = $user->last_login_ip ?? '127.0.0.1';
                if ($ip !== '127.0.0.1' && $ip !== '::1') {
                    $events[] = [
                        'type' => 'unusual_location',
                        'severity' => 'medium',
                        'user_id' => $user->id,
                        'ip' => $ip,
                        'message' => "User {$user->email} logged in from unusual location"
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Error detecting unusual access patterns', ['error' => $e->getMessage()]);
        }

        return $events;
    }

    /**
     * Detect privilege changes.
     */
    protected function detectPrivilegeChanges(): array
    {
        $events = [];

        try {
            // Check for role assignments
            $roleAssignments = DB::table('user_roles')
                ->where('created_at', '>=', now()->subHours(24))
                ->get();

            foreach ($roleAssignments as $assignment) {
                $user = DB::table('users')->where('id', $assignment->user_id)->first();
                $role = DB::table('roles')->where('id', $assignment->role_id)->first();
                
                $events[] = [
                    'type' => 'role_assignment',
                    'severity' => 'medium',
                    'user_id' => $assignment->user_id,
                    'user_email' => $user->email ?? 'Unknown',
                    'role' => $role->name ?? 'Unknown',
                    'message' => "Role {$role->name} assigned to user {$user->email}"
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error detecting privilege changes', ['error' => $e->getMessage()]);
        }

        return $events;
    }

    /**
     * Detect data exports.
     */
    protected function detectDataExports(): array
    {
        $events = [];

        try {
            // Check for bulk data operations
            $bulkOperations = DB::table('bulk_operations')
                ->where('created_at', '>=', now()->subHours(24))
                ->get();

            foreach ($bulkOperations as $operation) {
                if ($operation->operation_type === 'export' && $operation->record_count > 1000) {
                    $events[] = [
                        'type' => 'bulk_export',
                        'severity' => 'medium',
                        'user_id' => $operation->user_id,
                        'record_count' => $operation->record_count,
                        'message' => "Large data export: {$operation->record_count} records"
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Error detecting data exports', ['error' => $e->getMessage()]);
        }

        return $events;
    }

    /**
     * Detect bulk data access.
     */
    protected function detectBulkDataAccess(): array
    {
        $events = [];

        try {
            // Check for large queries
            $largeQueries = DB::table('query_logs')
                ->where('created_at', '>=', now()->subHours(24))
                ->where('record_count', '>', 1000)
                ->get();

            foreach ($largeQueries as $query) {
                $events[] = [
                    'type' => 'bulk_data_access',
                    'severity' => 'medium',
                    'user_id' => $query->user_id,
                    'record_count' => $query->record_count,
                    'message' => "Large data access: {$query->record_count} records"
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error detecting bulk data access', ['error' => $e->getMessage()]);
        }

        return $events;
    }

    /**
     * Detect cross-tenant access.
     */
    protected function detectCrossTenantAccess(): array
    {
        $events = [];

        try {
            // Check for cross-tenant data access
            $crossTenantAccess = DB::table('access_logs')
                ->where('created_at', '>=', now()->subHours(24))
                ->where('cross_tenant', true)
                ->get();

            foreach ($crossTenantAccess as $access) {
                $events[] = [
                    'type' => 'cross_tenant_access',
                    'severity' => 'high',
                    'user_id' => $access->user_id,
                    'resource' => $access->resource,
                    'message' => "Cross-tenant access detected: {$access->resource}"
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error detecting cross-tenant access', ['error' => $e->getMessage()]);
        }

        return $events;
    }

    /**
     * Generate security alerts.
     */
    protected function generateSecurityAlerts(array $events): array
    {
        $alerts = [];

        foreach ($events as $eventType => $eventData) {
            if (isset($eventData['events'])) {
                foreach ($eventData['events'] as $event) {
                    if ($event['severity'] === 'high') {
                        $alerts[] = [
                            'type' => $event['type'],
                            'severity' => $event['severity'],
                            'message' => $event['message'],
                            'timestamp' => now()->toISOString(),
                            'action_required' => true
                        ];
                    }
                }
            }
        }

        return $alerts;
    }

    /**
     * Generate security monitoring report.
     */
    public function generateSecurityMonitoringReport(): array
    {
        $monitoringResults = $this->monitorSecurityEvents();
        
        $report = [
            'title' => 'Security Monitoring Report',
            'generated_at' => now()->toISOString(),
            'total_events' => array_sum(array_column($monitoringResults['events'], 'count')),
            'high_severity_events' => $this->countHighSeverityEvents($monitoringResults['events']),
            'medium_severity_events' => $this->countMediumSeverityEvents($monitoringResults['events']),
            'low_severity_events' => $this->countLowSeverityEvents($monitoringResults['events']),
            'alerts' => $monitoringResults['alerts'],
            'recommendations' => $this->generateMonitoringRecommendations($monitoringResults),
            'details' => $monitoringResults
        ];

        return $report;
    }

    /**
     * Count high severity events.
     */
    protected function countHighSeverityEvents(array $events): int
    {
        $count = 0;
        foreach ($events as $eventData) {
            if (isset($eventData['events'])) {
                foreach ($eventData['events'] as $event) {
                    if ($event['severity'] === 'high') {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Count medium severity events.
     */
    protected function countMediumSeverityEvents(array $events): int
    {
        $count = 0;
        foreach ($events as $eventData) {
            if (isset($eventData['events'])) {
                foreach ($eventData['events'] as $event) {
                    if ($event['severity'] === 'medium') {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Count low severity events.
     */
    protected function countLowSeverityEvents(array $events): int
    {
        $count = 0;
        foreach ($events as $eventData) {
            if (isset($eventData['events'])) {
                foreach ($eventData['events'] as $event) {
                    if ($event['severity'] === 'low') {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Generate monitoring recommendations.
     */
    protected function generateMonitoringRecommendations(array $monitoringResults): array
    {
        $recommendations = [];

        // Check for high severity events
        $highSeverityCount = $this->countHighSeverityEvents($monitoringResults['events']);
        if ($highSeverityCount > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'recommendation' => 'Immediate action required: ' . $highSeverityCount . ' high severity security events detected'
            ];
        }

        // Check for failed login attempts
        if (isset($monitoringResults['events']['failed_logins']['count']) && 
            $monitoringResults['events']['failed_logins']['count'] > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'recommendation' => 'Review failed login attempts and consider implementing additional security measures'
            ];
        }

        // Check for privilege escalations
        if (isset($monitoringResults['events']['privilege_escalations']['count']) && 
            $monitoringResults['events']['privilege_escalations']['count'] > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'recommendation' => 'Review privilege escalations and ensure proper authorization'
            ];
        }

        return $recommendations;
    }

    /**
     * Set up security monitoring alerts.
     */
    public function setupSecurityAlerts(): array
    {
        $alerts = [
            'timestamp' => now()->toISOString(),
            'alerts_configured' => []
        ];

        try {
            // Configure failed login alerts
            $alerts['alerts_configured'][] = [
                'type' => 'failed_login',
                'threshold' => 5,
                'timeframe' => '1 hour',
                'action' => 'email_notification'
            ];

            // Configure privilege escalation alerts
            $alerts['alerts_configured'][] = [
                'type' => 'privilege_escalation',
                'threshold' => 1,
                'timeframe' => '24 hours',
                'action' => 'immediate_notification'
            ];

            // Configure cross-tenant access alerts
            $alerts['alerts_configured'][] = [
                'type' => 'cross_tenant_access',
                'threshold' => 1,
                'timeframe' => '1 hour',
                'action' => 'immediate_notification'
            ];

        } catch (\Exception $e) {
            Log::error('Error setting up security alerts', ['error' => $e->getMessage()]);
        }

        return $alerts;
    }
}
