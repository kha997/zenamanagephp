<?php

namespace App\Http\Controllers;

use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    protected $activityService;
    
    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }
    
    /**
     * Get recent activities for the current user/tenant
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $activities = $this->activityService->getRecentActivities($limit);
            
            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'activities_' . uniqid(),
                    'code' => 'E500.ACTIVITIES_FETCH_ERROR',
                    'message' => 'Failed to fetch activities',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Log a new activity
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'description' => 'required|string|max:255',
                'icon' => 'nullable|string|max:50',
                'url' => 'nullable|string|max:255'
            ]);
            
            $activityData = [
                'description' => $request->description,
                'icon' => $request->icon ?? 'fas fa-circle',
                'url' => $request->url
            ];
            
            $success = $this->activityService->logActivity($activityData);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Activity logged successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'activity_create_' . uniqid(),
                        'code' => 'E500.ACTIVITY_CREATE_ERROR',
                        'message' => 'Failed to log activity',
                        'details' => []
                    ]
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'activity_create_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'activity_create_' . uniqid(),
                    'code' => 'E500.ACTIVITY_CREATE_ERROR',
                    'message' => 'Failed to log activity',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get activities by type
     */
    public function byType(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|max:50'
            ]);
            
            $activities = $this->activityService->getActivitiesByType($request->type);
            
            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'activity_type_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'activity_type_' . uniqid(),
                    'code' => 'E500.ACTIVITY_TYPE_ERROR',
                    'message' => 'Failed to fetch activities by type',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get activity statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->activityService->getActivityStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'activity_stats_' . uniqid(),
                    'code' => 'E500.ACTIVITY_STATS_ERROR',
                    'message' => 'Failed to fetch activity statistics',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Clear old activities
     */
    public function clearOld(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'days_old' => 'integer|min:1|max:365'
            ]);
            
            $daysOld = $request->days_old ?? 30;
            $success = $this->activityService->clearOldActivities($daysOld);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => "Activities older than {$daysOld} days cleared successfully"
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'activity_clear_' . uniqid(),
                        'code' => 'E500.ACTIVITY_CLEAR_ERROR',
                        'message' => 'Failed to clear old activities',
                        'details' => []
                    ]
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'activity_clear_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'activity_clear_' . uniqid(),
                    'code' => 'E500.ACTIVITY_CLEAR_ERROR',
                    'message' => 'Failed to clear old activities',
                    'details' => []
                ]
            ], 500);
        }
    }
}
