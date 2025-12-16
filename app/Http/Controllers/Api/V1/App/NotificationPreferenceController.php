<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * NotificationPreferenceController - Round 255: Notification Preferences
 * 
 * API endpoints for current user to manage their notification preferences:
 * - GET /api/v1/app/notification-preferences (get all preferences)
 * - PUT /api/v1/app/notification-preferences (update preferences)
 */
class NotificationPreferenceController extends BaseApiV1Controller
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}

    /**
     * Get notification preferences for current user
     * 
     * Returns all known notification types with their effective enabled status.
     * If no preference row exists for a type, it defaults to enabled (true).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $tenantId = $this->getTenantId();

            $preferences = $this->notificationPreferenceService->getPreferencesForUser($tenantId, $user->id);

            return $this->successResponse(
                ['preferences' => $preferences],
                'Notification preferences retrieved successfully'
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index']);
            return $this->errorResponse('Failed to retrieve notification preferences', 500);
        }
    }

    /**
     * Update notification preferences for current user
     * 
     * Request body:
     * {
     *   "preferences": [
     *     { "type": "task.due_soon", "is_enabled": false },
     *     { "type": "task.overdue", "is_enabled": false }
     *   ]
     * }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }

            $tenantId = $this->getTenantId();

            // Validate request
            $validator = Validator::make($request->all(), [
                'preferences' => 'required|array',
                'preferences.*.type' => 'required|string',
                'preferences.*.is_enabled' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    422,
                    $validator->errors()->toArray(),
                    'VALIDATION_FAILED'
                );
            }

            $preferences = $request->input('preferences', []);

            // Validate that all types are in the known types list
            $knownTypes = config('notification_types', []);
            foreach ($preferences as $pref) {
                $type = $pref['type'] ?? null;
                if ($type && !in_array($type, $knownTypes, true)) {
                    return $this->errorResponse(
                        "Unknown notification type: {$type}",
                        422,
                        ['type' => $type],
                        'UNKNOWN_NOTIFICATION_TYPE'
                    );
                }
            }

            // Update preferences
            $this->notificationPreferenceService->updatePreferencesForUser($tenantId, $user->id, $preferences);

            // Return updated preferences
            $updatedPreferences = $this->notificationPreferenceService->getPreferencesForUser($tenantId, $user->id);

            return $this->successResponse(
                ['preferences' => $updatedPreferences],
                'Notification preferences updated successfully'
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update']);
            return $this->errorResponse('Failed to update notification preferences', 500);
        }
    }
}
