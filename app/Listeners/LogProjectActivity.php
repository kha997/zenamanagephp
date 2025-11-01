<?php declare(strict_types=1);

namespace App\Listeners;

use App\Models\ProjectActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * LogProjectActivity Listener - Log activities khi có events
 */
class LogProjectActivity implements ShouldQueue
{

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        if ($event instanceof ProjectMilestoneCompleted) {
            ProjectActivity::logMilestoneCompleted(
                $event->milestone,
                $event->completedBy
            );
        } elseif ($event instanceof ProjectTaskUpdated) {
            ProjectActivity::logTaskUpdated(
                $event->task,
                $event->updatedBy,
                $event->changes
            );
        } elseif ($event instanceof ProjectTeamMemberJoined) {
            ProjectActivity::logTeamMemberJoined(
                $event->project,
                $event->user,
                $event->role,
                $event->addedBy
            );
        }
    }
}