<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SecurityAuditService
 * 
 * Comprehensive security audit logging and monitoring system
 * for tracking permission checks, security events, and access patterns.
 * 
 * Features:
 * - Permission check logging
 * - Security event tracking
 * - Access pattern analysis
 * - Audit log retrieval
 * - Security metrics collection
 */
class SecurityAuditService
{
    private const AUDIT_LOG_TTL = 2592000; // 30 days
    private const SECURITY_METRICS_TTL = 86400; // 24 hours
    private const MAX_LOG_ENTRIES = 10000;
    
    /**
     * Log permission check with detailed context
     */
    public function logPermissionCheck(string $permission, int $userId, int $tenantId, bool $result): void
    {
        try {
            $auditEntry = [
                'timestamp' => now()->toISOString(),
                'event_type' => 'permission_check',
                'permission' => $permission,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'result' => $result,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->header('X-Request-Id'),
                'session_id' => session()->getId(),
            ];
            
            $this->storeAuditLog($auditEntry);
            $this->updateSecurityMetrics('permission_checks', $result);
            
            Log::info('Permission check logged', [
                'permission' => $permission,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Security audit logging error', [
                'permission' => $permission,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Log general security event
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        try {
            $auditEntry = [
                'timestamp' => now()->toISOString(),
                'event_type' => 'security_event',
                'event' => $event,
                'context' => $context,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->header('X-Request-Id'),
                'session_id' => session()->getId(),
            ];
            
            $this->storeAuditLog($auditEntry);
            $this->updateSecurityMetrics('security_events', true);
            
            Log::info('Security event logged', [
                'event' => $event,
                'context' => $context
            ]);
            
        } catch (\Exception $e) {
            Log::error('Security event logging error', [
                'event' => $event,
                'context' => $context,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Log authentication event
     */
    public function logAuthenticationEvent(string $event, int $userId, bool $success, array $context = []): void
    {
        try {
            $auditEntry = [
                'timestamp' => now()->toISOString(),
                'event_type' => 'authentication',
                'event' => $event,
                'user_id' => $userId,
                'success' => $success,
                'context' => $context,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->header('X-Request-Id'),
                'session_id' => session()->getId(),
            ];
            
            $this->storeAuditLog($auditEntry);
            $this->updateSecurityMetrics('auth_events', $success);
            
            Log::info('Authentication event logged', [
                'event' => $event,
                'user_id' => $userId,
                'success' => $success
            ]);
            
        } catch (\Exception $e) {
            Log::error('Authentication event logging error', [
                'event' => $event,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Log data access event
     */
    public function logDataAccessEvent(string $resource, string $action, int $userId, int $tenantId, bool $success): void
    {
        try {
            $auditEntry = [
                'timestamp' => now()->toISOString(),
                'event_type' => 'data_access',
                'resource' => $resource,
                'action' => $action,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'success' => $success,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->header('X-Request-Id'),
                'session_id' => session()->getId(),
            ];
            
            $this->storeAuditLog($auditEntry);
            $this->updateSecurityMetrics('data_access', $success);
            
            Log::info('Data access event logged', [
                'resource' => $resource,
                'action' => $action,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'success' => $success
            ]);
            
        } catch (\Exception $e) {
            Log::error('Data access event logging error', [
                'resource' => $resource,
                'action' => $action,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get security audit log with filtering
     */
    public function getSecurityAuditLog(int $userId = null, int $tenantId = null, int $limit = 100): array
    {
        try {
            $cacheKey = "security_audit_log:{$userId}:{$tenantId}:{$limit}";
            $cachedLog = Cache::get($cacheKey);
            
            if ($cachedLog !== null) {
                return $cachedLog;
            }
            
            $logs = [];
            $logEntries = Cache::get('security_audit_logs', []);
            
            // Filter logs based on criteria
            foreach ($logEntries as $entry) {
                if ($userId && $entry['user_id'] !== $userId) {
                    continue;
                }
                
                if ($tenantId && $entry['tenant_id'] !== $tenantId) {
                    continue;
                }
                
                $logs[] = $entry;
                
                if (count($logs) >= $limit) {
                    break;
                }
            }
            
            // Sort by timestamp (newest first)
            usort($logs, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            // Cache the result
            Cache::put($cacheKey, $logs, 300); // 5 minutes
            
            return $logs;
            
        } catch (\Exception $e) {
            Log::error('Security audit log retrieval error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get security metrics
     */
    public function getSecurityMetrics(): array
    {
        try {
            $metrics = Cache::get('security_metrics', [
                'permission_checks' => ['total' => 0, 'success' => 0, 'failed' => 0],
                'auth_events' => ['total' => 0, 'success' => 0, 'failed' => 0],
                'data_access' => ['total' => 0, 'success' => 0, 'failed' => 0],
                'security_events' => ['total' => 0],
                'last_updated' => now()->toISOString()
            ]);
            
            // Calculate success rates
            foreach ($metrics as $key => $metric) {
                if (is_array($metric) && isset($metric['total']) && $metric['total'] > 0) {
                    $metrics[$key]['success_rate'] = round(
                        ($metric['success'] / $metric['total']) * 100, 2
                    );
                }
            }
            
            return $metrics;
            
        } catch (\Exception $e) {
            Log::error('Security metrics retrieval error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get security alerts
     */
    public function getSecurityAlerts(): array
    {
        try {
            $alerts = [];
            $metrics = $this->getSecurityMetrics();
            
            // Check for suspicious patterns
            if (isset($metrics['auth_events']['failed']) && $metrics['auth_events']['failed'] > 10) {
                $alerts[] = [
                    'type' => 'high_failed_auth',
                    'message' => 'High number of failed authentication attempts',
                    'count' => $metrics['auth_events']['failed'],
                    'severity' => 'high'
                ];
            }
            
            if (isset($metrics['permission_checks']['failed']) && $metrics['permission_checks']['failed'] > 50) {
                $alerts[] = [
                    'type' => 'high_permission_denials',
                    'message' => 'High number of permission denials',
                    'count' => $metrics['permission_checks']['failed'],
                    'severity' => 'medium'
                ];
            }
            
            if (isset($metrics['data_access']['failed']) && $metrics['data_access']['failed'] > 20) {
                $alerts[] = [
                    'type' => 'high_data_access_denials',
                    'message' => 'High number of data access denials',
                    'count' => $metrics['data_access']['failed'],
                    'severity' => 'high'
                ];
            }
            
            return $alerts;
            
        } catch (\Exception $e) {
            Log::error('Security alerts retrieval error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Clear old audit logs
     */
    public function clearOldAuditLogs(int $daysToKeep = 30): void
    {
        try {
            $cutoffDate = now()->subDays($daysToKeep);
            $logEntries = Cache::get('security_audit_logs', []);
            
            $filteredLogs = array_filter($logEntries, function($entry) use ($cutoffDate) {
                return strtotime($entry['timestamp']) > $cutoffDate->timestamp;
            });
            
            Cache::put('security_audit_logs', array_values($filteredLogs), self::AUDIT_LOG_TTL);
            
            Log::info('Old audit logs cleared', [
                'days_kept' => $daysToKeep,
                'remaining_entries' => count($filteredLogs)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Clear old audit logs error', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Store audit log entry
     */
    private function storeAuditLog(array $entry): void
    {
        try {
            $logEntries = Cache::get('security_audit_logs', []);
            
            // Add new entry
            $logEntries[] = $entry;
            
            // Limit the number of entries
            if (count($logEntries) > self::MAX_LOG_ENTRIES) {
                $logEntries = array_slice($logEntries, -self::MAX_LOG_ENTRIES);
            }
            
            Cache::put('security_audit_logs', $logEntries, self::AUDIT_LOG_TTL);
            
        } catch (\Exception $e) {
            Log::error('Store audit log error', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update security metrics
     */
    private function updateSecurityMetrics(string $type, bool $success): void
    {
        try {
            $metrics = Cache::get('security_metrics', [
                'permission_checks' => ['total' => 0, 'success' => 0, 'failed' => 0],
                'auth_events' => ['total' => 0, 'success' => 0, 'failed' => 0],
                'data_access' => ['total' => 0, 'success' => 0, 'failed' => 0],
                'security_events' => ['total' => 0],
                'last_updated' => now()->toISOString()
            ]);
            
            if (!isset($metrics[$type])) {
                $metrics[$type] = ['total' => 0, 'success' => 0, 'failed' => 0];
            }
            
            $metrics[$type]['total']++;
            
            if ($success) {
                $metrics[$type]['success']++;
            } else {
                $metrics[$type]['failed']++;
            }
            
            $metrics['last_updated'] = now()->toISOString();
            
            Cache::put('security_metrics', $metrics, self::SECURITY_METRICS_TTL);
            
        } catch (\Exception $e) {
            Log::error('Update security metrics error', [
                'type' => $type,
                'success' => $success,
                'error' => $e->getMessage()
            ]);
        }
    }
}
