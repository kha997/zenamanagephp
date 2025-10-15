<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Models\UserPreference;
use App\Models\Tenant;

class FeatureFlagService
{
    /**
     * Check if a feature flag is enabled
     *
     * @param string $flag
     * @param string|null $tenantId
     * @param string|null $userId
     * @return bool
     */
    public function isEnabled(string $flag, ?string $tenantId = null, ?string $userId = null): bool
    {
        $cacheKey = $this->getCacheKey($flag, $tenantId, $userId);
        
        return Cache::remember($cacheKey, Config::get('features.cache.ttl', 300), function () use ($flag, $tenantId, $userId) {
            // Check user-specific setting first (highest priority)
            if ($userId) {
                $userEnabled = $this->getUserFeatureFlag($flag, $userId);
                if ($userEnabled !== null) {
                    return $userEnabled;
                }
            }
            
            // Check tenant-specific setting (medium priority)
            if ($tenantId) {
                $tenantEnabled = $this->getTenantFeatureFlag($flag, $tenantId);
                if ($tenantEnabled !== null) {
                    return $tenantEnabled;
                }
            }
            
            // Check global setting (lowest priority)
            return Config::get("features.{$flag}", false);
        });
    }

    /**
     * Enable a feature flag for a specific context
     *
     * @param string $flag
     * @param bool $enabled
     * @param string|null $tenantId
     * @param string|null $userId
     * @return bool
     */
    public function setEnabled(string $flag, bool $enabled, ?string $tenantId = null, ?string $userId = null): bool
    {
        if ($userId) {
            return $this->setUserFeatureFlag($flag, $enabled, $userId);
        }
        
        if ($tenantId) {
            return $this->setTenantFeatureFlag($flag, $enabled, $tenantId);
        }
        
        return $this->setGlobalFeatureFlag($flag, $enabled);
    }

    /**
     * Get all feature flags for a context
     *
     * @param string|null $tenantId
     * @param string|null $userId
     * @return array
     */
    public function getAllFlags(?string $tenantId = null, ?string $userId = null): array
    {
        $flags = Config::get('features', []);
        $result = [];
        
        $this->flattenFlags($flags, $result, '');
        
        foreach ($result as $flag => $defaultValue) {
            $result[$flag] = $this->isEnabled($flag, $tenantId, $userId);
        }
        
        return $result;
    }

    /**
     * Clear feature flag cache
     *
     * @param string|null $flag
     * @param string|null $tenantId
     * @param string|null $userId
     * @return void
     */
    public function clearCache(?string $flag = null, ?string $tenantId = null, ?string $userId = null): void
    {
        if ($flag) {
            $cacheKey = $this->getCacheKey($flag, $tenantId, $userId);
            Cache::forget($cacheKey);
        } else {
            // Clear all feature flag cache
            $pattern = Config::get('features.cache.key_prefix', 'feature_flag:') . '*';
            Cache::forget($pattern);
        }
    }

    /**
     * Get cache key for feature flag
     *
     * @param string $flag
     * @param string|null $tenantId
     * @param string|null $userId
     * @return string
     */
    private function getCacheKey(string $flag, ?string $tenantId = null, ?string $userId = null): string
    {
        $prefix = Config::get('features.cache.key_prefix', 'feature_flag:');
        $key = $prefix . $flag;
        
        if ($tenantId) {
            $key .= ":tenant:{$tenantId}";
        }
        
        if ($userId) {
            $key .= ":user:{$userId}";
        }
        
        return $key;
    }

    /**
     * Get tenant-specific feature flag
     *
     * @param string $flag
     * @param string $tenantId
     * @return bool|null
     */
    private function getTenantFeatureFlag(string $flag, string $tenantId): ?bool
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return null;
        }
        
        $preferences = $tenant->preferences ?? [];
        return data_get($preferences, "feature_flags.{$flag}");
    }

    /**
     * Set tenant-specific feature flag
     *
     * @param string $flag
     * @param bool $enabled
     * @param string $tenantId
     * @return bool
     */
    private function setTenantFeatureFlag(string $flag, bool $enabled, string $tenantId): bool
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return false;
        }
        
        $preferences = $tenant->preferences ?? [];
        data_set($preferences, "feature_flags.{$flag}", $enabled);
        
        $tenant->update(['preferences' => $preferences]);
        
        $this->clearCache($flag, $tenantId);
        
        return true;
    }

    /**
     * Get user-specific feature flag
     *
     * @param string $flag
     * @param string $userId
     * @return bool|null
     */
    private function getUserFeatureFlag(string $flag, string $userId): ?bool
    {
        $preference = UserPreference::where('user_id', $userId)->first();
        if (!$preference) {
            return null;
        }
        
        $preferences = $preference->preferences ?? [];
        return data_get($preferences, "feature_flags.{$flag}");
    }

    /**
     * Set user-specific feature flag
     *
     * @param string $flag
     * @param bool $enabled
     * @param string $userId
     * @return bool
     */
    private function setUserFeatureFlag(string $flag, bool $enabled, string $userId): bool
    {
        $preference = UserPreference::updateOrCreate(
            ['user_id' => $userId],
            ['preferences' => []]
        );
        
        $preferences = $preference->preferences ?? [];
        data_set($preferences, "feature_flags.{$flag}", $enabled);
        
        $preference->update(['preferences' => $preferences]);
        
        $this->clearCache($flag, null, $userId);
        
        return true;
    }

    /**
     * Set global feature flag (environment variable)
     *
     * @param string $flag
     * @param bool $enabled
     * @return bool
     */
    private function setGlobalFeatureFlag(string $flag, bool $enabled): bool
    {
        // This would typically update environment variables or config files
        // For now, we'll just clear cache and return true
        $this->clearCache($flag);
        
        return true;
    }

    /**
     * Flatten nested feature flags array
     *
     * @param array $flags
     * @param array &$result
     * @param string $prefix
     * @return void
     */
    private function flattenFlags(array $flags, array &$result, string $prefix): void
    {
        foreach ($flags as $key => $value) {
            if (is_array($value)) {
                $this->flattenFlags($value, $result, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = $value;
            }
        }
    }
}
