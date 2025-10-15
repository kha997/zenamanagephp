<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FeatureFlagService;
use App\Models\UserPreference;
use App\Support\ApiResponse;

class RewardsController extends Controller
{
    protected FeatureFlagService $featureFlagService;

    public function __construct(FeatureFlagService $featureFlagService)
    {
        $this->featureFlagService = $featureFlagService;
    }

    /**
     * Trigger rewards animation for task completion
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function triggerTaskCompletion(Request $request): JsonResponse
    {
        $request->validate([
            'task_id' => 'required|string',
            'task_title' => 'required|string',
            'completion_time' => 'nullable|date'
        ]);

        // Check if rewards feature is enabled globally
        if (!$this->featureFlagService->isEnabled('ui.enable_rewards')) {
            return ApiResponse::error(
                ['message' => 'Rewards feature is not enabled'],
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

        // Check if user has rewards enabled
        $preference = UserPreference::where('user_id', $user->id)->first();
        $userRewardsEnabled = $preference ? $preference->isRewardsEnabled() : true; // Default to true if no preference

        if (!$userRewardsEnabled) {
            return ApiResponse::success([
                'rewards_triggered' => false,
                'message' => 'User has rewards disabled'
            ])->toResponse($request);
        }

        $taskId = $request->input('task_id');
        $taskTitle = $request->input('task_title');
        $completionTime = $request->input('completion_time', now());

        // Generate reward data
        $rewardData = $this->generateRewardData($taskId, $taskTitle, $completionTime);

        return ApiResponse::success([
            'rewards_triggered' => true,
            'reward_data' => $rewardData,
            'message' => 'Rewards animation triggered successfully'
        ])->toResponse($request);
    }

    /**
     * Toggle rewards for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggle(Request $request): JsonResponse
    {
        // Check if rewards feature is enabled globally
        if (!$this->featureFlagService->isEnabled('ui.enable_rewards')) {
            return ApiResponse::error(
                ['message' => 'Rewards feature is not enabled'],
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

        // Get current rewards state
        $preference = UserPreference::where('user_id', $user->id)->first();
        $currentState = $preference ? $preference->isRewardsEnabled() : true; // Default to true
        
        // Toggle the state
        $newState = !$currentState;
        
        // Update user preference
        if (!$preference) {
            $preference = UserPreference::create([
                'user_id' => $user->id,
                'preferences' => []
            ]);
        }
        
        $preference->setRewards($newState);

        // Clear feature flag cache
        $this->featureFlagService->clearCache('ui.enable_rewards', null, $user->id);

        return ApiResponse::success([
            'rewards_enabled' => $newState,
            'message' => $newState ? 'Rewards enabled' : 'Rewards disabled'
        ])->toResponse($request);
    }

    /**
     * Get current rewards state
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
        $featureEnabled = $this->featureFlagService->isEnabled('ui.enable_rewards');
        
        // Get user's current preference
        $preference = UserPreference::where('user_id', $user->id)->first();
        $userEnabled = $preference ? $preference->isRewardsEnabled() : true; // Default to true

        return ApiResponse::success([
            'feature_enabled' => $featureEnabled,
            'user_enabled' => $userEnabled,
            'rewards_active' => $featureEnabled && $userEnabled
        ])->toResponse($request);
    }

    /**
     * Get reward messages for different languages
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function messages(Request $request): JsonResponse
    {
        $locale = $request->get('locale', app()->getLocale());

        $messages = [
            'en' => [
                'congrats_message' => 'Great job! Task completed 🎉',
                'celebration_title' => 'Congratulations!',
                'task_completed' => 'Task completed successfully',
                'keep_it_up' => 'Keep up the great work!'
            ],
            'vi' => [
                'congrats_message' => 'Xuất sắc! Bạn đã hoàn thành công việc 🎉',
                'celebration_title' => 'Chúc mừng!',
                'task_completed' => 'Hoàn thành công việc thành công',
                'keep_it_up' => 'Tiếp tục phát huy!'
            ]
        ];

        $selectedMessages = $messages[$locale] ?? $messages['en'];

        return ApiResponse::success([
            'locale' => $locale,
            'messages' => $selectedMessages
        ])->toResponse($request);
    }

    /**
     * Generate reward data for animation
     *
     * @param string $taskId
     * @param string $taskTitle
     * @param string $completionTime
     * @return array
     */
    private function generateRewardData(string $taskId, string $taskTitle, string $completionTime): array
    {
        $locale = app()->getLocale();
        
        $messages = [
            'en' => [
                'congrats_message' => 'Great job! Task completed 🎉',
                'celebration_title' => 'Congratulations!',
                'task_completed' => 'Task completed successfully',
                'keep_it_up' => 'Keep up the great work!'
            ],
            'vi' => [
                'congrats_message' => 'Xuất sắc! Bạn đã hoàn thành công việc 🎉',
                'celebration_title' => 'Chúc mừng!',
                'task_completed' => 'Hoàn thành công việc thành công',
                'keep_it_up' => 'Tiếp tục phát huy!'
            ]
        ];

        $selectedMessages = $messages[$locale] ?? $messages['en'];

        return [
            'task_id' => $taskId,
            'task_title' => $taskTitle,
            'completion_time' => $completionTime,
            'animation_type' => 'confetti',
            'duration' => 4000, // 4 seconds
            'messages' => $selectedMessages,
            'config' => [
                'particle_count' => 100,
                'spread' => 70,
                'start_velocity' => 45,
                'colors' => ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57']
            ]
        ];
    }
}
