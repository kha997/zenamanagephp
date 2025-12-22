<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SupportTicket;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupportTicketPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any support tickets.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'support']);
    }

    /**
     * Determine whether the user can view the support ticket.
     */
    public function view(User $user, SupportTicket $supportTicket)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $supportTicket->tenant_id) {
            return false;
        }

        // Check if user is the ticket creator or has support role
        return $user->id === $supportTicket->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm', 'support']);
    }

    /**
     * Determine whether the user can create support tickets.
     */
    public function create(User $user)
    {
        return true; // All authenticated users can create support tickets
    }

    /**
     * Determine whether the user can update the support ticket.
     */
    public function update(User $user, SupportTicket $supportTicket)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $supportTicket->tenant_id) {
            return false;
        }

        // Check if user is the ticket creator or has support role
        return $user->id === $supportTicket->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm', 'support']);
    }

    /**
     * Determine whether the user can delete the support ticket.
     */
    public function delete(User $user, SupportTicket $supportTicket)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $supportTicket->tenant_id) {
            return false;
        }

        // Only super_admin and admin can delete
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can assign the support ticket.
     */
    public function assign(User $user, SupportTicket $supportTicket)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $supportTicket->tenant_id) {
            return false;
        }

        // Only support staff can assign tickets
        return $user->hasRole(['super_admin', 'admin', 'pm', 'support']);
    }

    /**
     * Determine whether the user can close the support ticket.
     */
    public function close(User $user, SupportTicket $supportTicket)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $supportTicket->tenant_id) {
            return false;
        }

        // Check if user is the ticket creator or has support role
        return $user->id === $supportTicket->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm', 'support']);
    }

    /**
     * Determine whether the user can reopen the support ticket.
     */
    public function reopen(User $user, SupportTicket $supportTicket)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $supportTicket->tenant_id) {
            return false;
        }

        // Check if user is the ticket creator or has support role
        return $user->id === $supportTicket->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm', 'support']);
    }

    /**
     * Determine whether the user can add comments to the support ticket.
     */
    public function comment(User $user, SupportTicket $supportTicket)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $supportTicket->tenant_id) {
            return false;
        }

        // Check if user is the ticket creator or has support role
        return $user->id === $supportTicket->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm', 'support']);
    }
}
