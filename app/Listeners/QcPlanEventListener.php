<?php

namespace App\Listeners;
use Illuminate\Support\Facades\Auth;


use App\Models\QcPlan;
use App\Models\Notification;
use App\Models\User;
use App\Events\QcPlanCreated;
use App\Events\QcPlanApproved;
use App\Events\QcPlanExecuted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class QcPlanEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleQcPlanCreated(QcPlanCreated $event)
    {
        $qcPlan = $event->qcPlan;
        
        Log::info('QC Plan created', [
            'qc_plan_id' => $qcPlan->id,
            'name' => $qcPlan->name,
            'tenant_id' => $qcPlan->tenant_id,
            'created_by' => $qcPlan->created_by
        ]);

        // Notify project team members
        if ($qcPlan->project) {
            $teamMembers = $qcPlan->project->teams()
                ->with('members')
                ->get()
                ->pluck('members')
                ->flatten()
                ->unique('id');

            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'tenant_id' => $qcPlan->tenant_id,
                    'project_id' => $qcPlan->project_id,
                    'type' => 'qc_plan_created',
                    'title' => 'New QC Plan Created',
                    'message' => "QC Plan '{$qcPlan->name}' has been created",
                    'data' => json_encode([
                        'qc_plan_id' => $qcPlan->id,
                        'name' => $qcPlan->name,
                        'project_id' => $qcPlan->project_id
                    ]),
                    'priority' => 'normal',
                    'channels' => json_encode(['inapp', 'email'])
                ]);
            }
        }
    }

    public function handleQcPlanApproved(QcPlanApproved $event)
    {
        $qcPlan = $event->qcPlan;
        
        Log::info('QC Plan approved', [
            'qc_plan_id' => $qcPlan->id,
            'name' => $qcPlan->name,
            'tenant_id' => $qcPlan->tenant_id,
            'approved_by' => Auth::id()
        ]);

        // Notify QC Plan creator
        if ($qcPlan->created_by) {
            Notification::create([
                'user_id' => $qcPlan->created_by,
                'tenant_id' => $qcPlan->tenant_id,
                'project_id' => $qcPlan->project_id,
                'type' => 'qc_plan_approved',
                'title' => 'QC Plan Approved',
                'message' => "QC Plan '{$qcPlan->name}' has been approved",
                'data' => json_encode([
                    'qc_plan_id' => $qcPlan->id,
                    'name' => $qcPlan->name,
                    'project_id' => $qcPlan->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }

    public function handleQcPlanExecuted(QcPlanExecuted $event)
    {
        $qcPlan = $event->qcPlan;
        
        Log::info('QC Plan executed', [
            'qc_plan_id' => $qcPlan->id,
            'name' => $qcPlan->name,
            'tenant_id' => $qcPlan->tenant_id,
            'executed_by' => Auth::id()
        ]);

        // Notify project team members
        if ($qcPlan->project) {
            $teamMembers = $qcPlan->project->teams()
                ->with('members')
                ->get()
                ->pluck('members')
                ->flatten()
                ->unique('id');

            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'tenant_id' => $qcPlan->tenant_id,
                    'project_id' => $qcPlan->project_id,
                    'type' => 'qc_plan_executed',
                    'title' => 'QC Plan Executed',
                    'message' => "QC Plan '{$qcPlan->name}' has been executed",
                    'data' => json_encode([
                        'qc_plan_id' => $qcPlan->id,
                        'name' => $qcPlan->name,
                        'project_id' => $qcPlan->project_id
                    ]),
                    'priority' => 'normal',
                    'channels' => json_encode(['inapp'])
                ]);
            }
        }
    }
}