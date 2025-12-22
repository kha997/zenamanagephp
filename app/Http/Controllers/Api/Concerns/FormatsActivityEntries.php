<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;

/**
 * Trait for formatting activity entries consistently across controllers
 * 
 * Round 32: Activity & Permission Hardening
 * 
 * Provides a shared method to format activity entries with consistent structure
 * without changing the JSON shape of existing endpoints.
 */
trait FormatsActivityEntries
{
    /**
     * Format an activity entry with consistent structure
     * 
     * @param array $base Base data for the activity entry (id, type, action, description, timestamp)
     * @param User|null $user User instance or null (fallback to authenticated user)
     * @return array Formatted activity entry
     */
    protected function formatActivityEntry(array $base, ?User $user = null): array
    {
        // Use provided user or fallback to authenticated user
        $activityUser = $user ?? auth()->user();
        
        // Build the activity entry with all required keys
        return [
            'id' => $base['id'] ?? '',
            'type' => $base['type'] ?? '',
            'action' => $base['action'] ?? '',
            'description' => $base['description'] ?? '',
            'timestamp' => $base['timestamp'] ?? now()->toISOString(),
            'user' => [
                'id' => $activityUser->id ?? null,
                'name' => $activityUser->name ?? 'Unknown',
            ],
        ];
    }
}

