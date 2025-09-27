<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CalendarEvent;
use Illuminate\Auth\Access\HandlesAuthorization;

class CalendarEventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any calendar events.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can view the calendar event.
     */
    public function view(User $user, CalendarEvent $calendarEvent)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $calendarEvent->tenant_id) {
            return false;
        }

        // Check if user is the event creator or has appropriate role
        return $user->id === $calendarEvent->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can create calendar events.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can update the calendar event.
     */
    public function update(User $user, CalendarEvent $calendarEvent)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $calendarEvent->tenant_id) {
            return false;
        }

        // Check if user is the event creator or has appropriate role
        return $user->id === $calendarEvent->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can delete the calendar event.
     */
    public function delete(User $user, CalendarEvent $calendarEvent)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $calendarEvent->tenant_id) {
            return false;
        }

        // Check if user is the event creator or has appropriate role
        return $user->id === $calendarEvent->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can invite others to the calendar event.
     */
    public function invite(User $user, CalendarEvent $calendarEvent)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $calendarEvent->tenant_id) {
            return false;
        }

        // Check if user is the event creator or has appropriate role
        return $user->id === $calendarEvent->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can respond to the calendar event.
     */
    public function respond(User $user, CalendarEvent $calendarEvent)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $calendarEvent->tenant_id) {
            return false;
        }

        // All team members can respond to events
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can view calendar event details.
     */
    public function viewDetails(User $user, CalendarEvent $calendarEvent)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $calendarEvent->tenant_id) {
            return false;
        }

        // Check if user is the event creator or has appropriate role
        return $user->id === $calendarEvent->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }
}
