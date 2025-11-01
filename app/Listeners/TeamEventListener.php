<?php

namespace App\Listeners;
use Illuminate\Support\Facades\Auth;


use App\Models\Team;
use App\Models\Notification;
use App\Models\User;
use App\Events\TeamCreated;
use App\Events\TeamUpdated;
use App\Events\TeamDeleted;
use App\Events\TeamMemberAdded;
use App\Events\TeamMemberRemoved;
use App\Events\TeamRoleChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class TeamEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle team created event.
     */
    public function handleTeamCreated(TeamCreated $event)
    {
        $team = $event->team;
        
        Log::info('Team created', [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'tenant_id' => $team->tenant_id,
            'created_by' => $team->created_by
        ]);

        // Notify team creator
        if ($team->created_by) {
            Notification::create([
                'user_id' => $team->created_by,
                'tenant_id' => $team->tenant_id,
                'type' => 'team_created',
                'title' => 'Team Created',
                'message' => "Team '{$team->name}' has been created successfully",
                'data' => json_encode([
                    'team_id' => $team->id,
                    'team_name' => $team->name
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    /**
     * Handle team updated event.
     */
    public function handleTeamUpdated(TeamUpdated $event)
    {
        $team = $event->team;
        
        Log::info('Team updated', [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'tenant_id' => $team->tenant_id,
            'updated_by' => Auth::id()
        ]);

        // Notify all team members
        $teamMembers = $team->members()->get();
        foreach ($teamMembers as $member) {
            Notification::create([
                'user_id' => $member->id,
                'tenant_id' => $team->tenant_id,
                'type' => 'team_updated',
                'title' => 'Team Updated',
                'message' => "Team '{$team->name}' has been updated",
                'data' => json_encode([
                    'team_id' => $team->id,
                    'team_name' => $team->name
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    /**
     * Handle team deleted event.
     */
    public function handleTeamDeleted(TeamDeleted $event)
    {
        $team = $event->team;
        
        Log::info('Team deleted', [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'tenant_id' => $team->tenant_id,
            'deleted_by' => Auth::id()
        ]);

        // Notify all team members
        $teamMembers = $team->members()->get();
        foreach ($teamMembers as $member) {
            Notification::create([
                'user_id' => $member->id,
                'tenant_id' => $team->tenant_id,
                'type' => 'team_deleted',
                'title' => 'Team Deleted',
                'message' => "Team '{$team->name}' has been deleted",
                'data' => json_encode([
                    'team_id' => $team->id,
                    'team_name' => $team->name
                ]),
                'priority' => 'high',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }

    /**
     * Handle team member added event.
     */
    public function handleTeamMemberAdded(TeamMemberAdded $event)
    {
        $team = $event->team;
        $member = $event->member;
        $role = $event->role;
        
        Log::info('Team member added', [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'member_id' => $member->id,
            'member_name' => $member->name,
            'role' => $role,
            'tenant_id' => $team->tenant_id
        ]);

        // Notify the new member
        Notification::create([
            'user_id' => $member->id,
            'tenant_id' => $team->tenant_id,
            'type' => 'team_member_added',
            'title' => 'Added to Team',
            'message' => "You have been added to team '{$team->name}' as {$role}",
            'data' => json_encode([
                'team_id' => $team->id,
                'team_name' => $team->name,
                'role' => $role
            ]),
            'priority' => 'normal',
            'channels' => json_encode(['inapp', 'email'])
        ]);

        // Notify team leader
        $teamLeader = $team->members()->where('role', 'leader')->first();
        if ($teamLeader && $teamLeader->id !== $member->id) {
            Notification::create([
                'user_id' => $teamLeader->id,
                'tenant_id' => $team->tenant_id,
                'type' => 'team_member_added',
                'title' => 'New Team Member',
                'message' => "{$member->name} has been added to team '{$team->name}' as {$role}",
                'data' => json_encode([
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'role' => $role
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    /**
     * Handle team member removed event.
     */
    public function handleTeamMemberRemoved(TeamMemberRemoved $event)
    {
        $team = $event->team;
        $member = $event->member;
        
        Log::info('Team member removed', [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'member_id' => $member->id,
            'member_name' => $member->name,
            'tenant_id' => $team->tenant_id
        ]);

        // Notify the removed member
        Notification::create([
            'user_id' => $member->id,
            'tenant_id' => $team->tenant_id,
            'type' => 'team_member_removed',
            'title' => 'Removed from Team',
            'message' => "You have been removed from team '{$team->name}'",
            'data' => json_encode([
                'team_id' => $team->id,
                'team_name' => $team->name
            ]),
            'priority' => 'normal',
            'channels' => json_encode(['inapp', 'email'])
        ]);

        // Notify team leader
        $teamLeader = $team->members()->where('role', 'leader')->first();
        if ($teamLeader && $teamLeader->id !== $member->id) {
            Notification::create([
                'user_id' => $teamLeader->id,
                'tenant_id' => $team->tenant_id,
                'type' => 'team_member_removed',
                'title' => 'Team Member Removed',
                'message' => "{$member->name} has been removed from team '{$team->name}'",
                'data' => json_encode([
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'member_id' => $member->id,
                    'member_name' => $member->name
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    /**
     * Handle team role changed event.
     */
    public function handleTeamRoleChanged(TeamRoleChanged $event)
    {
        $team = $event->team;
        $member = $event->member;
        $oldRole = $event->oldRole;
        $newRole = $event->newRole;
        
        Log::info('Team role changed', [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'member_id' => $member->id,
            'member_name' => $member->name,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'tenant_id' => $team->tenant_id
        ]);

        // Notify the member whose role changed
        Notification::create([
            'user_id' => $member->id,
            'tenant_id' => $team->tenant_id,
            'type' => 'team_role_changed',
            'title' => 'Role Changed',
            'message' => "Your role in team '{$team->name}' has been changed from {$oldRole} to {$newRole}",
            'data' => json_encode([
                'team_id' => $team->id,
                'team_name' => $team->name,
                'old_role' => $oldRole,
                'new_role' => $newRole
            ]),
            'priority' => 'normal',
            'channels' => json_encode(['inapp', 'email'])
        ]);
    }
}
