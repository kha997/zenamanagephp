<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Session Service
 * 
 * Handles user session management operations
 */
class SessionService
{
    /**
     * Create a new session for user
     */
    public function createSession(User $user, string $ip, string $userAgent): UserSession
    {
        $token = Str::random(64);
        
        $session = UserSession::create([
            'user_id' => $user->id,
            'session_id' => $token, // Using session_id as token
            'ip_address' => $ip,
            'last_activity_at' => now(), // Using last_activity_at
            'expires_at' => now()->addHours(config('sanctum.expiration', 24)),
        ]);

        Log::info('Session created', [
            'user_id' => $user->id,
            'session_id' => $session->id,
        ]);

        return $session;
    }

    /**
     * Get all active sessions for user
     */
    public function getUserSessions(User $user): Collection
    {
        return UserSession::where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('expires_at', '>', now())
                      ->orWhereNull('expires_at');
            })
            ->orderBy('last_activity_at', 'desc')
            ->get();
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(User $user, string $sessionId): bool
    {
        $session = UserSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return false;
        }

        // Set expires_at to now to invalidate
        $session->update(['expires_at' => now()]);

        Log::info('Session revoked', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
        ]);

        return true;
    }

    /**
     * Revoke all sessions for user (except current)
     */
    public function revokeAllSessions(User $user, ?string $currentSessionId = null): int
    {
        $query = UserSession::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('expires_at', '>', now())
                  ->orWhereNull('expires_at');
            });

        if ($currentSessionId) {
            $query->where('id', '!=', $currentSessionId);
        }

        $count = $query->count();
        
        $query->update(['expires_at' => now()]);

        Log::info('All sessions revoked', [
            'user_id' => $user->id,
            'sessions_revoked' => $count,
        ]);

        return $count;
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions(): int
    {
        $count = UserSession::where('expires_at', '<=', now())
            ->delete();

        Log::info('Expired sessions cleaned', [
            'sessions_deleted' => $count,
        ]);

        return $count;
    }
}

