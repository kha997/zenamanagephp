<?php

namespace App\Services;

use App\Models\User;
use App\Models\OnboardingStep;
use App\Models\UserOnboardingProgress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class OnboardingService
{
    /**
     * Get onboarding steps for a user
     */
    public function getStepsForUser(User $user): Collection
    {
        return OnboardingStep::active()
            ->byRole($user->role)
            ->ordered()
            ->get()
            ->map(function ($step) use ($user) {
                $progress = $step->getProgressForUser($user);
                return [
                    'step' => $step,
                    'progress' => $progress,
                    'is_completed' => $step->isCompletedForUser($user),
                    'is_skipped' => $step->isSkippedForUser($user),
                    'status' => $progress ? $progress->status : 'pending'
                ];
            });
    }

    /**
     * Get next step for user
     */
    public function getNextStepForUser(User $user): ?OnboardingStep
    {
        $steps = OnboardingStep::active()
            ->byRole($user->role)
            ->ordered()
            ->get();

        foreach ($steps as $step) {
            if (!$step->isCompletedForUser($user) && !$step->isSkippedForUser($user)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Get current step for user
     */
    public function getCurrentStepForUser(User $user): ?OnboardingStep
    {
        return $this->getNextStepForUser($user);
    }

    /**
     * Complete a step for user
     */
    public function completeStep(User $user, OnboardingStep $step, array $data = []): UserOnboardingProgress
    {
        $progress = $step->getProgressForUser($user);
        
        if (!$progress) {
            $progress = UserOnboardingProgress::create([
                'user_id' => $user->id,
                'onboarding_step_id' => $step->id,
                'status' => 'completed',
                'completed_at' => now(),
                'data' => $data
            ]);
        } else {
            $progress->markAsCompleted($data);
        }

        Log::info('Onboarding step completed', [
            'user_id' => $user->id,
            'step_id' => $step->id,
            'step_key' => $step->key
        ]);

        return $progress;
    }

    /**
     * Skip a step for user
     */
    public function skipStep(User $user, OnboardingStep $step, array $data = []): UserOnboardingProgress
    {
        $progress = $step->getProgressForUser($user);
        
        if (!$progress) {
            $progress = UserOnboardingProgress::create([
                'user_id' => $user->id,
                'onboarding_step_id' => $step->id,
                'status' => 'skipped',
                'skipped_at' => now(),
                'data' => $data
            ]);
        } else {
            $progress->markAsSkipped($data);
        }

        Log::info('Onboarding step skipped', [
            'user_id' => $user->id,
            'step_id' => $step->id,
            'step_key' => $step->key
        ]);

        return $progress;
    }

    /**
     * Get onboarding progress for user
     */
    public function getProgressForUser(User $user): array
    {
        $steps = $this->getStepsForUser($user);
        $totalSteps = $steps->count();
        $completedSteps = $steps->where('is_completed', true)->count();
        $skippedSteps = $steps->where('is_skipped', true)->count();
        $pendingSteps = $steps->where('status', 'pending')->count();

        $progressPercentage = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;

        return [
            'total_steps' => $totalSteps,
            'completed_steps' => $completedSteps,
            'skipped_steps' => $skippedSteps,
            'pending_steps' => $pendingSteps,
            'progress_percentage' => $progressPercentage,
            'is_completed' => $pendingSteps === 0,
            'current_step' => $this->getCurrentStepForUser($user),
            'next_step' => $this->getNextStepForUser($user)
        ];
    }

    /**
     * Check if user has completed onboarding
     */
    public function isOnboardingCompleted(User $user): bool
    {
        $progress = $this->getProgressForUser($user);
        return $progress['is_completed'];
    }

    /**
     * Reset onboarding for user
     */
    public function resetOnboarding(User $user): void
    {
        UserOnboardingProgress::where('user_id', $user->id)->delete();

        Log::info('Onboarding reset for user', [
            'user_id' => $user->id
        ]);
    }

    /**
     * Initialize onboarding for new user
     */
    public function initializeForNewUser(User $user): void
    {
        $steps = OnboardingStep::active()
            ->byRole($user->role)
            ->ordered()
            ->get();

        foreach ($steps as $step) {
            UserOnboardingProgress::create([
                'user_id' => $user->id,
                'onboarding_step_id' => $step->id,
                'status' => 'pending'
            ]);
        }

        Log::info('Onboarding initialized for new user', [
            'user_id' => $user->id,
            'steps_count' => $steps->count()
        ]);
    }

    /**
     * Get onboarding statistics
     */
    public function getOnboardingStats(): array
    {
        $totalUsers = User::count();
        $completedUsers = User::whereHas('onboardingProgress', function ($query) {
            $query->where('status', 'completed');
        })->count();

        $steps = OnboardingStep::active()->get();
        $stepStats = [];

        foreach ($steps as $step) {
            $completed = UserOnboardingProgress::where('onboarding_step_id', $step->id)
                ->where('status', 'completed')
                ->count();
            
            $skipped = UserOnboardingProgress::where('onboarding_step_id', $step->id)
                ->where('status', 'skipped')
                ->count();

            $stepStats[] = [
                'step' => $step,
                'completed_count' => $completed,
                'skipped_count' => $skipped,
                'completion_rate' => $totalUsers > 0 ? round(($completed / $totalUsers) * 100) : 0
            ];
        }

        return [
            'total_users' => $totalUsers,
            'completed_users' => $completedUsers,
            'completion_rate' => $totalUsers > 0 ? round(($completedUsers / $totalUsers) * 100) : 0,
            'step_stats' => $stepStats
        ];
    }

    /**
     * Create default onboarding steps
     */
    public function createDefaultSteps(): void
    {
        $defaultSteps = [
            [
                'key' => 'welcome',
                'title' => 'Welcome to ZenaManage!',
                'description' => 'Let\'s get you started with a quick tour of the platform.',
                'type' => 'modal',
                'target_element' => null,
                'position' => null,
                'content' => [
                    'image' => '/images/onboarding/welcome.svg',
                    'features' => [
                        'Project Management',
                        'Task Tracking',
                        'Team Collaboration',
                        'Analytics & Reporting'
                    ]
                ],
                'actions' => ['next', 'skip'],
                'order' => 1,
                'is_required' => false,
                'role' => null
            ],
            [
                'key' => 'dashboard_overview',
                'title' => 'Dashboard Overview',
                'description' => 'Your dashboard shows key metrics, recent activity, and quick access to important features.',
                'type' => 'tooltip',
                'target_element' => '.dashboard-content',
                'position' => 'bottom',
                'content' => [
                    'highlight' => true
                ],
                'actions' => ['next', 'skip'],
                'order' => 2,
                'is_required' => false,
                'role' => null
            ],
            [
                'key' => 'create_project',
                'title' => 'Create Your First Project',
                'description' => 'Projects help you organize your work. Click here to create your first project.',
                'type' => 'interactive',
                'target_element' => '[data-onboarding="create-project"]',
                'position' => 'right',
                'content' => [
                    'action_required' => 'create_project',
                    'success_message' => 'Great! You\'ve created your first project.'
                ],
                'actions' => ['next', 'skip'],
                'order' => 3,
                'is_required' => true,
                'role' => null
            ],
            [
                'key' => 'add_team_member',
                'title' => 'Invite Team Members',
                'description' => 'Collaborate with your team by inviting members to your project.',
                'type' => 'tooltip',
                'target_element' => '[data-onboarding="invite-member"]',
                'position' => 'left',
                'content' => [
                    'highlight' => true
                ],
                'actions' => ['next', 'skip'],
                'order' => 4,
                'is_required' => false,
                'role' => 'project_manager'
            ],
            [
                'key' => 'upload_file',
                'title' => 'Upload Files',
                'description' => 'Share documents and files with your team using the file management system.',
                'type' => 'interactive',
                'target_element' => '[data-onboarding="upload-file"]',
                'position' => 'top',
                'content' => [
                    'action_required' => 'upload_file',
                    'success_message' => 'Perfect! You\'ve uploaded your first file.'
                ],
                'actions' => ['next', 'skip'],
                'order' => 5,
                'is_required' => false,
                'role' => null
            ],
            [
                'key' => 'explore_analytics',
                'title' => 'Explore Analytics',
                'description' => 'Track your progress and performance with detailed analytics and reports.',
                'type' => 'tooltip',
                'target_element' => '[data-onboarding="analytics"]',
                'position' => 'bottom',
                'content' => [
                    'highlight' => true
                ],
                'actions' => ['next', 'skip'],
                'order' => 6,
                'is_required' => false,
                'role' => null
            ],
            [
                'key' => 'customize_settings',
                'title' => 'Customize Your Settings',
                'description' => 'Personalize your experience by adjusting your account settings and preferences.',
                'type' => 'tooltip',
                'target_element' => '[data-onboarding="settings"]',
                'position' => 'left',
                'content' => [
                    'highlight' => true
                ],
                'actions' => ['next', 'skip'],
                'order' => 7,
                'is_required' => false,
                'role' => null
            ],
            [
                'key' => 'onboarding_complete',
                'title' => 'You\'re All Set!',
                'description' => 'Congratulations! You\'ve completed the onboarding process. You can always access help and tutorials from the help menu.',
                'type' => 'modal',
                'target_element' => null,
                'position' => null,
                'content' => [
                    'image' => '/images/onboarding/complete.svg',
                    'next_steps' => [
                        'Explore the dashboard',
                        'Create your first project',
                        'Invite team members',
                        'Set up notifications'
                    ]
                ],
                'actions' => ['finish'],
                'order' => 8,
                'is_required' => false,
                'role' => null
            ]
        ];

        foreach ($defaultSteps as $stepData) {
            OnboardingStep::updateOrCreate(
                ['key' => $stepData['key']],
                $stepData
            );
        }

        Log::info('Default onboarding steps created/updated');
    }
}
