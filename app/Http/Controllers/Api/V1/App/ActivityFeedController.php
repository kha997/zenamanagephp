<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\ActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ActivityFeedController
 * 
 * Round 248: Global Activity / My Work Feed
 * 
 * Provides activity feed endpoint for authenticated users.
 */
class ActivityFeedController extends BaseApiV1Controller
{
    private ActivityFeedService $activityFeedService;

    public function __construct(ActivityFeedService $activityFeedService)
    {
        $this->activityFeedService = $activityFeedService;
    }

    /**
     * Get activity feed for current user
     * 
     * GET /api/v1/app/activity-feed
     * 
     * Query params:
     * - page: int (default: 1)
     * - per_page: int (default: 20)
     * - module: 'all' | 'tasks' | 'documents' | 'cost' | 'rbac' (default: 'all')
     * - from: ISO datetime string (optional)
     * - to: ISO datetime string (optional)
     * - search: string (optional)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        // Get query parameters
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $module = $request->input('module', 'all');
        $from = $request->input('from');
        $to = $request->input('to');
        $search = $request->input('search');

        // Validate module
        $validModules = ['all', 'tasks', 'documents', 'cost', 'rbac'];
        if (!in_array($module, $validModules)) {
            return $this->errorResponse('Invalid module. Must be one of: ' . implode(', ', $validModules), 400);
        }

        try {
            $paginator = $this->activityFeedService->getFeedForUser(
                $user,
                $module,
                $from,
                $to,
                $search,
                $page,
                $perPage
            );

            return $this->paginatedResponse(
                ['items' => $paginator->items()],
                [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
                'Activity feed retrieved successfully'
            );
        } catch (\Exception $e) {
            \Log::error('Activity feed error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to retrieve activity feed', 500);
        }
    }
}
