<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FilePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any files.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the file.
     */
    public function view(User $user, File $file): bool
    {
        // Admin can view any file
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($file->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can view their files
        if ($file->user_id === $user->id) {
            return true;
        }

        // Public files can be viewed by anyone in the tenant
        if ($file->is_public) {
            return true;
        }

        // Project members can view project files
        if ($file->project_id && $user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create files.
     */
    public function create(User $user): bool
    {
        return $user->is_active && (
            $user->hasRole('admin') ||
            $user->hasRole('project_manager') ||
            $user->hasRole('member')
        );
    }

    /**
     * Determine whether the user can update the file.
     */
    public function update(User $user, File $file): bool
    {
        // Admin can update any file
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($file->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can update their files
        if ($file->user_id === $user->id) {
            return true;
        }

        // Project managers can update project files
        if ($file->project_id && $user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the file.
     */
    public function delete(User $user, File $file): bool
    {
        // Admin can delete any file
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($file->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can delete their files
        if ($file->user_id === $user->id) {
            return true;
        }

        // Project managers can delete project files
        if ($file->project_id && $user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the file.
     */
    public function restore(User $user, File $file): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the file.
     */
    public function forceDelete(User $user, File $file): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can download the file.
     */
    public function download(User $user, File $file): bool
    {
        return $this->view($user, $file);
    }

    /**
     * Determine whether the user can upload files.
     */
    public function upload(User $user): bool
    {
        return $this->create($user);
    }
}
