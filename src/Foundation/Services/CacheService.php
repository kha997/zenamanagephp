<?php declare(strict_types=1);

namespace Src\Foundation\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service quản lý cache với prefix và TTL tự động
 * Hỗ trợ cache invalidation và bulk operations
 */
class CacheService
{
    private string $prefix;
    private int $defaultTtl;
    
    public function __construct(string $prefix = 'zena', int $defaultTtl = 3600)
    {
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
    }
    
    /**
     * Lưu dữ liệu vào cache với key có prefix
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $cacheKey = $this->buildKey($key);
            $ttl = $ttl ?? $this->defaultTtl;
            
            return Cache::put($cacheKey, $value, $ttl);
        } catch (\Exception $e) {
            Log::error('Cache put failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Lấy dữ liệu từ cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $cacheKey = $this->buildKey($key);
            return Cache::get($cacheKey, $default);
        } catch (\Exception $e) {
            Log::error('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }
    
    /**
     * Lấy hoặc tạo cache với callback
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        try {
            $cacheKey = $this->buildKey($key);
            $ttl = $ttl ?? $this->defaultTtl;
            
            return Cache::remember($cacheKey, $ttl, $callback);
        } catch (\Exception $e) {
            Log::error('Cache remember failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $callback();
        }
    }
    
    /**
     * Xóa cache theo key
     */
    public function forget(string $key): bool
    {
        try {
            $cacheKey = $this->buildKey($key);
            return Cache::forget($cacheKey);
        } catch (\Exception $e) {
            Log::error('Cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Xóa nhiều cache keys cùng lúc
     */
    public function forgetMany(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->forget($key);
        }
        return $results;
    }
    
    /**
     * Xóa cache theo pattern (Redis only)
     */
    public function forgetByPattern(string $pattern): int
    {
        try {
            $fullPattern = $this->buildKey($pattern);
            $keys = Cache::getRedis()->keys($fullPattern);
            
            if (empty($keys)) {
                return 0;
            }
            
            return Cache::getRedis()->del($keys);
        } catch (\Exception $e) {
            Log::error('Cache pattern delete failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Invalidate cache cho user cụ thể
     */
    public function invalidateUserCache(string $userId): bool
    {
        $patterns = [
            "user:{$userId}:*",
            "permissions:{$userId}:*",
            "roles:{$userId}:*"
        ];
        
        $totalDeleted = 0;
        foreach ($patterns as $pattern) {
            $totalDeleted += $this->forgetByPattern($pattern);
        }
        
        Log::info('User cache invalidated', [
            'user_id' => $userId,
            'keys_deleted' => $totalDeleted
        ]);
        
        return $totalDeleted > 0;
    }
    
    /**
     * Invalidate cache cho project cụ thể
     */
    public function invalidateProjectCache(string $projectId): bool
    {
        $patterns = [
            "project:{$projectId}:*",
            "tasks:{$projectId}:*",
            "components:{$projectId}:*"
        ];
        
        $totalDeleted = 0;
        foreach ($patterns as $pattern) {
            $totalDeleted += $this->forgetByPattern($pattern);
        }
        
        Log::info('Project cache invalidated', [
            'project_id' => $projectId,
            'keys_deleted' => $totalDeleted
        ]);
        
        return $totalDeleted > 0;
    }
    
    /**
     * Tạo cache key với prefix
     */
    private function buildKey(string $key): string
    {
        return $this->prefix . ':' . $key;
    }
    
    /**
     * Lấy thống kê cache
     */
    public function getStats(): array
    {
        try {
            $redis = Cache::getRedis();
            $info = $redis->info('memory');
            
            return [
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'used_memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
                'keyspace_hits' => $redis->info('stats')['keyspace_hits'] ?? 0,
                'keyspace_misses' => $redis->info('stats')['keyspace_misses'] ?? 0
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}