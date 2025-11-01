<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Models\User;
use Carbon\Carbon;

/**
 * Session Cache Service
 * 
 * Handles caching of user sessions for performance optimization
 */
class SessionCacheService
{
    private const CACHE_PREFIX = 'session:';
    private const USER_SESSION_TTL = 3600; // 1 hour
    private const USER_PREFERENCES_TTL = 86400; // 24 hours
    private const USER_ACTIVITY_TTL = 1800; // 30 minutes

    /**
     * Cache user session data
     */
    public function cacheUserSession(string $userId, array $sessionData): void
    {
        $cacheKey = $this->getUserSessionKey($userId);
        
        Cache::put($cacheKey, [
            'user_id' => $userId,
            'session_data' => $sessionData,
            'cached_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds(self::USER_SESSION_TTL)->toISOString(),
        ], self::USER_SESSION_TTL);

        Log::debug('User session cached', [
            'user_id' => $userId,
            'cache_key' => $cacheKey
        ]);
    }

    /**
     * Get cached user session data
     */
    public function getCachedUserSession(string $userId): ?array
    {
        $cacheKey = $this->getUserSessionKey($userId);
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData) {
            return null;
        }

        // Check if session is still valid
        if (isset($cachedData['expires_at'])) {
            $expiresAt = Carbon::parse($cachedData['expires_at']);
            if ($expiresAt->isPast()) {
                Cache::forget($cacheKey);
                return null;
            }
        }

        return $cachedData['session_data'] ?? null;
    }

    /**
     * Cache user preferences
     */
    public function cacheUserPreferences(string $userId, array $preferences): void
    {
        $cacheKey = $this->getUserPreferencesKey($userId);
        
        Cache::put($cacheKey, [
            'user_id' => $userId,
            'preferences' => $preferences,
            'cached_at' => now()->toISOString(),
        ], self::USER_PREFERENCES_TTL);

        Log::debug('User preferences cached', [
            'user_id' => $userId,
            'preferences_count' => count($preferences)
        ]);
    }

    /**
     * Get cached user preferences
     */
    public function getCachedUserPreferences(string $userId): ?array
    {
        $cacheKey = $this->getUserPreferencesKey($userId);
        $cachedData = Cache::get($cacheKey);

        return $cachedData['preferences'] ?? null;
    }

    /**
     * Cache user activity
     */
    public function cacheUserActivity(string $userId, string $activity, array $metadata = []): void
    {
        $cacheKey = $this->getUserActivityKey($userId);
        
        $activities = Cache::get($cacheKey, []);
        $activities[] = [
            'activity' => $activity,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ];

        // Keep only last 50 activities
        $activities = array_slice($activities, -50);

        Cache::put($cacheKey, $activities, self::USER_ACTIVITY_TTL);

        Log::debug('User activity cached', [
            'user_id' => $userId,
            'activity' => $activity
        ]);
    }

    /**
     * Get cached user activity
     */
    public function getCachedUserActivity(string $userId, int $limit = 10): array
    {
        $cacheKey = $this->getUserActivityKey($userId);
        $activities = Cache::get($cacheKey, []);

        return array_slice($activities, -$limit);
    }

    /**
     * Cache user dashboard data
     */
    public function cacheUserDashboard(string $userId, array $dashboardData): void
    {
        $cacheKey = $this->getUserDashboardKey($userId);
        
        Cache::put($cacheKey, [
            'user_id' => $userId,
            'dashboard_data' => $dashboardData,
            'cached_at' => now()->toISOString(),
        ], self::USER_SESSION_TTL);

        Log::debug('User dashboard cached', [
            'user_id' => $userId,
            'widgets_count' => count($dashboardData['widgets'] ?? [])
        ]);
    }

    /**
     * Get cached user dashboard data
     */
    public function getCachedUserDashboard(string $userId): ?array
    {
        $cacheKey = $this->getUserDashboardKey($userId);
        $cachedData = Cache::get($cacheKey);

        return $cachedData['dashboard_data'] ?? null;
    }

    /**
     * Clear user session cache
     */
    public function clearUserSessionCache(string $userId): void
    {
        $keys = [
            $this->getUserSessionKey($userId),
            $this->getUserPreferencesKey($userId),
            $this->getUserActivityKey($userId),
            $this->getUserDashboardKey($userId),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Log::info('User session cache cleared', ['user_id' => $userId]);
    }

    /**
     * Clear all session cache
     */
    public function clearAllSessionCache(): void
    {
        // Note: This is a simplified implementation
        // In production, you might want to use Redis SCAN to find and delete keys
        Cache::flush();

        Log::info('All session cache cleared');
    }

    /**
     * Get session cache statistics
     */
    public function getSessionCacheStats(): array
    {
        return [
            'cache_driver' => config('cache.default'),
            'redis_connected' => $this->isRedisConnected(),
            'cache_prefix' => self::CACHE_PREFIX,
            'user_session_ttl' => self::USER_SESSION_TTL,
            'user_preferences_ttl' => self::USER_PREFERENCES_TTL,
            'user_activity_ttl' => self::USER_ACTIVITY_TTL,
        ];
    }

    /**
     * Get user session key
     */
    private function getUserSessionKey(string $userId): string
    {
        return self::CACHE_PREFIX . 'user:' . $userId . ':session';
    }

    /**
     * Get user preferences key
     */
    private function getUserPreferencesKey(string $userId): string
    {
        return self::CACHE_PREFIX . 'user:' . $userId . ':preferences';
    }

    /**
     * Get user activity key
     */
    private function getUserActivityKey(string $userId): string
    {
        return self::CACHE_PREFIX . 'user:' . $userId . ':activity';
    }

    /**
     * Get user dashboard key
     */
    private function getUserDashboardKey(string $userId): string
    {
        return self::CACHE_PREFIX . 'user:' . $userId . ':dashboard';
    }

    /**
     * Check if Redis is connected
     */
    private function isRedisConnected(): bool
    {
        try {
            Cache::store('redis')->get('test');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extend user session
     */
    public function extendUserSession(string $userId): void
    {
        $cacheKey = $this->getUserSessionKey($userId);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            $cachedData['expires_at'] = now()->addSeconds(self::USER_SESSION_TTL)->toISOString();
            Cache::put($cacheKey, $cachedData, self::USER_SESSION_TTL);

            Log::debug('User session extended', ['user_id' => $userId]);
        }
    }

    /**
     * Get active sessions count
     */
    public function getActiveSessionsCount(): int
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN to count keys
        try {
            $keys = Cache::store('redis')->keys(self::CACHE_PREFIX . 'user:*:session');
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
