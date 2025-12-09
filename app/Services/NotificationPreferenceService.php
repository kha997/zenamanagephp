<?php declare(strict_types=1);

namespace App\Services;

use App\Models\UserNotificationPreference;
use Illuminate\Support\Facades\Log;

/**
 * NotificationPreferenceService - Round 255: Notification Preferences
 * 
 * Service for managing user notification preferences.
 * 
 * Behavior:
 * - If no preference row exists for a type → considered enabled (default)
 * - Preference rows only store deviations (e.g. user turned OFF a type)
 * - Per-tenant, per-user, per-type isolation
 * 
 * Note: All preference checks read directly from the database to ensure
 * immediate effect of preference changes in long-lived processes (queue workers, scheduler).
 */
class NotificationPreferenceService
{

    /**
     * Check if a notification type is enabled for a user
     * 
     * Reads directly from the database to ensure immediate effect of preference changes.
     * 
     * @param string $tenantId Tenant ID
     * @param string $userId User ID
     * @param string $type Notification type (e.g., 'task.assigned')
     * @return bool True if enabled, false if disabled
     */
    public function isTypeEnabledForUser(string $tenantId, string $userId, string $type): bool
    {
        // Query preference directly from database (no cache)
        $preference = UserNotificationPreference::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->first();

        // If no preference row exists → default to enabled
        return $preference ? $preference->is_enabled : true;
    }

    /**
     * Get all preferences for a user
     * 
     * Returns an array of {type, is_enabled} for all known notification types.
     * If no preference row exists for a type, is_enabled = true (default).
     * 
     * @param string $tenantId Tenant ID
     * @param string $userId User ID
     * @return array<int, array{type: string, is_enabled: bool}>
     */
    public function getPreferencesForUser(string $tenantId, string $userId): array
    {
        $knownTypes = config('notification_types', []);
        
        if (empty($knownTypes)) {
            Log::warning('NotificationPreferenceService: notification_types config is empty', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
            ]);
            return [];
        }

        // Fetch all preferences for this user in one query
        $preferences = UserNotificationPreference::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereIn('type', $knownTypes)
            ->pluck('is_enabled', 'type')
            ->toArray();

        // Build result array with defaults
        $result = [];
        foreach ($knownTypes as $type) {
            $result[] = [
                'type' => $type,
                'is_enabled' => $preferences[$type] ?? true, // Default to true if no row
            ];
        }

        return $result;
    }

    /**
     * Update preferences for a user
     * 
     * Upserts preference rows for the given types.
     * 
     * @param string $tenantId Tenant ID
     * @param string $userId User ID
     * @param array<int, array{type: string, is_enabled: bool}> $preferences Array of {type, is_enabled}
     * @return void
     */
    public function updatePreferencesForUser(string $tenantId, string $userId, array $preferences): void
    {
        $knownTypes = config('notification_types', []);

        foreach ($preferences as $pref) {
            $type = $pref['type'] ?? null;
            $isEnabled = $pref['is_enabled'] ?? true;

            if (!$type) {
                continue;
            }

            // Validate type is in known types
            if (!in_array($type, $knownTypes, true)) {
                Log::warning('NotificationPreferenceService: Attempted to set preference for unknown type', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => $type,
                ]);
                continue;
            }

            // Upsert preference
            UserNotificationPreference::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => $type,
                ],
                [
                    'is_enabled' => $isEnabled,
                ]
            );
        }
    }
}
