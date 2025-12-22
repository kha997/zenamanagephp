<?php

namespace App\Policies;

use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SearchHistoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any search histories.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the search history.
     */
    public function view(User $user, SearchHistory $searchHistory): bool
    {
        // Admin can view any search history
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($searchHistory->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Users can view their own search history
        return $searchHistory->user_id === $user->id;
    }

    /**
     * Determine whether the user can create search histories.
     */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can update the search history.
     */
    public function update(User $user, SearchHistory $searchHistory): bool
    {
        // Admin can update any search history
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($searchHistory->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Users can update their own search history
        return $searchHistory->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the search history.
     */
    public function delete(User $user, SearchHistory $searchHistory): bool
    {
        // Admin can delete any search history
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($searchHistory->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Users can delete their own search history
        return $searchHistory->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the search history.
     */
    public function restore(User $user, SearchHistory $searchHistory): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the search history.
     */
    public function forceDelete(User $user, SearchHistory $searchHistory): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can clear their search history.
     */
    public function clear(User $user): bool
    {
        return $user->is_active;
    }
}
