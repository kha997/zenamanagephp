<?php

namespace App\Helpers;

use App\Models\User;

class RedirectHelper
{
    /**
     * Get redirect path after login based on user role
     * 
     * @param User $user
     * @return string
     */
    public static function getPostLoginRedirect(User $user): string
    {
        // Super Admin or Org Admin → Admin Dashboard
        if ($user->canAccessAdmin()) {
            return '/admin/dashboard';
        }
        
        // Regular users → App Dashboard
        return '/app/dashboard';
    }
}

