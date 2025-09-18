<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserSidebarPreference;
use Illuminate\Support\Facades\Cache;

class UserPreferenceService
{
    /**
     * Get user sidebar preferences.
     */
    public function getUserPreferences(User $user): array
    {
        $cacheKey = "user_preferences_{$user->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($user) {
            $preference = UserSidebarPreference::where('user_id', $user->id)
                ->where('is_enabled', true)
                ->first();
            
            if (!$preference) {
                return UserSidebarPreference::getDefaultPreferences();
            }
            
            return $preference->preferences ?? UserSidebarPreference::getDefaultPreferences();
        });
    }

    /**
     * Update user sidebar preferences.
     */
    public function updateUserPreferences(User $user, array $preferences): UserSidebarPreference
    {
        $preference = UserSidebarPreference::where('user_id', $user->id)
            ->where('is_enabled', true)
            ->first();
        
        if (!$preference) {
            $preference = UserSidebarPreference::create([
                'user_id' => $user->id,
                'preferences' => $preferences,
                'is_enabled' => true,
                'version' => 1,
            ]);
        } else {
            $preference->update(['preferences' => $preferences]);
        }
        
        // Clear cache
        $this->clearUserPreferencesCache($user);
        
        return $preference;
    }

    /**
     * Pin an item for user.
     */
    public function pinItem(User $user, string $itemId): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->pinItem($itemId);
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Unpin an item for user.
     */
    public function unpinItem(User $user, string $itemId): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->unpinItem($itemId);
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Hide an item for user.
     */
    public function hideItem(User $user, string $itemId): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->hideItem($itemId);
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Show an item for user.
     */
    public function showItem(User $user, string $itemId): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->showItem($itemId);
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Set custom order for user.
     */
    public function setCustomOrder(User $user, array $itemIds): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->setCustomOrder($itemIds);
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Update theme preference for user.
     */
    public function setTheme(User $user, string $theme): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->setTheme($theme);
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Toggle compact mode for user.
     */
    public function toggleCompactMode(User $user): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->toggleCompactMode();
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Toggle badges display for user.
     */
    public function toggleBadges(User $user): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->toggleBadges();
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Toggle auto expand groups for user.
     */
    public function toggleAutoExpandGroups(User $user): void
    {
        $preference = $this->getOrCreatePreference($user);
        $preference->toggleAutoExpandGroups();
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Apply user preferences to sidebar config.
     */
    public function applyUserPreferences(array $sidebarConfig, User $user): array
    {
        $preferences = $this->getUserPreferences($user);
        
        if (empty($preferences['pinned_items']) && 
            empty($preferences['hidden_items']) && 
            empty($preferences['custom_order'])) {
            return $sidebarConfig;
        }
        
        $items = $sidebarConfig['items'] ?? [];
        
        // Apply custom order
        if (!empty($preferences['custom_order'])) {
            $items = $this->applyCustomOrder($items, $preferences['custom_order']);
        }
        
        // Apply pinned items (move to top)
        if (!empty($preferences['pinned_items'])) {
            $items = $this->applyPinnedItems($items, $preferences['pinned_items']);
        }
        
        // Apply hidden items (remove from display)
        if (!empty($preferences['hidden_items'])) {
            $items = $this->applyHiddenItems($items, $preferences['hidden_items']);
        }
        
        $sidebarConfig['items'] = $items;
        
        // Apply theme and display preferences
        $sidebarConfig['user_preferences'] = [
            'theme' => $preferences['theme'] ?? 'auto',
            'compact_mode' => $preferences['compact_mode'] ?? false,
            'show_badges' => $preferences['show_badges'] ?? true,
            'auto_expand_groups' => $preferences['auto_expand_groups'] ?? false,
        ];
        
        return $sidebarConfig;
    }

    /**
     * Apply custom order to items.
     */
    protected function applyCustomOrder(array $items, array $customOrder): array
    {
        $orderedItems = [];
        $remainingItems = $items;
        
        // Add items in custom order
        foreach ($customOrder as $itemId) {
            foreach ($remainingItems as $index => $item) {
                if ($item['id'] === $itemId) {
                    $orderedItems[] = $item;
                    unset($remainingItems[$index]);
                    break;
                }
            }
        }
        
        // Add remaining items
        $orderedItems = array_merge($orderedItems, array_values($remainingItems));
        
        return $orderedItems;
    }

    /**
     * Apply pinned items (move to top).
     */
    protected function applyPinnedItems(array $items, array $pinnedItems): array
    {
        $pinned = [];
        $unpinned = [];
        
        foreach ($items as $item) {
            if (in_array($item['id'], $pinnedItems)) {
                $pinned[] = $item;
            } else {
                $unpinned[] = $item;
            }
        }
        
        return array_merge($pinned, $unpinned);
    }

    /**
     * Apply hidden items (remove from display).
     */
    protected function applyHiddenItems(array $items, array $hiddenItems): array
    {
        return array_filter($items, function ($item) use ($hiddenItems) {
            return !in_array($item['id'], $hiddenItems);
        });
    }

    /**
     * Get or create user preference.
     */
    protected function getOrCreatePreference(User $user): UserSidebarPreference
    {
        $preference = UserSidebarPreference::where('user_id', $user->id)
            ->where('is_enabled', true)
            ->first();
        
        if (!$preference) {
            $preference = UserSidebarPreference::create([
                'user_id' => $user->id,
                'preferences' => UserSidebarPreference::getDefaultPreferences(),
                'is_enabled' => true,
                'version' => 1,
            ]);
        }
        
        return $preference;
    }

    /**
     * Clear user preferences cache.
     */
    public function clearUserPreferencesCache(User $user): void
    {
        $cacheKey = "user_preferences_{$user->id}";
        Cache::forget($cacheKey);
    }

    /**
     * Reset user preferences to default.
     */
    public function resetUserPreferences(User $user): void
    {
        $preference = UserSidebarPreference::where('user_id', $user->id)
            ->where('is_enabled', true)
            ->first();
        
        if ($preference) {
            $preference->resetToDefault();
        }
        
        $this->clearUserPreferencesCache($user);
    }

    /**
     * Get user preference statistics.
     */
    public function getUserPreferenceStats(User $user): array
    {
        $preferences = $this->getUserPreferences($user);
        
        return [
            'pinned_items_count' => count($preferences['pinned_items'] ?? []),
            'hidden_items_count' => count($preferences['hidden_items'] ?? []),
            'custom_order_count' => count($preferences['custom_order'] ?? []),
            'theme' => $preferences['theme'] ?? 'auto',
            'compact_mode' => $preferences['compact_mode'] ?? false,
            'show_badges' => $preferences['show_badges'] ?? true,
            'auto_expand_groups' => $preferences['auto_expand_groups'] ?? false,
        ];
    }

    /**
     * Bulk update user preferences.
     */
    public function bulkUpdatePreferences(User $user, array $updates): void
    {
        $preferences = $this->getUserPreferences($user);
        
        foreach ($updates as $key => $value) {
            if (array_key_exists($key, $preferences)) {
                $preferences[$key] = $value;
            }
        }
        
        $this->updateUserPreferences($user, $preferences);
    }
}
