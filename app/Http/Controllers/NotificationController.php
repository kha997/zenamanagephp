<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\NotificationRuleFormRequest;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\NotificationRuleResource;
use App\Services\NotificationService;
use App\Services\NotificationRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing user notifications and notification rules
 * Handles CRUD operations for notifications and user preferences
 */
class NotificationController extends Controller
{
    protected NotificationService $notificationService;
    protected NotificationRuleService $ruleService;
    
    public function __construct(
        NotificationService $notificationService,
        NotificationRuleService $ruleService
    ) {
        $this->notificationService = $notificationService;
        $this->ruleService = $ruleService;
    }
    
    /**
     * Get user's unread notifications
     *
     * @param Request $request HTTP request
     * @return JsonResponse JSON response with notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->get('limit', 50), 100);
            $userId = Auth::id();
            
            $notifications = $this->notificationService->getUnreadNotifications($userId, $limit);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'notifications' => NotificationResource::collection($notifications),
                    'total_unread' => $notifications->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve notifications.'
            ], 500);
        }
    }
    
    /**
     * Mark notification as read
     *
     * @param int $id Notification ID
     * @return JsonResponse JSON response
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            $success = $this->notificationService->markAsRead($id, $userId);
            
            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => ['message' => 'Notification marked as read.']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark notification as read.'
            ], 500);
        }
    }
    
    /**
     * Get user's notification rules
     *
     * @param Request $request HTTP request
     * @return JsonResponse JSON response with rules
     */
    public function rules(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $projectId = $request->get('project_id') ? (int) $request->get('project_id') : null;
            
            $rules = $this->ruleService->getUserRules($userId, $projectId);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'rules' => NotificationRuleResource::collection($rules),
                    'available_events' => $this->ruleService->getAvailableEventKeys()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve notification rules.'
            ], 500);
        }
    }
    
    /**
     * Create new notification rule
     *
     * @param NotificationRuleFormRequest $request Validated request
     * @return JsonResponse JSON response with created rule
     */
    public function createRule(NotificationRuleFormRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $rule = $this->ruleService->createRule($userId, $request->validated());
            
            return response()->json([
                'status' => 'success',
                'data' => ['rule' => new NotificationRuleResource($rule)]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create notification rule.'
            ], 500);
        }
    }
    
    /**
     * Update notification rule
     *
     * @param int $id Rule ID
     * @param NotificationRuleFormRequest $request Validated request
     * @return JsonResponse JSON response with updated rule
     */
    public function updateRule(int $id, NotificationRuleFormRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $rule = $this->ruleService->updateRule($id, $userId, $request->validated());
            
            return response()->json([
                'status' => 'success',
                'data' => ['rule' => new NotificationRuleResource($rule)]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update notification rule.'
            ], 500);
        }
    }
    
    /**
     * Toggle notification rule enabled status
     *
     * @param int $id Rule ID
     * @return JsonResponse JSON response with updated rule
     */
    public function toggleRule(int $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            $rule = $this->ruleService->toggleRule($id, $userId);
            
            return response()->json([
                'status' => 'success',
                'data' => ['rule' => new NotificationRuleResource($rule)]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle notification rule.'
            ], 500);
        }
    }
    
    /**
     * Delete notification rule
     *
     * @param int $id Rule ID
     * @return JsonResponse JSON response
     */
    public function deleteRule(int $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            $success = $this->ruleService->deleteRule($id, $userId);
            
            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification rule not found.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => ['message' => 'Notification rule deleted successfully.']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete notification rule.'
            ], 500);
        }
    }
}