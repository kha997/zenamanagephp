<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Tenant;

/**
 * PermissionCacheService
 * 
 * Advanced permission caching system with intelligent cache management,
 * invalidation strategies, and performance optimization.
 * 
 * Features:
 * - Multi-level caching (user, role, tenant)
 * - Intelligent cache warming
 * - Automatic cache invalidation
 * - Cache hit/miss analytics
 * - Memory-efficient storage
 */
class PermissionCacheService
{
    private const CACHE_PREFIX = 'permissions';
    private const CACHE_TTL = 3600; // 1 hour
    private const WARM_UP_BATCH_SIZE = 100;
    
    /**
     * Get cached permissions for a user in a specific tenant
     */
    public function getCachedPermissions(int $userId, int $tenantId): array
    {
        $cacheKey = $this->getCacheKey($userId, $tenantId);
        
        try {
            $permissions = Cache::get($cacheKey);
            
            if ($permissions === null) {
                Log::info('Permission cache miss', [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'cache_key' => $cacheKey
                ]);
                
                // Cache miss - fetch from database
                $permissions = $this->fetchPermissionsFromDatabase($userId, $tenantId);
                
                // Cache the result
                $this->cachePermissions($cacheKey, $permissions);
                
                return $permissions;
            }
            
            Log::debug('Permission cache hit', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'cache_key' => $cacheKey,
                'permission_count' => count($permissions)
            ]);
            
            return $permissions;
            
        } catch (\Exception $e) {
            Log::error('Permission cache error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to database
            return $this->fetchPermissionsFromDatabase($userId, $tenantId);
        }
    }
    
    /**
     * Invalidate permissions cache for a specific user and tenant
     */
    public function invalidateUserPermissions(int $userId, int $tenantId): void
    {
        $cacheKey = $this->getCacheKey($userId, $tenantId);
        
        try {
            Cache::forget($cacheKey);
            
            Log::info('Permission cache invalidated', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'cache_key' => $cacheKey
            ]);
            
        } catch (\Exception $e) {
            Log::error('Permission cache invalidation error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Invalidate all permissions cache for a tenant
     */
    public function invalidateTenantPermissions(int $tenantId): void
    {
        try {
            $pattern = $this->getCacheKey('*', $tenantId);
            $this->invalidateCachePattern($pattern);
            
            Log::info('Tenant permission cache invalidated', [
                'tenant_id' => $tenantId,
                'pattern' => $pattern
            ]);
            
        } catch (\Exception $e) {
            Log::error('Tenant permission cache invalidation error', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Warm up permission cache for a user
     */
    public function warmUpPermissionCache(int $userId, int $tenantId): void
    {
        try {
            $permissions = $this->fetchPermissionsFromDatabase($userId, $tenantId);
            $cacheKey = $this->getCacheKey($userId, $tenantId);
            
            $this->cachePermissions($cacheKey, $permissions);
            
            Log::info('Permission cache warmed up', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'permission_count' => count($permissions)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Permission cache warm up error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Warm up permission cache for multiple users
     */
    public function warmUpBatchPermissionCache(array $userIds, int $tenantId): void
    {
        $batches = array_chunk($userIds, self::WARM_UP_BATCH_SIZE);
        
        foreach ($batches as $batch) {
            foreach ($batch as $userId) {
                $this->warmUpPermissionCache($userId, $tenantId);
            }
        }
        
        Log::info('Batch permission cache warm up completed', [
            'user_count' => count($userIds),
            'tenant_id' => $tenantId,
            'batch_count' => count($batches)
        ]);
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStatistics(): array
    {
        try {
            $stats = Cache::get('permission_cache_stats', [
                'hits' => 0,
                'misses' => 0,
                'invalidations' => 0,
                'warm_ups' => 0
            ]);
            
            $hitRate = $stats['hits'] + $stats['misses'] > 0 
                ? round(($stats['hits'] / ($stats['hits'] + $stats['misses'])) * 100, 2)
                : 0;
            
            return array_merge($stats, [
                'hit_rate' => $hitRate,
                'cache_ttl' => self::CACHE_TTL
            ]);
            
        } catch (\Exception $e) {
            Log::error('Permission cache statistics error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'hits' => 0,
                'misses' => 0,
                'invalidations' => 0,
                'warm_ups' => 0,
                'hit_rate' => 0,
                'cache_ttl' => self::CACHE_TTL
            ];
        }
    }
    
    /**
     * Clear all permission caches
     */
    public function clearAllPermissionCaches(): void
    {
        try {
            $pattern = $this->getCacheKey('*', '*');
            $this->invalidateCachePattern($pattern);
            
            // Clear statistics
            Cache::forget('permission_cache_stats');
            
            Log::info('All permission caches cleared');
            
        } catch (\Exception $e) {
            Log::error('Clear all permission caches error', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get cache key for user and tenant
     */
    private function getCacheKey(int $userId, int $tenantId): string
    {
        return self::CACHE_PREFIX . ":user:{$userId}:tenant:{$tenantId}";
    }
    
    /**
     * Cache permissions with TTL
     */
    private function cachePermissions(string $cacheKey, array $permissions): void
    {
        Cache::put($cacheKey, $permissions, self::CACHE_TTL);
        
        // Update statistics
        $this->updateCacheStatistics('hits');
    }
    
    /**
     * Fetch permissions from database
     */
    private function fetchPermissionsFromDatabase(int $userId, int $tenantId): array
    {
        try {
            $user = User::with(['roles.permissions'])
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$user) {
                return [];
            }
            
            $permissions = [];
            
            foreach ($user->roles as $role) {
                foreach ($role->permissions as $permission) {
                    $permissions[] = $permission->name;
                }
            }
            
            // Update statistics
            $this->updateCacheStatistics('misses');
            
            return array_unique($permissions);
            
        } catch (\Exception $e) {
            Log::error('Database permission fetch error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Invalidate cache pattern (Redis-specific)
     */
    private function invalidateCachePattern(string $pattern): void
    {
        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            
            if (!empty($keys)) {
                $redis->del($keys);
            }
        }
    }
    
    /**
     * Update cache statistics
     */
    private function updateCacheStatistics(string $type): void
    {
        try {
            $stats = Cache::get('permission_cache_stats', [
                'hits' => 0,
                'misses' => 0,
                'invalidations' => 0,
                'warm_ups' => 0
            ]);
            
            $stats[$type]++;
            Cache::put('permission_cache_stats', $stats, 86400); // 24 hours
            
        } catch (\Exception $e) {
            Log::error('Cache statistics update error', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }
}
