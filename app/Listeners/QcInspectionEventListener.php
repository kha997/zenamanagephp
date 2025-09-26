<?php

namespace App\Listeners;
use Illuminate\Support\Facades\Auth;


use App\Models\QcInspection;
use App\Models\Notification;
use App\Models\User;
use App\Events\QcInspectionCreated;
use App\Events\QcInspectionCompleted;
use App\Events\QcInspectionApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class QcInspectionEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleQcInspectionCreated(QcInspectionCreated $event)
    {
        $qcInspection = $event->qcInspection;
        
        Log::info('QC Inspection created', [
            'qc_inspection_id' => $qcInspection->id,
            'name' => $qcInspection->name,
            'tenant_id' => $qcInspection->tenant_id,
            'created_by' => $qcInspection->created_by
        ]);

        // Notify inspector
        if ($qcInspection->inspector_id) {
            Notification::create([
                'user_id' => $qcInspection->inspector_id,
                'tenant_id' => $qcInspection->tenant_id,
                'project_id' => $qcInspection->project_id,
                'type' => 'qc_inspection_created',
                'title' => 'New QC Inspection',
                'message' => "QC Inspection '{$qcInspection->name}' has been assigned to you",
                'data' => json_encode([
                    'qc_inspection_id' => $qcInspection->id,
                    'name' => $qcInspection->name,
                    'project_id' => $qcInspection->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }

    public function handleQcInspectionCompleted(QcInspectionCompleted $event)
    {
        $qcInspection = $event->qcInspection;
        
        Log::info('QC Inspection completed', [
            'qc_inspection_id' => $qcInspection->id,
            'name' => $qcInspection->name,
            'tenant_id' => $qcInspection->tenant_id,
            'completed_by' => Auth::id()
        ]);

        // Notify project team members
        if ($qcInspection->project) {
            $teamMembers = $qcInspection->project->teams()
                ->with('members')
                ->get()
                ->pluck('members')
                ->flatten()
                ->unique('id');

            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'tenant_id' => $qcInspection->tenant_id,
                    'project_id' => $qcInspection->project_id,
                    'type' => 'qc_inspection_completed',
                    'title' => 'QC Inspection Completed',
                    'message' => "QC Inspection '{$qcInspection->name}' has been completed",
                    'data' => json_encode([
                        'qc_inspection_id' => $qcInspection->id,
                        'name' => $qcInspection->name,
                        'project_id' => $qcInspection->project_id
                    ]),
                    'priority' => 'normal',
                    'channels' => json_encode(['inapp'])
                ]);
            }
        }
    }

    public function handleQcInspectionApproved(QcInspectionApproved $event)
    {
        $qcInspection = $event->qcInspection;
        
        Log::info('QC Inspection approved', [
            'qc_inspection_id' => $qcInspection->id,
            'name' => $qcInspection->name,
            'tenant_id' => $qcInspection->tenant_id,
            'approved_by' => Auth::id()
        ]);

        // Notify inspector
        if ($qcInspection->inspector_id) {
            Notification::create([
                'user_id' => $qcInspection->inspector_id,
                'tenant_id' => $qcInspection->tenant_id,
                'project_id' => $qcInspection->project_id,
                'type' => 'qc_inspection_approved',
                'title' => 'QC Inspection Approved',
                'message' => "QC Inspection '{$qcInspection->name}' has been approved",
                'data' => json_encode([
                    'qc_inspection_id' => $qcInspection->id,
                    'name' => $qcInspection->name,
                    'project_id' => $qcInspection->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }
}