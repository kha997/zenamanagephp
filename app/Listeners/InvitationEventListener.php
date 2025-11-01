<?php

namespace App\Listeners;

use App\Models\Invitation;
use App\Models\Notification;
use App\Models\User;
use App\Events\InvitationSent;
use App\Events\InvitationAccepted;
use App\Events\InvitationRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class InvitationEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleInvitationSent(InvitationSent $event)
    {
        $invitation = $event->invitation;
        
        Log::info('Invitation sent', [
            'invitation_id' => $invitation->id,
            'email' => $invitation->email,
            'tenant_id' => $invitation->tenant_id,
            'invited_by' => $invitation->invited_by
        ]);

        // Notify invitation sender
        if ($invitation->invited_by) {
            Notification::create([
                'user_id' => $invitation->invited_by,
                'tenant_id' => $invitation->tenant_id,
                'type' => 'invitation_sent',
                'title' => 'Invitation Sent',
                'message' => "Invitation has been sent to {$invitation->email}",
                'data' => json_encode([
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->email
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    public function handleInvitationAccepted(InvitationAccepted $event)
    {
        $invitation = $event->invitation;
        $user = $event->user;
        
        Log::info('Invitation accepted', [
            'invitation_id' => $invitation->id,
            'email' => $invitation->email,
            'user_id' => $user->id,
            'tenant_id' => $invitation->tenant_id
        ]);

        // Notify invitation sender
        if ($invitation->invited_by) {
            Notification::create([
                'user_id' => $invitation->invited_by,
                'tenant_id' => $invitation->tenant_id,
                'type' => 'invitation_accepted',
                'title' => 'Invitation Accepted',
                'message' => "Invitation to {$invitation->email} has been accepted",
                'data' => json_encode([
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->email,
                    'user_id' => $user->id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    public function handleInvitationRejected(InvitationRejected $event)
    {
        $invitation = $event->invitation;
        
        Log::info('Invitation rejected', [
            'invitation_id' => $invitation->id,
            'email' => $invitation->email,
            'tenant_id' => $invitation->tenant_id
        ]);

        // Notify invitation sender
        if ($invitation->invited_by) {
            Notification::create([
                'user_id' => $invitation->invited_by,
                'tenant_id' => $invitation->tenant_id,
                'type' => 'invitation_rejected',
                'title' => 'Invitation Rejected',
                'message' => "Invitation to {$invitation->email} has been rejected",
                'data' => json_encode([
                    'invitation_id' => $invitation->id,
                    'email' => $invitation->email
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }
}