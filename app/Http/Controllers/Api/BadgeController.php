<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BadgeController extends Controller
{
    protected BadgeService $badgeService;

    public function __construct(BadgeService $badgeService)
    {
        $this->badgeService = $badgeService;
    }

    /**
     * Get badge count for a specific item.
     */
    public function getBadgeCount(Request $request, string $itemId): JsonResponse
    {
        $count = $this->badgeService->getBadgeCount($itemId);

        return response()->json([
            'success' => true,
            'data' => [
                'item_id' => $itemId,
                'count' => $count,
            ],
        ]);
    }

    /**
     * Get badge counts for multiple items.
     */
    public function getBadgeCounts(Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'string|max:64',
        ]);

        $itemIds = $request->get('item_ids');
        $counts = $this->badgeService->getBadgeCounts($itemIds);

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    /**
     * Update badge count for a specific item.
     */
    public function updateBadgeCount(Request $request, string $itemId): JsonResponse
    {
        $count = $this->badgeService->getBadgeCount($itemId);

        return response()->json([
            'success' => true,
            'data' => [
                'item_id' => $itemId,
                'count' => $count,
            ],
        ]);
    }

    /**
     * Update badge counts for multiple items.
     */
    public function updateBadgeCounts(Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'string|max:64',
        ]);

        $itemIds = $request->get('item_ids');
        $counts = $this->badgeService->updateBadgeCounts($itemIds);

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    /**
     * Clear badge cache for a specific item.
     */
    public function clearBadgeCache(Request $request, string $itemId): JsonResponse
    {
        $this->badgeService->clearBadgeCache($itemId);

        return response()->json([
            'success' => true,
            'message' => "Badge cache cleared for item: {$itemId}",
        ]);
    }

    /**
     * Clear all badge caches for current user.
     */
    public function clearUserBadgeCache(Request $request): JsonResponse
    {
        $this->badgeService->clearUserBadgeCache();

        return response()->json([
            'success' => true,
            'message' => 'All badge caches cleared for current user',
        ]);
    }

    /**
     * Get badge configuration for sidebar items.
     */
    public function getBadgeConfig(Request $request): JsonResponse
    {
        $request->validate([
            'sidebar_items' => 'required|array',
        ]);

        $sidebarItems = $request->get('sidebar_items');
        $badgeConfig = $this->badgeService->getBadgeConfig($sidebarItems);

        return response()->json([
            'success' => true,
            'data' => $badgeConfig,
        ]);
    }

    /**
     * Batch update badges for sidebar.
     */
    public function batchUpdateBadges(Request $request): JsonResponse
    {
        $request->validate([
            'sidebar_items' => 'required|array',
        ]);

        $sidebarItems = $request->get('sidebar_items');
        $badgeCounts = $this->badgeService->batchUpdateBadges($sidebarItems);

        return response()->json([
            'success' => true,
            'data' => $badgeCounts,
        ]);
    }
}
