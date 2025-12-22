<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FeatureFlagService;
use App\Models\UserPreference;
use App\Support\ApiResponse;

class FocusModeController extends Controller
{
    protected FeatureFlagService $featureFlagService;

    public function __construct(FeatureFlagService $featureFlagService)
    {
        $this->featureFlagService = $featureFlagService;
    }

    /**
     * Toggle focus mode for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggle(Request $request): JsonResponse
    {
        // Check if focus mode feature is enabled globally
        if (!$this->featureFlagService->isEnabled('ui.enable_focus_mode')) {
            return ApiResponse::error(
                ['message' => 'Focus Mode feature is not enabled'],
                403,
                'FEATURE_DISABLED'
            )->toResponse($request);
        }

        $user = $request->user();
        if (!$user) {
            return ApiResponse::error(
                ['message' => 'User not authenticated'],
                401,
                'UNAUTHENTICATED'
            )->toResponse($request);
        }

        // Get current focus mode state
        $preference = UserPreference::where('user_id', $user->id)->first();
        $currentState = $preference ? $preference->isFocusModeEnabled() : false;
        
        // Toggle the state
        $newState = !$currentState;
        
        // Update user preference
        if (!$preference) {
            $preference = UserPreference::create([
                'user_id' => $user->id,
                'preferences' => []
            ]);
        }
        
        $preference->setFocusMode($newState);

        // Clear feature flag cache
        $this->featureFlagService->clearCache('ui.enable_focus_mode', null, $user->id);

        return ApiResponse::success([
            'focus_mode_enabled' => $newState,
            'message' => $newState ? 'Focus Mode enabled' : 'Focus Mode disabled'
        ])->toResponse($request);
    }

    /**
     * Get current focus mode state
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error(
                ['message' => 'User not authenticated'],
                401,
                'UNAUTHENTICATED'
            )->toResponse($request);
        }

        // Check if feature is enabled globally
        $featureEnabled = $this->featureFlagService->isEnabled('ui.enable_focus_mode');
        
        // Get user's current preference
        $preference = UserPreference::where('user_id', $user->id)->first();
        $userEnabled = $preference ? $preference->isFocusModeEnabled() : false;

        return ApiResponse::success([
            'feature_enabled' => $featureEnabled,
            'user_enabled' => $userEnabled,
            'focus_mode_active' => $featureEnabled && $userEnabled
        ])->toResponse($request);
    }

    /**
     * Set focus mode state explicitly
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setState(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'required|boolean'
        ]);

        // Check if focus mode feature is enabled globally
        if (!$this->featureFlagService->isEnabled('ui.enable_focus_mode')) {
            return ApiResponse::error(
                ['message' => 'Focus Mode feature is not enabled'],
                403,
                'FEATURE_DISABLED'
            )->toResponse($request);
        }

        $user = $request->user();
        if (!$user) {
            return ApiResponse::error(
                ['message' => 'User not authenticated'],
                401,
                'UNAUTHENTICATED'
            )->toResponse($request);
        }

        $enabled = $request->boolean('enabled');

        // Update user preference
        $preference = UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['preferences' => []]
        );
        
        $preference->setFocusMode($enabled);

        // Clear feature flag cache
        $this->featureFlagService->clearCache('ui.enable_focus_mode', null, $user->id);

        return ApiResponse::success([
            'focus_mode_enabled' => $enabled,
            'message' => $enabled ? 'Focus Mode enabled' : 'Focus Mode disabled'
        ])->toResponse($request);
    }

    /**
     * Get focus mode configuration
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function config(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error(
                ['message' => 'User not authenticated'],
                401,
                'UNAUTHENTICATED'
            )->toResponse($request);
        }

        // Check if feature is enabled globally
        $featureEnabled = $this->featureFlagService->isEnabled('ui.enable_focus_mode');
        
        if (!$featureEnabled) {
            return ApiResponse::success([
                'feature_enabled' => false,
                'config' => null
            ])->toResponse($request);
        }

        // Get user's current preference
        $preference = UserPreference::where('user_id', $user->id)->first();
        $userEnabled = $preference ? $preference->isFocusModeEnabled() : false;

        return ApiResponse::success([
            'feature_enabled' => true,
            'user_enabled' => $userEnabled,
            'config' => [
                'sidebar_collapsed' => $userEnabled,
                'hide_secondary_kpis' => $userEnabled,
                'minimal_theme' => $userEnabled,
                'show_main_content_only' => $userEnabled,
                'theme_class' => $userEnabled ? 'focus-mode' : 'normal-mode'
            ]
        ])->toResponse($request);
    }
}
