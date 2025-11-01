<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Session Management Service
 * 
 * Handles user sessions, device management, and security monitoring
 */
class SessionManagementService
{
    private SecureAuditService $auditService;
    private int $sessionLifetime;
    private int $maxSessionsPerUser;

    public function __construct(SecureAuditService $auditService)
    {
        $this->auditService = $auditService;
        $this->sessionLifetime = config('session.lifetime', 120); // minutes
        $this->maxSessionsPerUser = config('session.max_per_user', 5);
    }

    /**
     * Create new session for user
     */
    public function createSession(User $user, Request $request, string $sessionId = null): array
    {
        try {
            $sessionId = $sessionId ?: Str::uuid();
            $deviceInfo = $this->extractDeviceInfo($request);
            
            // Check if we need to revoke old sessions
            $this->enforceMaxSessions($user);

            // Mark all other sessions as not current
            DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->update(['is_current' => false]);

            // Create new session
            DB::table('user_sessions')->insert([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'device_id' => $deviceInfo['device_id'],
                'device_name' => $deviceInfo['device_name'],
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'browser_version' => $deviceInfo['browser_version'],
                'os' => $deviceInfo['os'],
                'os_version' => $deviceInfo['os_version'],
                'ip_address' => $request->ip(),
                'country' => $deviceInfo['country'],
                'city' => $deviceInfo['city'],
                'is_current' => true,
                'is_trusted' => false,
                'last_activity_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addMinutes($this->sessionLifetime),
                'metadata' => json_encode($deviceInfo['metadata']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // Log session creation
            $this->auditService->logAction(
                userId: $user->id,
                action: 'session_created',
                entityType: 'Session',
                entityId: $sessionId,
                newData: [
                    'device_name' => $deviceInfo['device_name'],
                    'ip_address' => $request->ip(),
                    'browser' => $deviceInfo['browser']
                ],
                tenantId: $user->tenant_id
            );

            return [
                'success' => true,
                'session_id' => $sessionId,
                'expires_at' => Carbon::now()->addMinutes($this->sessionLifetime)
            ];

        } catch (\Exception $e) {
            Log::error('Session creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to create session'
            ];
        }
    }

    /**
     * Update session activity
     */
    public function updateSessionActivity(string $sessionId, Request $request = null): bool
    {
        try {
            $updateData = [
                'last_activity_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addMinutes($this->sessionLifetime),
                'updated_at' => Carbon::now()
            ];

            if ($request) {
                $updateData['ip_address'] = $request->ip();
            }

            DB::table('user_sessions')
                ->where('session_id', $sessionId)
                ->update($updateData);

            return true;

        } catch (\Exception $e) {
            Log::error('Session activity update failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoke session
     */
    public function revokeSession(string $sessionId, User $user = null): bool
    {
        try {
            $session = DB::table('user_sessions')
                ->where('session_id', $sessionId)
                ->first();

            if (!$session) {
                return false;
            }

            // Delete session
            DB::table('user_sessions')
                ->where('session_id', $sessionId)
                ->delete();

            // Log session revocation
            if ($user) {
                $this->auditService->logAction(
                    userId: $user->id,
                    action: 'session_revoked',
                    entityType: 'Session',
                    entityId: $sessionId,
                    oldData: ['session_active' => true],
                    tenantId: $user->tenant_id
                );
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Session revocation failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoke all sessions for user
     */
    public function revokeAllSessions(User $user): bool
    {
        try {
            $sessions = DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->get();

            DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->delete();

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'all_sessions_revoked',
                entityType: 'User',
                entityId: $user->id,
                newData: ['sessions_revoked' => count($sessions)],
                tenantId: $user->tenant_id
            );

            return true;

        } catch (\Exception $e) {
            Log::error('All sessions revocation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user sessions
     */
    public function getUserSessions(User $user): array
    {
        try {
            $sessions = DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->orderBy('last_activity_at', 'desc')
                ->get();

            return $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'session_id' => $session->session_id,
                    'device_name' => $session->device_name,
                    'device_type' => $session->device_type,
                    'browser' => $session->browser,
                    'os' => $session->os,
                    'ip_address' => $session->ip_address,
                    'country' => $session->country,
                    'city' => $session->city,
                    'is_current' => $session->is_current,
                    'is_trusted' => $session->is_trusted,
                    'last_activity_at' => $session->last_activity_at,
                    'expires_at' => $session->expires_at,
                    'created_at' => $session->created_at
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Get user sessions failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trust device
     */
    public function trustDevice(string $sessionId, User $user): bool
    {
        try {
            DB::table('user_sessions')
                ->where('session_id', $sessionId)
                ->where('user_id', $user->id)
                ->update(['is_trusted' => true]);

            // Log action
            $this->auditService->logAction(
                userId: $user->id,
                action: 'device_trusted',
                entityType: 'Session',
                entityId: $sessionId,
                newData: ['is_trusted' => true],
                tenantId: $user->tenant_id
            );

            return true;

        } catch (\Exception $e) {
            Log::error('Device trust failed', [
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check for suspicious activity
     */
    public function checkSuspiciousActivity(User $user, Request $request): array
    {
        try {
            $currentIp = $request->ip();
            $deviceInfo = $this->extractDeviceInfo($request);

            // Check for new IP address
            $recentSessions = DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->where('last_activity_at', '>', Carbon::now()->subDays(30))
                ->get();

            $knownIps = $recentSessions->pluck('ip_address')->unique();
            $isNewIp = !$knownIps->contains($currentIp);

            // Check for new device
            $knownDevices = $recentSessions->pluck('device_id')->unique();
            $isNewDevice = !$knownDevices->contains($deviceInfo['device_id']);

            // Check for new location (if we have geo data)
            $knownCountries = $recentSessions->pluck('country')->filter()->unique();
            $isNewLocation = $deviceInfo['country'] && 
                           !$knownCountries->contains($deviceInfo['country']);

            $riskLevel = 'low';
            $alerts = [];

            if ($isNewIp) {
                $riskLevel = 'medium';
                $alerts[] = 'New IP address detected';
            }

            if ($isNewDevice) {
                $riskLevel = 'high';
                $alerts[] = 'New device detected';
            }

            if ($isNewLocation) {
                $riskLevel = 'high';
                $alerts[] = 'New location detected';
            }

            return [
                'risk_level' => $riskLevel,
                'alerts' => $alerts,
                'is_new_ip' => $isNewIp,
                'is_new_device' => $isNewDevice,
                'is_new_location' => $isNewLocation,
                'requires_verification' => $riskLevel === 'high'
            ];

        } catch (\Exception $e) {
            Log::error('Suspicious activity check failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return [
                'risk_level' => 'unknown',
                'alerts' => [],
                'requires_verification' => true
            ];
        }
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions(): int
    {
        try {
            $deleted = DB::table('user_sessions')
                ->where('expires_at', '<', Carbon::now())
                ->delete();

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Clean expired sessions failed', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Enforce maximum sessions per user
     */
    private function enforceMaxSessions(User $user): void
    {
        $sessionCount = DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->count();

        if ($sessionCount >= $this->maxSessionsPerUser) {
            // Remove oldest sessions
            $sessionsToRemove = $sessionCount - $this->maxSessionsPerUser + 1;
            
            DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->orderBy('last_activity_at', 'asc')
                ->limit($sessionsToRemove)
                ->delete();
        }
    }

    /**
     * Extract device information from request
     */
    private function extractDeviceInfo(Request $request): array
    {
        $userAgent = $request->userAgent();
        
        // Simple device detection (in production, 
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/Tablet|iPad/', $userAgent)) {
            $deviceType = 'tablet';
        }

        $browser = 'Unknown';
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Safari ' . $matches[1];
        }

        $os = 'Unknown';
        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches)) {
            $os = 'Windows ' . $matches[1];
        } elseif (preg_match('/Mac OS X ([0-9_.]+)/', $userAgent, $matches)) {
            $os = 'macOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        }

        return [
            'device_id' => hash('sha256', $userAgent . $request->ip()),
            'device_name' => $this->generateDeviceName($deviceType, $os, $browser),
            'device_type' => $deviceType,
            'browser' => $browser,
            'browser_version' => null,
            'os' => $os,
            'os_version' => null,
            'country' => null, // Would need geo IP service
            'city' => null,
            'metadata' => [
                'user_agent' => $userAgent,
                'accept_language' => $request->header('Accept-Language'),
                'accept_encoding' => $request->header('Accept-Encoding')
            ]
        ];
    }

    /**
     * Generate friendly device name
     */
    private function generateDeviceName(string $deviceType, string $os, string $browser): string
    {
        return ucfirst($deviceType) . ' - ' . $os . ' (' . $browser . ')';
    }
}