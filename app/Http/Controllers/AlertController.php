<?php

namespace App\Http\Controllers;

use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    protected $alertService;
    
    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }
    
    /**
     * Get alerts for the current user/tenant
     */
    public function index(): JsonResponse
    {
        try {
            $alerts = $this->alertService->getAlerts();
            
            return response()->json([
                'success' => true,
                'data' => $alerts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alerts_' . uniqid(),
                    'code' => 'E500.ALERTS_FETCH_ERROR',
                    'message' => 'Failed to fetch alerts',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Resolve an alert
     */
    public function resolve(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'alert_id' => 'required|integer'
            ]);
            
            $success = $this->alertService->resolveAlert($request->alert_id);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Alert resolved successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'alert_resolve_' . uniqid(),
                        'code' => 'E404.ALERT_NOT_FOUND',
                        'message' => 'Alert not found',
                        'details' => []
                    ]
                ], 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_resolve_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_resolve_' . uniqid(),
                    'code' => 'E500.ALERT_RESOLVE_ERROR',
                    'message' => 'Failed to resolve alert',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Acknowledge an alert
     */
    public function acknowledge(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'alert_id' => 'required|integer'
            ]);
            
            $success = $this->alertService->acknowledgeAlert($request->alert_id);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Alert acknowledged successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'alert_ack_' . uniqid(),
                        'code' => 'E404.ALERT_NOT_FOUND',
                        'message' => 'Alert not found',
                        'details' => []
                    ]
                ], 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_ack_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_ack_' . uniqid(),
                    'code' => 'E500.ALERT_ACK_ERROR',
                    'message' => 'Failed to acknowledge alert',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Mute an alert
     */
    public function mute(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'alert_id' => 'required|integer',
                'duration_minutes' => 'integer|min:1|max:1440' // Max 24 hours
            ]);
            
            $duration = $request->duration_minutes ?? 60;
            $success = $this->alertService->muteAlert($request->alert_id, $duration);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Alert muted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'alert_mute_' . uniqid(),
                        'code' => 'E404.ALERT_NOT_FOUND',
                        'message' => 'Alert not found',
                        'details' => []
                    ]
                ], 404);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_mute_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_mute_' . uniqid(),
                    'code' => 'E500.ALERT_MUTE_ERROR',
                    'message' => 'Failed to mute alert',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Dismiss all alerts
     */
    public function dismissAll(): JsonResponse
    {
        try {
            $success = $this->alertService->dismissAllAlerts();
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'All alerts dismissed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'alert_dismiss_all_' . uniqid(),
                        'code' => 'E500.ALERT_DISMISS_ERROR',
                        'message' => 'Failed to dismiss alerts',
                        'details' => []
                    ]
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_dismiss_all_' . uniqid(),
                    'code' => 'E500.ALERT_DISMISS_ERROR',
                    'message' => 'Failed to dismiss alerts',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Create a new alert
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'level' => 'required|string|in:critical,high,medium,low',
                'message' => 'required|string|max:255',
                'url' => 'nullable|string|max:255'
            ]);
            
            $alertData = [
                'level' => $request->level,
                'message' => $request->message,
                'url' => $request->url,
                'action' => 'resolve'
            ];
            
            $success = $this->alertService->createAlert($alertData);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Alert created successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'id' => 'alert_create_' . uniqid(),
                        'code' => 'E500.ALERT_CREATE_ERROR',
                        'message' => 'Failed to create alert',
                        'details' => []
                    ]
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_create_validation_' . uniqid(),
                    'code' => 'E422.VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_create_' . uniqid(),
                    'code' => 'E500.ALERT_CREATE_ERROR',
                    'message' => 'Failed to create alert',
                    'details' => []
                ]
            ], 500);
        }
    }
    
    /**
     * Get alert statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->alertService->getAlertStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'id' => 'alert_stats_' . uniqid(),
                    'code' => 'E500.ALERT_STATS_ERROR',
                    'message' => 'Failed to fetch alert statistics',
                    'details' => []
                ]
            ], 500);
        }
    }
}
