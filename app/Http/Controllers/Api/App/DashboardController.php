<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $dashboardService;
    protected $cacheService;

    public function __construct(DashboardService $dashboardService, CacheService $cacheService)
    {
        $this->dashboardService = $dashboardService;
        $this->cacheService = $cacheService;
    }

    /**
     * Get dashboard data
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Get cached dashboard data
            $data = $this->cacheService->cacheDashboardData($tenantId, function () use ($user, $tenantId) {
                return $this->dashboardService->getDashboardData($user, $tenantId);
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard data fetch failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load dashboard data',
                'error_id' => 'DASHBOARD_LOAD_ERROR'
            ], 500);
        }
    }

    /**
     * Get real-time dashboard updates
     */
    public function updates(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Get incremental updates since last check
            $lastUpdate = $request->get('last_update', now()->subMinutes(5)->toISOString());
            
            $updates = $this->dashboardService->getIncrementalUpdates($user, $tenantId, $lastUpdate);

            return response()->json([
                'status' => 'success',
                'data' => $updates,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard updates fetch failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load updates',
                'error_id' => 'DASHBOARD_UPDATES_ERROR'
            ], 500);
        }
    }

    /**
     * Get KPI data
     */
    public function kpis(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $kpis = $this->cacheService->cacheKPIs($tenantId, function () use ($user, $tenantId) {
                return $this->dashboardService->getKPIData($user, $tenantId);
            });

            return response()->json([
                'status' => 'success',
                'data' => $kpis,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('KPI data fetch failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load KPI data',
                'error_id' => 'KPI_LOAD_ERROR'
            ], 500);
        }
    }

    /**
     * Get activity feed
     */
    public function activity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);

            $activity = $this->dashboardService->getActivityFeed($user, $tenantId, $limit, $offset);

            return response()->json([
                'status' => 'success',
                'data' => $activity,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Activity feed fetch failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load activity feed',
                'error_id' => 'ACTIVITY_LOAD_ERROR'
            ], 500);
        }
    }

    /**
     * Get alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $alerts = $this->dashboardService->getAlerts($user, $tenantId);

            return response()->json([
                'status' => 'success',
                'data' => $alerts,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Alerts fetch failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load alerts',
                'error_id' => 'ALERTS_LOAD_ERROR'
            ], 500);
        }
    }

    /**
     * Dismiss alert
     */
    public function dismissAlert(Request $request, string $alertId): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $result = $this->dashboardService->dismissAlert($user, $tenantId, $alertId);

            if ($result) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Alert dismissed successfully'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to dismiss alert',
                    'error_id' => 'ALERT_DISMISS_ERROR'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Alert dismiss failed', [
                'user_id' => Auth::id(),
                'alert_id' => $alertId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to dismiss alert',
                'error_id' => 'ALERT_DISMISS_ERROR'
            ], 500);
        }
    }

    /**
     * Get dashboard widgets configuration
     */
    public function widgets(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $widgets = $this->dashboardService->getUserWidgets($user, $tenantId);

            return response()->json([
                'status' => 'success',
                'data' => $widgets,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Widgets fetch failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load widgets',
                'error_id' => 'WIDGETS_LOAD_ERROR'
            ], 500);
        }
    }

    /**
     * Update dashboard layout
     */
    public function updateLayout(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $validated = $request->validate([
                'layout' => 'required|array',
                'widgets' => 'required|array'
            ]);

            $result = $this->dashboardService->updateUserLayout($user, $tenantId, $validated);

            if ($result) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Dashboard layout updated successfully'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update layout',
                    'error_id' => 'LAYOUT_UPDATE_ERROR'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Layout update failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update layout',
                'error_id' => 'LAYOUT_UPDATE_ERROR'
            ], 500);
        }
    }
}