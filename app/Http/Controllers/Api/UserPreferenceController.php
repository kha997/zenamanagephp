<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
    protected UserPreferenceService $userPreferenceService;

    public function __construct(UserPreferenceService $userPreferenceService)
    {
        $this->userPreferenceService = $userPreferenceService;
    }

    /**
     * Get user sidebar preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $user = Auth::user();
        $preferences = $this->userPreferenceService->getUserPreferences($user);

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ]);
    }

    /**
     * Update user sidebar preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'pinned_items' => 'array',
            'pinned_items.*' => 'string',
            'hidden_items' => 'array',
            'hidden_items.*' => 'string',
            'custom_order' => 'array',
            'custom_order.*' => 'string',
            'theme' => 'string|in:light,dark,auto',
            'compact_mode' => 'boolean',
            'show_badges' => 'boolean',
            'auto_expand_groups' => 'boolean',
        ]);

        $preference = $this->userPreferenceService->updateUserPreferences($user, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $preference->preferences,
        ]);
    }

    /**
     * Pin an item.
     */
    public function pinItem(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'item_id' => 'required|string',
        ]);

        $this->userPreferenceService->pinItem($user, $validated['item_id']);

        return response()->json([
            'success' => true,
            'message' => 'Item pinned successfully',
        ]);
    }

    /**
     * Unpin an item.
     */
    public function unpinItem(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'item_id' => 'required|string',
        ]);

        $this->userPreferenceService->unpinItem($user, $validated['item_id']);

        return response()->json([
            'success' => true,
            'message' => 'Item unpinned successfully',
        ]);
    }

    /**
     * Hide an item.
     */
    public function hideItem(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'item_id' => 'required|string',
        ]);

        $this->userPreferenceService->hideItem($user, $validated['item_id']);

        return response()->json([
            'success' => true,
            'message' => 'Item hidden successfully',
        ]);
    }

    /**
     * Show an item.
     */
    public function showItem(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'item_id' => 'required|string',
        ]);

        $this->userPreferenceService->showItem($user, $validated['item_id']);

        return response()->json([
            'success' => true,
            'message' => 'Item shown successfully',
        ]);
    }

    /**
     * Set custom order.
     */
    public function setCustomOrder(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'string',
        ]);

        $this->userPreferenceService->setCustomOrder($user, $validated['item_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Custom order set successfully',
        ]);
    }

    /**
     * Update theme.
     */
    public function setTheme(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'theme' => 'required|string|in:light,dark,auto',
        ]);

        $this->userPreferenceService->setTheme($user, $validated['theme']);

        return response()->json([
            'success' => true,
            'message' => 'Theme updated successfully',
        ]);
    }

    /**
     * Toggle compact mode.
     */
    public function toggleCompactMode(): JsonResponse
    {
        $user = Auth::user();
        $this->userPreferenceService->toggleCompactMode($user);

        return response()->json([
            'success' => true,
            'message' => 'Compact mode toggled successfully',
        ]);
    }

    /**
     * Toggle badges display.
     */
    public function toggleBadges(): JsonResponse
    {
        $user = Auth::user();
        $this->userPreferenceService->toggleBadges($user);

        return response()->json([
            'success' => true,
            'message' => 'Badges display toggled successfully',
        ]);
    }

    /**
     * Toggle auto expand groups.
     */
    public function toggleAutoExpandGroups(): JsonResponse
    {
        $user = Auth::user();
        $this->userPreferenceService->toggleAutoExpandGroups($user);

        return response()->json([
            'success' => true,
            'message' => 'Auto expand groups toggled successfully',
        ]);
    }

    /**
     * Reset preferences to default.
     */
    public function resetPreferences(): JsonResponse
    {
        $user = Auth::user();
        $this->userPreferenceService->resetUserPreferences($user);

        return response()->json([
            'success' => true,
            'message' => 'Preferences reset to default successfully',
        ]);
    }

    /**
     * Get preference statistics.
     */
    public function getStats(): JsonResponse
    {
        $user = Auth::user();
        $stats = $this->userPreferenceService->getUserPreferenceStats($user);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Bulk update preferences.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.pinned_items' => 'array',
            'updates.pinned_items.*' => 'string',
            'updates.hidden_items' => 'array',
            'updates.hidden_items.*' => 'string',
            'updates.custom_order' => 'array',
            'updates.custom_order.*' => 'string',
            'updates.theme' => 'string|in:light,dark,auto',
            'updates.compact_mode' => 'boolean',
            'updates.show_badges' => 'boolean',
            'updates.auto_expand_groups' => 'boolean',
        ]);

        $this->userPreferenceService->bulkUpdatePreferences($user, $validated['updates']);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
        ]);
    }
}