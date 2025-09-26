<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\gService;
use App\Services\SecurityMonitoringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Security Dashboard Controller
 * 
 * Provides security monitoring and analytics
 */
class SecurityDashboardController extends Controller
{
    private SecurityMonitoringService $securityMonitoringService;

    public function __construct(SecurityMonitoringService $securityMonitoringService)
    {
        $this->securityMonitoringService = $securityMonitoringService;
    }

    /**
     * Get security overview
     */
    public function getSecurityOverview(): JsonResponse
    {
        try {
            $overview = [
                'total_users' => $this->getTotalUsers(),
                'active_users' => $this->getActiveUsers(),
                'failed_logins_24h' => $this->getFailedLogins24h(),
                'suspicious_activities_24h' => $this->getSuspiciousActivities24h(),
                'security_events_24h' => $this->getSecurityEvents24h(),
                'mfa_enabled_users' => $this->getMFAEnabledUsers(),
                'unverified_users' => $this->getUnverifiedUsers(),
                'last_security_scan' => $this->getLastSecurityScan(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $overview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get security overview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get security events timeline
     */
    public function getSecurityEventsTimeline(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'days' => 'nullable|integer|min:1|max:30',
                'event_type' => 'nullable|string|in:login_failure,suspicious_request,file_upload,admin_action,unusual_login',
            ]);

            $days = $request->input('days', 7);
            $eventType = $request->input('event_type');

            $events = $this->securityMonitoringService->getSecurityEventsTimeline($days, $eventType);

            return response()->json([
                'status' => 'success',
                'data' => $events
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get security events timeline: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get failed login attempts
     */
    public function getFailedLoginAttempts(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'days' => 'nullable|integer|min:1|max:30',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $days = $request->input('days', 7);
            $limit = $request->input('limit', 50);

            $attempts = $this->securityMonitoringService->getFailedLoginAttempts($days, $limit);

            return response()->json([
                'status' => 'success',
                'data' => $attempts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get failed login attempts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get suspicious activities
     */
    public function getSuspiciousActivities(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'days' => 'nullable|integer|min:1|max:30',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $days = $request->input('days', 7);
            $limit = $request->input('limit', 50);

            $activities = $this->securityMonitoringService->getSuspiciousActivities($days, $limit);

            return response()->json([
                'status' => 'success',
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get suspicious activities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user security status
     */
    public function getUserSecurityStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $userId = $request->input('user_id');
            $limit = $request->input('limit', 50);

            $status = $this->securityMonitoringService->getUserSecurityStatus($userId, $limit);

            return response()->json([
                'status' => 'success',
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get user security status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get security recommendations
     */
    public function getSecurityRecommendations(): JsonResponse
    {
        try {
            $recommendations = $this->securityMonitoringService->getSecurityRecommendations();

            return response()->json([
                'status' => 'success',
                'data' => $recommendations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get security recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get security alerts
     */
    public function getSecurityAlerts(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'severity' => 'nullable|string|in:low,medium,high,critical',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $severity = $request->input('severity');
            $limit = $request->input('limit', 50);

            $alerts = $this->securityMonitoringService->getSecurityAlerts($severity, $limit);

            return response()->json([
                'status' => 'success',
                'data' => $alerts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get security alerts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get security metrics
     */
    public function getSecurityMetrics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'period' => 'nullable|string|in:1h,24h,7d,30d',
            ]);

            $period = $request->input('period', '24h');

            $metrics = [
                'login_success_rate' => $this->getLoginSuccessRate($period),
                'mfa_adoption_rate' => $this->getMFAAdoptionRate(),
                'password_strength_score' => $this->getPasswordStrengthScore(),
                'security_events_trend' => $this->getSecurityEventsTrend($period),
                'user_activity_score' => $this->getUserActivityScore($period),
                'threat_level' => $this->getThreatLevel($period),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get security metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export security report
     */
    public function exportSecurityReport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'format' => 'nullable|string|in:json,csv,pdf',
                'period' => 'nullable|string|in:1h,24h,7d,30d',
                'include_details' => 'nullable|boolean',
            ]);

            $format = $request->input('format', 'json');
            $period = $request->input('period', '7d');
            $includeDetails = $request->input('include_details', false);

            $report = $this->securityMonitoringService->generateSecurityReport($period, $includeDetails);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'report' => $report,
                    'format' => $format,
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export security report: ' . $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    private function getTotalUsers(): int
    {
        return Cache::remember('security_total_users', 300, function () {
            return DB::table('users')->count();
        });
    }

    private function getActiveUsers(): int
    {
        return Cache::remember('security_active_users', 300, function () {
            return DB::table('users')
                ->where('is_active', true)
                ->where('last_login_at', '>=', Carbon::now()->subDays(30))
                ->count();
        });
    }

    private function getFailedLogins24h(): int
    {
        return Cache::remember('security_failed_logins_24h', 300, function () {
            return DB::table('audit_logs')
                ->where('action', 'login_failure')
                ->where('created_at', '>=', Carbon::now()->subDay())
                ->count();
        });
    }

    private function getSuspiciousActivities24h(): int
    {
        return Cache::remember('security_suspicious_activities_24h', 300, function () {
            return DB::table('audit_logs')
                ->where('action', 'suspicious_request')
                ->where('created_at', '>=', Carbon::now()->subDay())
                ->count();
        });
    }

    private function getSecurityEvents24h(): int
    {
        return Cache::remember('security_events_24h', 300, function () {
            return DB::table('audit_logs')
                ->whereIn('action', ['login_failure', 'suspicious_request', 'file_upload', 'admin_action', 'unusual_login'])
                ->where('created_at', '>=', Carbon::now()->subDay())
                ->count();
        });
    }

    private function getMFAEnabledUsers(): int
    {
        return Cache::remember('security_mfa_enabled_users', 300, function () {
            return DB::table('users')
                ->where('mfa_enabled', true)
                ->count();
        });
    }

    private function getUnverifiedUsers(): int
    {
        return Cache::remember('security_unverified_users', 300, function () {
            return DB::table('users')
                ->where('email_verified', false)
                ->where('created_at', '<', Carbon::now()->subDays(1))
                ->count();
        });
    }

    private function getLastSecurityScan(): ?string
    {
        return Cache::get('last_security_scan');
    }

    private function getLoginSuccessRate(string $period): float
    {
        $startTime = match($period) {
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay(),
        };

        $totalLogins = DB::table('audit_logs')
            ->whereIn('action', ['login_success', 'login_failure'])
            ->where('created_at', '>=', $startTime)
            ->count();

        $successfulLogins = DB::table('audit_logs')
            ->where('action', 'login_success')
            ->where('created_at', '>=', $startTime)
            ->count();

        return $totalLogins > 0 ? ($successfulLogins / $totalLogins) * 100 : 0;
    }

    private function getMFAAdoptionRate(): float
    {
        $totalUsers = $this->getTotalUsers();
        $mfaUsers = $this->getMFAEnabledUsers();

        return $totalUsers > 0 ? ($mfaUsers / $totalUsers) * 100 : 0;
    }

    private function getPasswordStrengthScore(): float
    {
        // This would require analyzing password hashes
        // For now, return a placeholder value
        return 85.0;
    }

    private function getSecurityEventsTrend(string $period): array
    {
        $startTime = match($period) {
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay(),
        };

        $events = DB::table('audit_logs')
            ->whereIn('action', ['login_failure', 'suspicious_request', 'file_upload', 'admin_action', 'unusual_login'])
            ->where('created_at', '>=', $startTime)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $events->map(function ($event) {
            return [
                'date' => $event->date,
                'count' => $event->count,
            ];
        })->toArray();
    }

    private function getUserActivityScore(string $period): float
    {
        $startTime = match($period) {
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay(),
        };

        $activeUsers = DB::table('users')
            ->where('last_login_at', '>=', $startTime)
            ->count();

        $totalUsers = $this->getTotalUsers();

        return $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0;
    }

    private function getThreatLevel(string $period): string
    {
        $startTime = match($period) {
            '1h' => Carbon::now()->subHour(),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay(),
        };

        $threatCount = DB::table('audit_logs')
            ->whereIn('action', ['suspicious_request', 'unusual_login'])
            ->where('created_at', '>=', $startTime)
            ->count();

        return match(true) {
            $threatCount >= 100 => 'Critical',
            $threatCount >= 50 => 'High',
            $threatCount >= 20 => 'Medium',
            $threatCount >= 5 => 'Low',
            default => 'Minimal',
        };
    }
}