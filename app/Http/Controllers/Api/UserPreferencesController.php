<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HeaderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * User Preferences Controller
 * 
 * Handles user preference updates including theme
 */
class UserPreferencesController extends Controller
{
    protected HeaderService $headerService;

    public function __construct(HeaderService $headerService)
    {
        $this->headerService = $headerService;
    }

    /**
     * Update user theme preference
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'theme' => ['required', 'string', 'in:light,dark']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid theme value',
                'details' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated'
            ], 401);
        }

        try {
            $theme = $request->input('theme');
            $this->headerService->setUserTheme($user, $theme);

            return response()->json([
                'success' => true,
                'message' => 'Theme updated successfully',
                'data' => [
                    'theme' => $theme
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update theme',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user preferences
     */
    public function getPreferences(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated'
            ], 401);
        }

        try {
            $preferences = [
                'theme' => $this->headerService->getUserTheme($user),
                'notifications' => $this->headerService->getNotifications($user)->toArray(),
                'unread_count' => $this->headerService->getUnreadCount($user)
            ];

            return response()->json([
                'success' => true,
                'data' => $preferences
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get preferences',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update multiple preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'theme' => ['sometimes', 'string', 'in:light,dark'],
            'notifications' => ['sometimes', 'array'],
            'notifications.*.read' => ['sometimes', 'boolean']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid preferences data',
                'details' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated'
            ], 401);
        }

        try {
            $updated = [];

            if ($request->has('theme')) {
                $theme = $request->input('theme');
                $this->headerService->setUserTheme($user, $theme);
                $updated['theme'] = $theme;
            }

            if ($request->has('notifications')) {
                // Handle notification updates
                $notifications = $request->input('notifications');
                // This would typically update notification read status in database
                $updated['notifications'] = $notifications;
            }

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update preferences',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
