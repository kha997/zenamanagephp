<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Media Quota Service
 * 
 * Manages storage quotas per tenant for media files.
 * Tracks usage and enforces limits.
 */
class MediaQuotaService
{
    /**
     * Check if tenant can upload file of given size
     * 
     * @param string $tenantId
     * @param int $fileSizeBytes
     * @return array{allowed: bool, message?: string, quota_mb?: float, used_mb?: float, remaining_mb?: float}
     */
    public function canUpload(string $tenantId, int $fileSizeBytes): array
    {
        $quota = $this->getQuota($tenantId);
        $used = $this->getUsedStorage($tenantId);
        $fileSizeMb = $fileSizeBytes / (1024 * 1024);
        
        $remaining = $quota - $used;
        
        if ($fileSizeMb > $remaining) {
            return [
                'allowed' => false,
                'message' => "Storage quota exceeded. Remaining: {$remaining}MB, Required: {$fileSizeMb}MB",
                'quota_mb' => $quota,
                'used_mb' => $used,
                'remaining_mb' => $remaining,
            ];
        }

        return [
            'allowed' => true,
            'quota_mb' => $quota,
            'used_mb' => $used,
            'remaining_mb' => $remaining,
        ];
    }

    /**
     * Record file upload (increase used storage)
     */
    public function recordUpload(string $tenantId, int $fileSizeBytes): void
    {
        $fileSizeMb = $fileSizeBytes / (1024 * 1024);
        
        // Update cache
        $cacheKey = "media:quota:used:{$tenantId}";
        $current = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $current + $fileSizeMb, 86400); // 24 hours
        
        // Update database (async via queue if needed)
        DB::table('tenants')
            ->where('id', $tenantId)
            ->increment('media_used_mb', $fileSizeMb);
        
        Log::debug('Media quota updated', [
            'tenant_id' => $tenantId,
            'file_size_mb' => $fileSizeMb,
        ]);
    }

    /**
     * Record file deletion (decrease used storage)
     */
    public function recordDeletion(string $tenantId, int $fileSizeBytes): void
    {
        $fileSizeMb = $fileSizeBytes / (1024 * 1024);
        
        // Update cache
        $cacheKey = "media:quota:used:{$tenantId}";
        $current = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, max(0, $current - $fileSizeMb), 86400);
        
        // Update database
        DB::table('tenants')
            ->where('id', $tenantId)
            ->decrement('media_used_mb', $fileSizeMb);
        
        Log::debug('Media quota updated (deletion)', [
            'tenant_id' => $tenantId,
            'file_size_mb' => $fileSizeMb,
        ]);
    }

    /**
     * Get quota for tenant (in MB)
     */
    public function getQuota(string $tenantId): float
    {
        $cacheKey = "media:quota:limit:{$tenantId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($tenantId) {
            $tenant = DB::table('tenants')
                ->where('id', $tenantId)
                ->select('media_quota_mb')
                ->first();
            
            return (float) ($tenant->media_quota_mb ?? config('media.default_quota_mb', 10240));
        });
    }

    /**
     * Get used storage for tenant (in MB)
     */
    public function getUsedStorage(string $tenantId): float
    {
        $cacheKey = "media:quota:used:{$tenantId}";
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId) {
            $tenant = DB::table('tenants')
                ->where('id', $tenantId)
                ->select('media_used_mb')
                ->first();
            
            return (float) ($tenant->media_used_mb ?? 0);
        });
    }

    /**
     * Get quota usage percentage
     */
    public function getUsagePercentage(string $tenantId): float
    {
        $quota = $this->getQuota($tenantId);
        $used = $this->getUsedStorage($tenantId);
        
        if ($quota == 0) {
            return 0;
        }
        
        return min(100, ($used / $quota) * 100);
    }
}
