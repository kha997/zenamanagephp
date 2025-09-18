<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Models\ProjectActivity;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

/**
 * RealTimeNotificationService - Service cho real-time notifications
 */
class RealTimeNotificationService
{
    private Pusher $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => true
            ]
        );
    }

    /**
     * Send project update notification
     */
    public function sendProjectUpdate(Project $project, string $updatedBy, array $changes): void
    {
        try {
            $data = [
                'type' => 'project_update',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'progress' => $project->progress,
                    'status' => $project->status
                ],
                'updated_by' => $updatedBy,
                'changes' => $changes,
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastToProject($project->id, 'project.updated', $data);
            
            // Log activity
            ProjectActivity::logProjectUpdated($project, $updatedBy, $changes);
            
        } catch (\Exception $e) {
            Log::error('Failed to send project update notification', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'updated_by' => $updatedBy
            ]);
        }
    }

    /**
     * Send milestone completion notification
     */
    public function sendMilestoneCompleted(\App\Models\ProjectMilestone $milestone, string $completedBy): void
    {
        try {
            $data = [
                'type' => 'milestone_completed',
                'milestone' => [
                    'id' => $milestone->id,
                    'name' => $milestone->name,
                    'description' => $milestone->description,
                    'target_date' => $milestone->target_date?->toISOString(),
                    'completed_date' => $milestone->completed_date?->toISOString()
                ],
                'project' => [
                    'id' => $milestone->project->id,
                    'name' => $milestone->project->name,
                    'code' => $milestone->project->code
                ],
                'completed_by' => $completedBy,
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastToProject($milestone->project_id, 'milestone.completed', $data);
            
            // Log activity
            ProjectActivity::logMilestoneCompleted($milestone, $completedBy);
            
        } catch (\Exception $e) {
            Log::error('Failed to send milestone completion notification', [
                'error' => $e->getMessage(),
                'milestone_id' => $milestone->id,
                'completed_by' => $completedBy
            ]);
        }
    }

    /**
     * Send task update notification
     */
    public function sendTaskUpdate(\App\Models\Task $task, string $updatedBy, array $changes): void
    {
        try {
            $data = [
                'type' => 'task_update',
                'task' => [
                    'id' => $task->id,
                    'name' => $task->name,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date?->toISOString(),
                    'assigned_to' => $task->assigned_to
                ],
                'project' => [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                    'code' => $task->project->code
                ],
                'updated_by' => $updatedBy,
                'changes' => $changes,
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastToProject($task->project_id, 'task.updated', $data);
            
            // Log activity
            ProjectActivity::logTaskUpdated($task, $updatedBy, $changes);
            
        } catch (\Exception $e) {
            Log::error('Failed to send task update notification', [
                'error' => $e->getMessage(),
                'task_id' => $task->id,
                'updated_by' => $updatedBy
            ]);
        }
    }

    /**
     * Send team member joined notification
     */
    public function sendTeamMemberJoined(Project $project, User $user, string $role, string $addedBy): void
    {
        try {
            $data = [
                'type' => 'team_member_joined',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar
                ],
                'role' => $role,
                'added_by' => $addedBy,
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastToProject($project->id, 'team.member_joined', $data);
            
            // Log activity
            ProjectActivity::logTeamMemberJoined($project, $user, $role, $addedBy);
            
        } catch (\Exception $e) {
            Log::error('Failed to send team member joined notification', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'user_id' => $user->id,
                'added_by' => $addedBy
            ]);
        }
    }

    /**
     * Send document upload notification
     */
    public function sendDocumentUploaded(\App\Models\Document $document, string $uploadedBy): void
    {
        try {
            $data = [
                'type' => 'document_uploaded',
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'description' => $document->description,
                    'document_type' => $document->document_type,
                    'file_size' => $document->file_size,
                    'file_name' => $document->file_name
                ],
                'project' => [
                    'id' => $document->project->id,
                    'name' => $document->project->name,
                    'code' => $document->project->code
                ],
                'uploaded_by' => $uploadedBy,
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastToProject($document->project_id, 'document.uploaded', $data);
            
            // Log activity
            ProjectActivity::logDocumentUploaded($document, $uploadedBy);
            
        } catch (\Exception $e) {
            Log::error('Failed to send document upload notification', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'uploaded_by' => $uploadedBy
            ]);
        }
    }

    /**
     * Send project status change notification
     */
    public function sendProjectStatusChange(Project $project, string $oldStatus, string $newStatus, string $changedBy): void
    {
        try {
            $data = [
                'type' => 'project_status_change',
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'status' => $newStatus
                ],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $changedBy,
                'timestamp' => now()->toISOString()
            ];

            $this->broadcastToProject($project->id, 'project.status_changed', $data);
            
            // Log activity
            ProjectActivity::logProjectUpdated($project, $changedBy, [
                'status' => ['old' => $oldStatus, 'new' => $newStatus]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send project status change notification', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'changed_by' => $changedBy
            ]);
        }
    }

    /**
     * Send custom notification
     */
    public function sendCustomNotification(
        string $projectId,
        string $event,
        array $data,
        string $userId = null
    ): void {
        try {
            $notificationData = array_merge($data, [
                'timestamp' => now()->toISOString()
            ]);

            if ($userId) {
                $this->broadcastToUser($userId, $event, $notificationData);
            } else {
                $this->broadcastToProject($projectId, $event, $notificationData);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send custom notification', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'event' => $event,
                'user_id' => $userId
            ]);
        }
    }

    /**
     * Broadcast to project channel
     */
    private function broadcastToProject(string $projectId, string $event, array $data): void
    {
        $this->pusher->trigger("private-project.{$projectId}", $event, $data);
    }

    /**
     * Broadcast to user channel
     */
    private function broadcastToUser(string $userId, string $event, array $data): void
    {
        $this->pusher->trigger("private-user.{$userId}", $event, $data);
    }

    /**
     * Broadcast to tenant channel
     */
    private function broadcastToTenant(string $tenantId, string $event, array $data): void
    {
        $this->pusher->trigger("private-tenant.{$tenantId}", $event, $data);
    }

    /**
     * Get notification history for project
     */
    public function getNotificationHistory(string $projectId, int $limit = 50): array
    {
        $activities = ProjectActivity::byProject($projectId)
                                   ->with(['user:id,name,email,avatar'])
                                   ->orderBy('created_at', 'desc')
                                   ->limit($limit)
                                   ->get();

        return $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'type' => $activity->action,
                'description' => $activity->description,
                'user' => [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                    'email' => $activity->user->email,
                    'avatar' => $activity->user->avatar
                ],
                'metadata' => $activity->metadata,
                'timestamp' => $activity->created_at->toISOString(),
                'time_ago' => $activity->time_ago
            ];
        })->toArray();
    }

    /**
     * Get user notification preferences
     */
    public function getUserNotificationPreferences(string $userId): array
    {
        $user = User::find($userId);
        
        if (!$user) {
            return [];
        }

        $preferences = $user->preferences['notifications'] ?? [];

        return array_merge([
            'project_updates' => true,
            'milestone_completions' => true,
            'task_updates' => true,
            'team_changes' => true,
            'document_uploads' => true,
            'status_changes' => true,
            'email_notifications' => true,
            'push_notifications' => true,
            'real_time_updates' => true
        ], $preferences);
    }

    /**
     * Update user notification preferences
     */
    public function updateUserNotificationPreferences(string $userId, array $preferences): bool
    {
        try {
            $user = User::find($userId);
            
            if (!$user) {
                return false;
            }

            $currentPreferences = $user->preferences ?? [];
            $currentPreferences['notifications'] = array_merge(
                $currentPreferences['notifications'] ?? [],
                $preferences
            );

            $user->update(['preferences' => $currentPreferences]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to update user notification preferences', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'preferences' => $preferences
            ]);
            
            return false;
        }
    }

    /**
     * Send bulk notification to project team
     */
    public function sendBulkNotificationToTeam(
        Project $project,
        string $event,
        array $data,
        array $excludeUserIds = []
    ): void {
        try {
            $teamMembers = $project->teamMembers()
                                 ->whereNotIn('user_id', $excludeUserIds)
                                 ->get();

            foreach ($teamMembers as $member) {
                $this->broadcastToUser($member->user_id, $event, $data);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send bulk notification to team', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'event' => $event
            ]);
        }
    }
}
