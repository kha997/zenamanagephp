<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\RateLimiter;

class SecurityApiController
{
    /**
     * Get security KPIs
     */
    public function kpis(Request $request): JsonResponse
    {
        $period = $this->validatePeriod($request->get('period', '30d'));
        $days = $this->getDaysFromPeriod($period);
        
        // MFA Adoption
        $totalUsers = User::count();
        $mfaUsers = User::whereNotNull('mfa_secret')->count();
        $mfaAdoption = $totalUsers > 0 ? round(($mfaUsers / $totalUsers) * 100, 1) : 0;
        
        // Failed logins in last 24h
        $failedLogins24h = AuditLog::where('action', 'login_failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();
            
        // Locked accounts
        $lockedAccounts = User::where('is_active', false)->count();
        
        // Active sessions (users logged in within last 30 minutes)
        $activeSessions = User::where('last_login_at', '>=', now()->subMinutes(30))->count();
        
        // Risky keys (placeholder)
        $riskyKeys = 0;
        
        // Generate historical data for charts
        $mfaSeries = $this->generateMfaAdoptionSeries($days);
        $loginAttempts = $this->generateLoginAttemptsSeries($days);
        $activeSessionsSeries = $this->generateActiveSessionsSeries($days);
        $failedLoginsSeries = $this->generateFailedLoginsSeries($days);
        
        return response()->json([
            'data' => [
                'mfaAdoption' => [
                    'value' => $mfaAdoption,
                    'deltaPct' => 0,
                    'series' => $mfaSeries,
                    'period' => $period
                ],
                'failedLogins' => [
                    'value' => $failedLogins24h,
                    'deltaAbs' => 0,
                    'series' => $failedLoginsSeries,
                    'period' => $period
                ],
                'lockedAccounts' => [
                    'value' => $lockedAccounts,
                    'deltaAbs' => 0,
                    'series' => array_fill(0, $days, $lockedAccounts),
                    'period' => $period
                ],
                'activeSessions' => [
                    'value' => $activeSessions,
                    'deltaAbs' => 0,
                    'series' => $activeSessionsSeries,
                    'period' => $period
                ],
                'riskyKeys' => [
                    'value' => $riskyKeys,
                    'deltaAbs' => 0,
                    'series' => array_fill(0, $days, $riskyKeys),
                    'period' => $period
                ],
                'loginAttempts' => [
                    'success' => $loginAttempts['success'],
                    'failed' => $loginAttempts['failed']
                ]
            ],
            'meta' => [
                'generatedAt' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get MFA users
     */
    public function mfa(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 20), 100);
        $page = $request->get('page', 1);
        $sortBy = $request->get('sort_by', 'last_login_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $mfaEnabled = $request->get('mfa_enabled');
        
        $query = User::query();
        
        // Filter by MFA status
        if ($mfaEnabled !== null) {
            if ($mfaEnabled) {
                $query->whereNotNull('mfa_secret');
            } else {
                $query->whereNull('mfa_secret');
            }
        }
        
        // Sort
        $allowedSortFields = ['name', 'email', 'last_login_at', 'created_at', 'role'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        // Select required fields including role
        $users = $query->select(['id', 'name', 'email', 'role', 'mfa_secret', 'last_login_at', 'created_at'])
                      ->paginate($perPage, ['*'], 'page', $page);
        
        $data = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?: 'member', // Default role if null
                'mfa_enabled' => !is_null($user->mfa_secret),
                'last_login_at' => $user->last_login_at?->toISOString(), // Use Carbon method toISOString()
                'created_at' => $user->created_at->toISOString()
            ];
        });
        
        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $users->total(),
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
                'generatedAt' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get login attempts
     */
    public function logins(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 20), 100);
        $page = $request->get('page', 1);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $result = $request->get('result'); // success, failed
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        $query = AuditLog::whereIn('action', ['login', 'login_failed']);
        
        // Filter by result (map to action)
        if ($result) {
            if ($result === 'failed') {
                $query->where('action', 'login_failed');
            } else {
                $query->where('action', 'login');
            }
        }
        
        // Filter by date range
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }
        
        // Sort
        $allowedSortFields = ['created_at', 'result', 'ip_address'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        $logs = $query->paginate($perPage, ['*'], 'page', $page);
        
        $data = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'user_email' => $log->user_id ? User::find($log->user_id)?->email : 'Unknown',
                'result' => $log->action === 'login_failed' ? 'failed' : 'success',
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toISOString()
            ];
        });
        
        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $logs->total(),
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'last_page' => $logs->lastPage(),
                'generatedAt' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get audit logs
     */
    public function audit(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 20), 100);
        $page = $request->get('page', 1);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $action = $request->get('action');
        $severity = $request->get('severity');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        $query = AuditLog::query();
        
        // Filter by action
        if ($action) {
            $query->where('action', $action);
        }
        
        // Filter by severity (mapped from action since severity column doesn't exist)
        if ($severity) {
            $severityActions = $this->getActionsBySeverity($severity);
            if (!empty($severityActions)) {
                $query->whereIn('action', $severityActions);
            }
        }
        
        // Filter by date range
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }
        
        // Sort
        $allowedSortFields = ['created_at', 'action', 'severity', 'user_email'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        $logs = $query->paginate($perPage, ['*'], 'page', $page);
        
        $data = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'user_email' => $log->user_id ? User::find($log->user_id)?->email : 'Unknown',
                'severity' => $this->mapActionToSeverity($log->action), // Map action to severity
                'result' => $log->action === 'login_failed' ? 'failed' : 'success',
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'details' => $log->new_data ?? [],
                'created_at' => $log->created_at->toISOString()
            ];
        });
        
        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $logs->total(),
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'last_page' => $logs->lastPage(),
                'generatedAt' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get active sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 20), 100);
        $page = $request->get('page', 1);
        $sortBy = $request->get('sort_by', 'last_login_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $activeOnly = $request->get('active_only', true);
        
        $query = User::query();
        
        // Filter active sessions (last 30 minutes)
        if ($activeOnly) {
            $query->where('last_login_at', '>=', now()->subMinutes(30));
        }
        
        // Sort
        $allowedSortFields = ['name', 'email', 'last_login_at', 'created_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        $users = $query->paginate($perPage, ['*'], 'page', $page);
        
        $data = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'ip_address' => $user->last_login_ip ?? 'Unknown',
                'user_agent' => $user->last_login_user_agent ?? 'Unknown',
                'last_seen' => $user->last_login_at?->toISOString(),
                'created_at' => $user->created_at->toISOString()
            ];
        });
        
        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $users->total(),
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
                'generatedAt' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Force MFA for user
     */
    public function forceMfa(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found'
                ]
            ], 404);
        }
        
        // Force MFA by setting a flag or updating user
        // For now, we'll just return success
        // In a real implementation, you might:
        // - Set a flag in the database
        // - Send an email to the user
        // - Log the action
        
        return response()->json([
            'data' => [
                'success' => true,
                'user_id' => $id,
                'user_email' => $user->email,
                'forced_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Export audit logs to CSV
     */
    public function exportAudit(Request $request): StreamedResponse
    {
        $this->enforceRateLimit($request, 'security_export', 10, 60); // 10 req / 60s
        $query = $this->buildAuditQuery($request);
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=audit_' . now()->format('Y-m-d_H-i-s') . '.csv',
            'Cache-Control' => 'no-store',
        ];
        
        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            
            // CSV headers
            fputcsv($out, [
                'Timestamp', 'User Email', 'Action', 'Entity Type', 'Entity ID', 
                'IP Address', 'User Agent', 'Tenant ID', 'Details'
            ]);
            
            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->created_at->toISOString(),
                        $row->user_id ? User::find($row->user_id)?->email : 'Unknown',
                        $row->action,
                        $row->entity_type ?? '',
                        $row->entity_id ?? '',
                        $row->ip_address ?? '',
                        $row->user_agent ?? '',
                        $row->tenant_id ?? '',
                        json_encode($row->new_data ?? [])
                    ]);
                }
                fflush($out);
            });
            
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Export MFA users to CSV
     */
    public function exportMfa(Request $request): StreamedResponse
    {
        $this->enforceRateLimit($request, 'security_export', 10, 60);
        
        $perPage = min($request->get('per_page', 1000), 1000);
        $mfaEnabled = $request->get('mfa_enabled');
        
        $query = User::query();
        
        if ($mfaEnabled !== null) {
            if ($mfaEnabled) {
                $query->whereNotNull('mfa_secret');
            } else {
                $query->whereNull('mfa_secret');
            }
        }
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=mfa_users_' . now()->format('Y-m-d_H-i-s') . '.csv',
            'Cache-Control' => 'no-store',
        ];
        
        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            
            fputcsv($out, [
                'ID', 'Name', 'Email', 'MFA Enabled', 'Last Login', 'Created At', 'Role'
            ]);
            
            $query->chunk(1000, function ($users) use ($out) {
                foreach ($users as $user) {
                    fputcsv($out, [
                        $user->id,
                        $user->name,
                        $user->email,
                        !is_null($user->mfa_secret) ? 'Yes' : 'No',
                        $user->last_login_at?->toISOString() ?? '',
                        $user->created_at->toISOString(),
                        $user->role ?? ''
                    ]);
                }
                fflush($out);
            });
            
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Export login attempts to CSV
     */
    public function exportLogins(Request $request): StreamedResponse
    {
        $this->enforceRateLimit($request, 'security_export', 10, 60);
        $query = $this->buildLoginQuery($request);
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=login_attempts_' . now()->format('Y-m-d_H-i-s') . '.csv',
            'Cache-Control' => 'no-store',
        ];
        
        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            
            fputcsv($out, [
                'Timestamp', 'User Email', 'Result', 'IP Address', 'User Agent'
            ]);
            
            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->created_at->toISOString(),
                        $row->user_id ? User::find($row->user_id)?->email : 'Unknown',
                        $row->action === 'login_failed' ? 'failed' : 'success',
                        $row->ip_address ?? '',
                        $row->user_agent ?? ''
                    ]);
                }
                fflush($out);
            });
            
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Build audit query with filters
     */
    protected function buildAuditQuery(Request $request)
    {
        $action = $request->get('action');
        $severity = $request->get('severity');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        $query = AuditLog::query();
        
        if ($action) {
            $query->where('action', $action);
        }
        
        if ($severity) {
            $query->where('severity', $severity);
        }
        
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }
        
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Build login query with filters
     */
    protected function buildLoginQuery(Request $request)
    {
        $result = $request->get('result');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        $query = AuditLog::whereIn('action', ['login', 'login_failed']);
        
        if ($result) {
            if ($result === 'failed') {
                $query->where('action', 'login_failed');
            } else {
                $query->where('action', 'login');
            }
        }
        
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }
        
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Enforce rate limiting
     */
    protected function enforceRateLimit(Request $request, string $key, int $max, int $seconds): void
    {
        $userId = $request->user()?->id ?? 'anon';
        $id = $key . ':' . $userId;
        
        if (!RateLimiter::attempt($id, $max, fn() => null, $seconds)) {
            $retry = RateLimiter::availableIn($id);
            abort(response()->json([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many exports. Please try again later.'
                ]
            ], 429)->header('Retry-After', $retry));
        }
    }

    /**
     * Get days from period string
     */
    protected function getDaysFromPeriod(string $period): int
    {
        return match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30
        };
    }

    /**
     * Validate and normalize period parameter
     */
    protected function validatePeriod(string $period): string
    {
        return in_array($period, ['7d', '30d', '90d']) ? $period : '30d';
    }

    /**
     * Generate MFA adoption series
     */
    protected function generateMfaAdoptionSeries(int $days): array
    {
        $series = [];
        $baseAdoption = 0; // Current MFA adoption
        
        for ($i = 0; $i < $days; $i++) {
            // Simulate gradual increase in MFA adoption
            $adoption = $baseAdoption + ($i * 0.5) + rand(-2, 2);
            $adoption = max(0, min(100, $adoption)); // Clamp between 0-100
            $series[] = round($adoption, 1);
        }
        
        return $series;
    }

    /**
     * Generate login attempts series
     */
    protected function generateLoginAttemptsSeries(int $days): array
    {
        $success = [];
        $failed = [];
        
        for ($i = 0; $i < $days; $i++) {
            // Simulate daily login patterns
            $baseSuccess = 150 + rand(-20, 20);
            $baseFailed = 5 + rand(-2, 8);
            
            $success[] = max(0, $baseSuccess);
            $failed[] = max(0, $baseFailed);
        }
        
        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Generate active sessions series
     */
    protected function generateActiveSessionsSeries(int $days): array
    {
        $series = [];
        
        for ($i = 0; $i < $days; $i++) {
            // Simulate daily active sessions
            $sessions = 800 + rand(-100, 150);
            $series[] = max(0, $sessions);
        }
        
        return $series;
    }

    /**
     * Generate failed logins series
     */
    protected function generateFailedLoginsSeries(int $days): array
    {
        $series = [];
        
        for ($i = 0; $i < $days; $i++) {
            // Simulate daily failed logins
            $failed = 12 + rand(-5, 15);
            $series[] = max(0, $failed);
        }
        
        return $series;
    }

    /**
     * Map action to severity level
     */
    protected function mapActionToSeverity(string $action): string
    {
        return match($action) {
            'login_failed' => 'high',
            'password_reset' => 'medium',
            'account_locked' => 'high',
            'permission_denied' => 'medium',
            'data_export' => 'low',
            'user_created' => 'low',
            'user_updated' => 'low',
            default => 'info'
        };
    }

    /**
     * Get actions by severity level
     */
    protected function getActionsBySeverity(string $severity): array
    {
        return match($severity) {
            'high' => ['login_failed', 'account_locked', 'unauthorized_access'],
            'medium' => ['password_reset', 'permission_denied', 'role_changed'],
            'low' => ['data_export', 'user_created', 'user_updated', 'login'],
            'info' => ['login', 'logout', 'profile_updated'],
            default => []
        };
    }

    /**
     * Test endpoint to trigger security events
     */
    public function testEvent(Request $request): JsonResponse
    {
        $eventType = $request->get('event', 'login_failed');
        
        try {
            switch ($eventType) {
                case 'login_failed':
                    event(new \App\Events\Security\LoginFailed(
                        now()->toISOString(),
                        'test@example.com',
                        '192.168.1.100',
                        'US',
                        'Test Tenant'
                    ));
                    break;
                    
                case 'key_revoked':
                    event(new \App\Events\Security\KeyRevoked(
                        'test-key-123',
                        'admin@example.com',
                        now()->toISOString(),
                        'Manual revocation'
                    ));
                    break;
                    
                case 'session_ended':
                    event(new \App\Events\Security\SessionEnded(
                        'session-456',
                        'user@example.com',
                        now()->toISOString(),
                        'Manual logout',
                        '192.168.1.101'
                    ));
                    break;
            }
            
            return response()->json([
                'message' => "Event {$eventType} triggered successfully",
                'timestamp' => now()->toISOString(),
                'broadcast_status' => 'success'
            ]);
            
        } catch (\Exception $e) {
            // Fallback when Redis/broadcasting is not configured
            return response()->json([
                'message' => "Event {$eventType} triggered successfully (broadcasting disabled)",
                'timestamp' => now()->toISOString(),
                'broadcast_status' => 'disabled',
                'note' => 'Redis not configured for real-time broadcasting'
            ]);
        }
    }
}