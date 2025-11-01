<?php

namespace App\Policies;

use App\Models\OnboardingStep;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OnboardingStepPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any onboarding steps.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the onboarding step.
     */
    public function view(User $user, OnboardingStep $onboardingStep): bool
    {
        // Admin can view any step
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can view steps for their role or general steps
        return $onboardingStep->is_active && (
            is_null($onboardingStep->role) || 
            $onboardingStep->role === $user->getRoleNames()->first()
        );
    }

    /**
     * Determine whether the user can create onboarding steps.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the onboarding step.
     */
    public function update(User $user, OnboardingStep $onboardingStep): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the onboarding step.
     */
    public function delete(User $user, OnboardingStep $onboardingStep): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the onboarding step.
     */
    public function restore(User $user, OnboardingStep $onboardingStep): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the onboarding step.
     */
    public function forceDelete(User $user, OnboardingStep $onboardingStep): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can complete onboarding steps.
     */
    public function complete(User $user, OnboardingStep $onboardingStep): bool
    {
        return $this->view($user, $onboardingStep);
    }

    /**
     * Determine whether the user can skip onboarding steps.
     */
    public function skip(User $user, OnboardingStep $onboardingStep): bool
    {
        return $this->view($user, $onboardingStep) && !$onboardingStep->is_required;
    }
}
