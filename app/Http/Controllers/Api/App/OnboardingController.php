<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\OnboardingStep;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    protected $onboardingService;

    public function __construct(OnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    /**
     * Get onboarding steps for user
     */
    public function getSteps(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $steps = $this->onboardingService->getStepsForUser($user);

            return response()->json([
                'success' => true,
                'data' => $steps
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get onboarding steps', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve onboarding steps'
                ]
            ], 500);
        }
    }

    /**
     * Get current step for user
     */
    public function getCurrentStep(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentStep = $this->onboardingService->getCurrentStepForUser($user);

            if (!$currentStep) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No current step found'
                ]);
            }

            $progress = $currentStep->getProgressForUser($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'step' => $currentStep,
                    'progress' => $progress,
                    'is_completed' => $currentStep->isCompletedForUser($user),
                    'is_skipped' => $currentStep->isSkippedForUser($user)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get current onboarding step', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve current step'
                ]
            ], 500);
        }
    }

    /**
     * Get onboarding progress for user
     */
    public function getProgress(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $progress = $this->onboardingService->getProgressForUser($user);

            return response()->json([
                'success' => true,
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get onboarding progress', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve onboarding progress'
                ]
            ], 500);
        }
    }

    /**
     * Complete a step
     */
    public function completeStep(Request $request, int $stepId): JsonResponse
    {
        try {
            $request->validate([
                'data' => 'nullable|array'
            ]);

            $user = $request->user();
            $step = OnboardingStep::findOrFail($stepId);

            $progress = $this->onboardingService->completeStep($user, $step, $request->input('data', []));

            return response()->json([
                'success' => true,
                'data' => $progress,
                'message' => 'Step completed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to complete onboarding step', [
                'user_id' => $request->user()->id,
                'step_id' => $stepId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to complete step'
                ]
            ], 500);
        }
    }

    /**
     * Skip a step
     */
    public function skipStep(Request $request, int $stepId): JsonResponse
    {
        try {
            $request->validate([
                'data' => 'nullable|array'
            ]);

            $user = $request->user();
            $step = OnboardingStep::findOrFail($stepId);

            $progress = $this->onboardingService->skipStep($user, $step, $request->input('data', []));

            return response()->json([
                'success' => true,
                'data' => $progress,
                'message' => 'Step skipped successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to skip onboarding step', [
                'user_id' => $request->user()->id,
                'step_id' => $stepId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to skip step'
                ]
            ], 500);
        }
    }

    /**
     * Reset onboarding for user
     */
    public function reset(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $this->onboardingService->resetOnboarding($user);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding reset successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reset onboarding', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to reset onboarding'
                ]
            ], 500);
        }
    }

    /**
     * Check if onboarding is completed
     */
    public function isCompleted(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $isCompleted = $this->onboardingService->isOnboardingCompleted($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_completed' => $isCompleted
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check onboarding completion', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to check completion status'
                ]
            ], 500);
        }
    }

    /**
     * Get onboarding statistics (admin only)
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->onboardingService->getOnboardingStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get onboarding statistics', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'Failed to retrieve statistics'
                ]
            ], 500);
        }
    }
}
