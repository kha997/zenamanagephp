<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session Management Middleware
 * 
 * Handles user session management, including:
 * - Session timeout
 * - Concurrent session limits
 * - Session security
 * - Activity tracking
 */
class SessionManagementMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return $next($request);
        }
        
        // Check session timeout
        if ($this->isSessionExpired($request)) {
            return $this->handleSessionExpired($request);
        }
        
        // Check concurrent session limits
        if ($this->exceedsConcurrentSessions($user, $request)) {
            return $this->handleConcurrentSessionLimit($request);
        }
        
        // Update session activity
        $this->updateSessionActivity($user, $request);
        
        // Add session info to request
        $request->attributes->set('session_info', [
            'last_activity' => now(),
            'session_id' => $request->session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        
        return $next($request);
    }
    
    /**
     * Check if session has expired
     */
    private function isSessionExpired(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }
        
        $session = $request->session();
        $lastActivity = $session->get('last_activity');
        
        if (!$lastActivity) {
            return false;
        }
        
        $timeout = config('session.lifetime', 120) * 60; // Convert to seconds
        $expired = now()->timestamp - $lastActivity > $timeout;
        
        if ($expired) {
            Log::info('Session expired', [
                'user_id' => Auth::id(),
                'session_id' => $session->getId(),
                'last_activity' => $lastActivity,
                'timeout' => $timeout
            ]);
        }
        
        return $expired;
    }
    
    /**
     * Check if user exceeds concurrent session limits
     */
    private function exceedsConcurrentSessions($user, Request $request): bool
    {
        $maxSessions = config('auth.max_concurrent_sessions', 3);
        
        if ($maxSessions <= 0) {
            return false; // No limit
        }
        
        // Get current session ID
        $currentSessionId = $request->session()->getId();
        
        // Count active sessions for this user
        $activeSessions = $this->getActiveSessions($user);
        
        // If current session is already active, don't count it
        if (in_array($currentSessionId, $activeSessions)) {
            return false;
        }
        
        // Check if adding this session would exceed the limit
        if (count($activeSessions) >= $maxSessions) {
            Log::warning('Concurrent session limit exceeded', [
                'user_id' => $user->id,
                'current_sessions' => count($activeSessions),
                'max_sessions' => $maxSessions,
                'current_session_id' => $currentSessionId
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get active sessions for user
     */
    private function getActiveSessions($user): array
    {
        // This is a simplified implementation
        // In a real application, you might want to store session info in database
        
        $activeSessions = [];
        
        // Get sessions from cache or database
        $sessions = cache()->get("user_sessions_{$user->id}", []);
        
        foreach ($sessions as $sessionId => $sessionData) {
            if ($this->isSessionActive($sessionData)) {
                $activeSessions[] = $sessionId;
            }
        }
        
        return $activeSessions;
    }
    
    /**
     * Check if session is still active
     */
    private function isSessionActive(array $sessionData): bool
    {
        $timeout = config('session.lifetime', 120) * 60;
        $lastActivity = $sessionData['last_activity'] ?? 0;
        
        return now()->timestamp - $lastActivity <= $timeout;
    }
    
    /**
     * Update session activity
     */
    private function updateSessionActivity($user, Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }
        
        $session = $request->session();
        $sessionId = $session->getId();
        
        // Update session last activity
        $session->put('last_activity', now()->timestamp);
        
        // Update user's last activity
        $user->update(['last_activity_at' => now()]);
        
        // Store session info in cache
        $sessions = cache()->get("user_sessions_{$user->id}", []);
        $sessions[$sessionId] = [
            'last_activity' => now()->timestamp,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => $sessions[$sessionId]['created_at'] ?? now()->timestamp
        ];
        
        cache()->put("user_sessions_{$user->id}", $sessions, now()->addHours(24));
    }
    
    /**
     * Handle session expired
     */
    private function handleSessionExpired(Request $request): Response
    {
        // Clear the session
        if ($request->hasSession()) {
            $request->session()->invalidate();
        }
        
        // Logout the user
        Auth::logout();
        
        return response()->json([
            'success' => false,
            'error' => 'Session Expired',
            'message' => 'Your session has expired. Please log in again.',
            'code' => 'SESSION_EXPIRED'
        ], 401);
    }
    
    /**
     * Handle concurrent session limit
     */
    private function handleConcurrentSessionLimit(Request $request): Response
    {
        return response()->json([
            'success' => false,
            'error' => 'Session Limit Exceeded',
            'message' => 'You have reached the maximum number of concurrent sessions. Please log out from another device.',
            'code' => 'SESSION_LIMIT_EXCEEDED'
        ], 409);
    }
}
