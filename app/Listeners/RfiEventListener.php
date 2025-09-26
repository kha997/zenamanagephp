<?php

namespace App\Listeners;
use Illuminate\Support\Facades\Auth;


use App\Models\Rfi;
use App\Models\Notification;
use App\Models\User;
use App\Events\RfiCreated;
use App\Events\RfiUpdated;
use App\Events\RfiResponded;
use App\Events\RfiClosed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RfiEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleRfiCreated(RfiCreated $event)
    {
        $rfi = $event->rfi;
        
        Log::info('RFI created', [
            'rfi_id' => $rfi->id,
            'subject' => $rfi->subject,
            'tenant_id' => $rfi->tenant_id,
            'created_by' => $rfi->created_by
        ]);

        // Notify project team members
        if ($rfi->project) {
            $teamMembers = $rfi->project->teams()
                ->with('members')
                ->get()
                ->pluck('members')
                ->flatten()
                ->unique('id');

            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'tenant_id' => $rfi->tenant_id,
                    'project_id' => $rfi->project_id,
                    'type' => 'rfi_created',
                    'title' => 'New RFI Created',
                    'message' => "RFI '{$rfi->subject}' has been created",
                    'data' => json_encode([
                        'rfi_id' => $rfi->id,
                        'subject' => $rfi->subject,
                        'project_id' => $rfi->project_id
                    ]),
                    'priority' => 'normal',
                    'channels' => json_encode(['inapp', 'email'])
                ]);
            }
        }
    }

    public function handleRfiUpdated(RfiUpdated $event)
    {
        $rfi = $event->rfi;
        
        Log::info('RFI updated', [
            'rfi_id' => $rfi->id,
            'subject' => $rfi->subject,
            'tenant_id' => $rfi->tenant_id,
            'updated_by' => Auth::id()
        ]);
    }

    public function handleRfiResponded(RfiResponded $event)
    {
        $rfi = $event->rfi;
        
        Log::info('RFI responded', [
            'rfi_id' => $rfi->id,
            'subject' => $rfi->subject,
            'tenant_id' => $rfi->tenant_id,
            'responded_by' => Auth::id()
        ]);

        // Notify RFI creator
        if ($rfi->created_by) {
            Notification::create([
                'user_id' => $rfi->created_by,
                'tenant_id' => $rfi->tenant_id,
                'project_id' => $rfi->project_id,
                'type' => 'rfi_responded',
                'title' => 'RFI Response Received',
                'message' => "RFI '{$rfi->subject}' has been responded to",
                'data' => json_encode([
                    'rfi_id' => $rfi->id,
                    'subject' => $rfi->subject,
                    'project_id' => $rfi->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }

    public function handleRfiClosed(RfiClosed $event)
    {
        $rfi = $event->rfi;
        
        Log::info('RFI closed', [
            'rfi_id' => $rfi->id,
            'subject' => $rfi->subject,
            'tenant_id' => $rfi->tenant_id,
            'closed_by' => Auth::id()
        ]);

        // Notify RFI creator
        if ($rfi->created_by) {
            Notification::create([
                'user_id' => $rfi->created_by,
                'tenant_id' => $rfi->tenant_id,
                'project_id' => $rfi->project_id,
                'type' => 'rfi_closed',
                'title' => 'RFI Closed',
                'message' => "RFI '{$rfi->subject}' has been closed",
                'data' => json_encode([
                    'rfi_id' => $rfi->id,
                    'subject' => $rfi->subject,
                    'project_id' => $rfi->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }
}