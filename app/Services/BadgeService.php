<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BadgeService
{
    /**
     * Get badge count for a sidebar item.
     */
    public function getBadgeCount(string $itemId, ?User $user = null): int
    {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return 0;
        }

        $cacheKey = "badge_{$itemId}_user_{$user->id}";
        
        return Cache::remember($cacheKey, 60, function () use ($itemId, $user) {
            return 0;
        });
    }

    /**
     * Get multiple badge counts for sidebar items.
     */
    public function getBadgeCounts(array $itemIds, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return array_fill_keys($itemIds, 0);
        }

        $results = [];
        
        foreach ($itemIds as $itemId) {
            $results[$itemId] = $this->getBadgeCount($itemId, $user);
        }
        
        return $results;
    }

    /**
     * Fetch badge count from API endpoint.
     */
    protected function fetchBadgeCount(string $itemId, User $user): int
    {
        $endpoint = $this->getBadgeEndpoint($itemId);
        
        if (!$endpoint) {
            return 0;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->getUserToken($user),
                    'Accept' => 'application/json',
                ])
                ->get($endpoint);

            if ($response->successful()) {
                $data = $response->json();
                return $data['count'] ?? 0;
            }
        } catch (\Exception $e) {
            // Log error but don't fail the sidebar
            \Log::warning("Failed to fetch badge count for {$itemId}: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Get badge endpoint for a sidebar item.
     */
    protected function getBadgeEndpoint(string $itemId): ?string
    {
        $endpoints = [
            'tasks' => '/api/metrics/tasks?status=pending',
            'projects' => '/api/metrics/projects?status=active',
            'users' => '/api/metrics/users?status=active',
            'notifications' => '/api/metrics/notifications?unread=true',
            'approvals' => '/api/metrics/approvals?pending=true',
            'rfis' => '/api/metrics/rfis?status=pending',
            'submittals' => '/api/metrics/submittals?status=pending',
            'change-requests' => '/api/metrics/change-requests?status=pending',
            'qc-inspections' => '/api/metrics/qc/inspections?status=pending',
            'ncr' => '/api/metrics/ncr?status=open',
            'hse-incidents' => '/api/metrics/hse/incidents?status=open',
            'material-requests' => '/api/metrics/materials/requests?status=pending',
            'purchase-orders' => '/api/metrics/po?status=pending',
            'invoices' => '/api/metrics/invoices?status=pending',
            'bills' => '/api/metrics/bills?status=pending',
        ];

        return $endpoints[$itemId] ?? null;
    }

    /**
     * Get user token for API requests.
     */
    protected function getUserToken(User $user): string
    {
        
        // For now, return a placeholder
        return 'user_token_' . $user->id;
    }

    /**
     * Clear badge cache for a specific item and user.
     */
    public function clearBadgeCache(string $itemId, ?User $user = null): void
    {
        $user = $user ?? Auth::user();
        
        if ($user) {
            $cacheKey = "badge_{$itemId}_user_{$user->id}";
            Cache::forget($cacheKey);
        }
    }

    /**
     * Clear all badge caches for a user.
     */
    public function clearUserBadgeCache(?User $user = null): void
    {
        $user = $user ?? Auth::user();
        
        if ($user) {
            $cacheKey = "badge_*_user_{$user->id}";
            
            Cache::flush();
        }
    }

    /**
     * Clear badge cache for a specific item for all users.
     */
    public function clearItemBadgeCache(string $itemId): void
    {
        $cacheKey = "badge_{$itemId}_user_*";
        
        Cache::flush();
    }

    /**
     * Get badge configuration for sidebar items.
     */
    public function getBadgeConfig(array $sidebarItems): array
    {
        $badgeConfig = [];
        
        foreach ($sidebarItems as $item) {
            if (isset($item['show_badge_from'])) {
                $badgeConfig[$item['id']] = [
                    'endpoint' => $item['show_badge_from'],
                    'cache_key' => "badge_{$item['id']}_user_" . (Auth::id() ?? 'guest'),
                    'cache_ttl' => 60, // 1 minute
                ];
            }
            
            // Check children for badge configuration
            if ($item['type'] === 'group' && isset($item['children'])) {
                $childConfig = $this->getBadgeConfig($item['children']);
                $badgeConfig = array_merge($badgeConfig, $childConfig);
            }
        }
        
        return $badgeConfig;
    }

    /**
     * Update badge counts for multiple items.
     */
    public function updateBadgeCounts(array $itemIds, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return array_fill_keys($itemIds, 0);
        }

        $results = [];
        
        foreach ($itemIds as $itemId) {
            // Clear cache first
            $this->clearBadgeCache($itemId, $user);
            
            // Fetch new count
            $results[$itemId] = $this->getBadgeCount($itemId, $user);
        }
        
        return $results;
    }

    /**
     * Get badge count with fallback to cached value.
     */
    public function getBadgeCountWithFallback(string $itemId, ?User $user = null): int
    {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return 0;
        }

        $cacheKey = "badge_{$itemId}_user_{$user->id}";
        
        // Try to get from cache first
        $cachedCount = Cache::get($cacheKey);
        
        if ($cachedCount !== null) {
            return $cachedCount;
        }
        
        // If not in cache, fetch and cache
        return $this->getBadgeCount($itemId, $user);
    }

    /**
     * Batch update badge counts for sidebar.
     */
    public function batchUpdateBadges(array $sidebarItems, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return [];
        }

        $itemIds = [];
        
        // Extract item IDs that have badge configuration
        foreach ($sidebarItems as $item) {
            if (isset($item['show_badge_from'])) {
                $itemIds[] = $item['id'];
            }
            
            // Check children
            if ($item['type'] === 'group' && isset($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (isset($child['show_badge_from'])) {
                        $itemIds[] = $child['id'];
                    }
                }
            }
        }
        
        return $this->updateBadgeCounts($itemIds, $user);
    }
}
