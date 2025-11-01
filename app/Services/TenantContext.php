<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TenantContext
{
    private static ?string $currentTenantId = null;
    private static ?string $currentUserId = null;

    /**
     * Set the current tenant context
     */
    public static function set(string $tenantId, ?string $userId = null): void
    {
        self::$currentTenantId = $tenantId;
        self::$currentUserId = $userId;
        
        // Log context change
        Log::info('Tenant context set', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'x_request_id' => request()->header('X-Request-Id')
        ]);
    }

    /**
     * Get the current tenant ID
     */
    public static function getTenantId(): ?string
    {
        return self::$currentTenantId;
    }

    /**
     * Get the current user ID
     */
    public static function getUserId(): ?string
    {
        return self::$currentUserId;
    }

    /**
     * Clear the tenant context
     */
    public static function clear(): void
    {
        self::$currentTenantId = null;
        self::$currentUserId = null;
    }

    /**
     * Check if tenant context is set
     */
    public static function hasContext(): bool
    {
        return self::$currentTenantId !== null;
    }

    /**
     * Get Redis key with tenant prefix
     */
    public static function getRedisKey(string $key): string
    {
        $tenantId = self::getTenantId();
        if (!$tenantId) {
            throw new \RuntimeException('No tenant context available for Redis key generation');
        }
        
        return "tm:{$tenantId}:{$key}";
    }

    /**
     * Get S3 key with tenant prefix
     */
    public static function getS3Key(string $key): string
    {
        $tenantId = self::getTenantId();
        if (!$tenantId) {
            throw new \RuntimeException('No tenant context available for S3 key generation');
        }
        
        return "tenants/{$tenantId}/{$key}";
    }

    /**
     * Get queue job metadata with tenant context
     */
    public static function getJobMetadata(): array
    {
        return [
            'tenant_id' => self::getTenantId(),
            'user_id' => self::getUserId(),
            'timestamp' => now()->toISOString()
        ];
    }
}
