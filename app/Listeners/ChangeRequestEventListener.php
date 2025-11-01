<?php

namespace App\Listeners;
use Illuminate\Support\Facades\Auth;


use App\Models\ChangeRequest;
use App\Models\Notification;
use App\Models\User;
use App\Events\ChangeRequestCreated;
use App\Events\ChangeRequestUpdated;
use App\Events\ChangeRequestApproved;
use App\Events\ChangeRequestRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ChangeRequestEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleChangeRequestCreated(ChangeRequestCreated $event)
    {
        $changeRequest = $event->changeRequest;
        
        Log::info('Change request created', [
            'change_request_id' => $changeRequest->id,
            'title' => $changeRequest->title,
            'tenant_id' => $changeRequest->tenant_id,
            'created_by' => $changeRequest->created_by
        ]);

        // Notify project team members
        if ($changeRequest->project) {
            $teamMembers = $changeRequest->project->teams()
                ->with('members')
                ->get()
                ->pluck('members')
                ->flatten()
                ->unique('id');

            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'tenant_id' => $changeRequest->tenant_id,
                    'project_id' => $changeRequest->project_id,
                    'type' => 'change_request_created',
                    'title' => 'New Change Request',
                    'message' => "Change request '{$changeRequest->title}' has been created",
                    'data' => json_encode([
                        'change_request_id' => $changeRequest->id,
                        'title' => $changeRequest->title,
                        'project_id' => $changeRequest->project_id
                    ]),
                    'priority' => 'normal',
                    'channels' => json_encode(['inapp', 'email'])
                ]);
            }
        }
    }

    public function handleChangeRequestUpdated(ChangeRequestUpdated $event)
    {
        $changeRequest = $event->changeRequest;
        
        Log::info('Change request updated', [
            'change_request_id' => $changeRequest->id,
            'title' => $changeRequest->title,
            'tenant_id' => $changeRequest->tenant_id,
            'updated_by' => Auth::id()
        ]);
    }

    public function handleChangeRequestApproved(ChangeRequestApproved $event)
    {
        $changeRequest = $event->changeRequest;
        
        Log::info('Change request approved', [
            'change_request_id' => $changeRequest->id,
            'title' => $changeRequest->title,
            'tenant_id' => $changeRequest->tenant_id,
            'approved_by' => Auth::id()
        ]);

        // Notify change request creator
        if ($changeRequest->created_by) {
            Notification::create([
                'user_id' => $changeRequest->created_by,
                'tenant_id' => $changeRequest->tenant_id,
                'project_id' => $changeRequest->project_id,
                'type' => 'change_request_approved',
                'title' => 'Change Request Approved',
                'message' => "Change request '{$changeRequest->title}' has been approved",
                'data' => json_encode([
                    'change_request_id' => $changeRequest->id,
                    'title' => $changeRequest->title,
                    'project_id' => $changeRequest->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }

    public function handleChangeRequestRejected(ChangeRequestRejected $event)
    {
        $changeRequest = $event->changeRequest;
        
        Log::info('Change request rejected', [
            'change_request_id' => $changeRequest->id,
            'title' => $changeRequest->title,
            'tenant_id' => $changeRequest->tenant_id,
            'rejected_by' => Auth::id()
        ]);

        // Notify change request creator
        if ($changeRequest->created_by) {
            Notification::create([
                'user_id' => $changeRequest->created_by,
                'tenant_id' => $changeRequest->tenant_id,
                'project_id' => $changeRequest->project_id,
                'type' => 'change_request_rejected',
                'title' => 'Change Request Rejected',
                'message' => "Change request '{$changeRequest->title}' has been rejected",
                'data' => json_encode([
                    'change_request_id' => $changeRequest->id,
                    'title' => $changeRequest->title,
                    'project_id' => $changeRequest->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }
}