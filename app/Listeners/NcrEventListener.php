<?php

namespace App\Listeners;
use Illuminate\Support\Facades\Auth;


use App\Models\Ncr;
use App\Models\Notification;
use App\Models\User;
use App\Events\NcrCreated;
use App\Events\NcrUpdated;
use App\Events\NcrAssigned;
use App\Events\NcrResolved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NcrEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleNcrCreated(NcrCreated $event)
    {
        $ncr = $event->ncr;
        
        Log::info('NCR created', [
            'ncr_id' => $ncr->id,
            'title' => $ncr->title,
            'severity' => $ncr->severity,
            'tenant_id' => $ncr->tenant_id,
            'created_by' => $ncr->created_by
        ]);

        // Notify project team members
        if ($ncr->project) {
            $teamMembers = $ncr->project->teams()
                ->with('members')
                ->get()
                ->pluck('members')
                ->flatten()
                ->unique('id');

            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'tenant_id' => $ncr->tenant_id,
                    'project_id' => $ncr->project_id,
                    'type' => 'ncr_created',
                    'title' => 'New NCR Created',
                    'message' => "NCR '{$ncr->title}' has been created ({$ncr->severity} severity)",
                    'data' => json_encode([
                        'ncr_id' => $ncr->id,
                        'title' => $ncr->title,
                        'severity' => $ncr->severity,
                        'project_id' => $ncr->project_id
                    ]),
                    'priority' => $ncr->severity === 'critical' ? 'high' : 'normal',
                    'channels' => json_encode(['inapp', 'email'])
                ]);
            }
        }
    }

    public function handleNcrUpdated(NcrUpdated $event)
    {
        $ncr = $event->ncr;
        
        Log::info('NCR updated', [
            'ncr_id' => $ncr->id,
            'title' => $ncr->title,
            'tenant_id' => $ncr->tenant_id,
            'updated_by' => Auth::id()
        ]);
    }

    public function handleNcrAssigned(NcrAssigned $event)
    {
        $ncr = $event->ncr;
        $assignedTo = $event->assignedTo;
        
        Log::info('NCR assigned', [
            'ncr_id' => $ncr->id,
            'title' => $ncr->title,
            'assigned_to' => $assignedTo->id,
            'tenant_id' => $ncr->tenant_id
        ]);

        // Notify assigned user
        Notification::create([
            'user_id' => $assignedTo->id,
            'tenant_id' => $ncr->tenant_id,
            'project_id' => $ncr->project_id,
            'type' => 'ncr_assigned',
            'title' => 'NCR Assigned',
            'message' => "NCR '{$ncr->title}' has been assigned to you",
            'data' => json_encode([
                'ncr_id' => $ncr->id,
                'title' => $ncr->title,
                'project_id' => $ncr->project_id
            ]),
            'priority' => $ncr->severity === 'critical' ? 'high' : 'normal',
            'channels' => json_encode(['inapp', 'email'])
        ]);
    }

    public function handleNcrResolved(NcrResolved $event)
    {
        $ncr = $event->ncr;
        
        Log::info('NCR resolved', [
            'ncr_id' => $ncr->id,
            'title' => $ncr->title,
            'tenant_id' => $ncr->tenant_id,
            'resolved_by' => Auth::id()
        ]);

        // Notify NCR creator
        if ($ncr->created_by) {
            Notification::create([
                'user_id' => $ncr->created_by,
                'tenant_id' => $ncr->tenant_id,
                'project_id' => $ncr->project_id,
                'type' => 'ncr_resolved',
                'title' => 'NCR Resolved',
                'message' => "NCR '{$ncr->title}' has been resolved",
                'data' => json_encode([
                    'ncr_id' => $ncr->id,
                    'title' => $ncr->title,
                    'project_id' => $ncr->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }
}