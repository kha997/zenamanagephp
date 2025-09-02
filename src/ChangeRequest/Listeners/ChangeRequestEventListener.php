<?php declare(strict_types=1);

namespace Src\ChangeRequest\Listeners;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Src\Foundation\Events\EventBus;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Src\ChangeRequest\Events\ChangeRequestCreated;
use Src\ChangeRequest\Events\ChangeRequestUpdated;
use Src\ChangeRequest\Events\ChangeRequestSubmitted;
use Src\ChangeRequest\Events\ChangeRequestApproved;
use Src\ChangeRequest\Events\ChangeRequestRejected;
use Src\ChangeRequest\Events\ChangeRequestDeleted;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\Notification\Services\NotificationService;
use Src\Notification\Services\NotificationRuleService;
use Src\Notification\Models\Notification;
use Src\CoreProject\Models\Baseline;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\InteractionLogs\Services\InteractionLogService;
use App\Models\User;
use Carbon\Carbon;

/**
 * Event Listener cho các sự kiện của Change Request
 * Xử lý business logic khi có các thay đổi trong vòng đời của Change Request
 */
class ChangeRequestEventListener
{
    private NotificationService $notificationService;
    private NotificationRuleService $notificationRuleService;
    private InteractionLogService $interactionLogService;

    public function __construct(
        NotificationService $notificationService,
        NotificationRuleService $notificationRuleService,
        InteractionLogService $interactionLogService
    ) {
        $this->notificationService = $notificationService;
        $this->notificationRuleService = $notificationRuleService;
        $this->interactionLogService = $interactionLogService;
    }

    /**
     * Đăng ký các event listeners
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            ChangeRequestCreated::class,
            [ChangeRequestEventListener::class, 'handleChangeRequestCreated']
        );

        $events->listen(
            ChangeRequestUpdated::class,
            [ChangeRequestEventListener::class, 'handleChangeRequestUpdated']
        );

        $events->listen(
            ChangeRequestSubmitted::class,
            [ChangeRequestEventListener::class, 'handleChangeRequestSubmitted']
        );

        $events->listen(
            ChangeRequestApproved::class,
            [ChangeRequestEventListener::class, 'handleChangeRequestApproved']
        );

        $events->listen(
            ChangeRequestRejected::class,
            [ChangeRequestEventListener::class, 'handleChangeRequestRejected']
        );

        $events->listen(
            ChangeRequestDeleted::class,
            [ChangeRequestEventListener::class, 'handleChangeRequestDeleted']
        );
    }

    /**
     * Xử lý khi Change Request được tạo
     *
     * @param ChangeRequestCreated $event
     * @return void
     */
    public function handleChangeRequestCreated(ChangeRequestCreated $event): void
    {
        Log::info('Change Request created', [
            'change_request_id' => $event->changeRequest->id,
            'change_request_code' => $event->changeRequest->code,
            'project_id' => $event->changeRequest->project_id,
            'created_by' => $event->changeRequest->created_by,
            'title' => $event->changeRequest->title
        ]);

        try {
            // Gửi notification cho project manager
            $this->notifyProjectManagerOnCreated($event->changeRequest);
            
            // Cập nhật project activity timeline
            $this->createActivityLog($event->changeRequest, 'created', 'Change Request được tạo');
            
            // Tạo audit log entry
            $this->createAuditLog($event->changeRequest, 'created', [
                'action' => 'Change Request Created',
                'details' => [
                    'code' => $event->changeRequest->code,
                    'title' => $event->changeRequest->title,
                    'impact_days' => $event->changeRequest->impact_days,
                    'impact_cost' => $event->changeRequest->impact_cost
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error processing Change Request creation', [
                'change_request_id' => $event->changeRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý khi Change Request được cập nhật
     *
     * @param ChangeRequestUpdated $event
     * @return void
     */
    public function handleChangeRequestUpdated(ChangeRequestUpdated $event): void
    {
        Log::info('Change Request updated', [
            'change_request_id' => $event->changeRequest->id,
            'change_request_code' => $event->changeRequest->code,
            'project_id' => $event->changeRequest->project_id,
            'updated_by' => $event->actorId,
            'changed_fields' => $event->changedFields
        ]);

        try {
            // Gửi notification cho stakeholders nếu có thay đổi quan trọng
            $importantFields = ['title', 'description', 'impact_days', 'impact_cost', 'status'];
            $hasImportantChanges = !empty(array_intersect(array_keys($event->changedFields), $importantFields));
            
            if ($hasImportantChanges) {
                $this->notifyStakeholdersOnUpdate($event->changeRequest, $event->changedFields);
            }
            
            // Cập nhật version history
            $this->createVersionHistory($event->changeRequest, $event->changedFields);
            
            // Kiểm tra và cập nhật approval workflow nếu cần
            if (isset($event->changedFields['status'])) {
                $this->updateApprovalWorkflow($event->changeRequest, $event->changedFields['status']);
            }
            
            // Tạo activity log
            $this->createActivityLog($event->changeRequest, 'updated', 'Change Request được cập nhật', [
                'changed_fields' => $event->changedFields
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error processing Change Request update', [
                'change_request_id' => $event->changeRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý khi Change Request được gửi để phê duyệt
     *
     * @param ChangeRequestSubmitted $event
     * @return void
     */
    public function handleChangeRequestSubmitted(ChangeRequestSubmitted $event): void
    {
        Log::info('Change Request submitted for approval', [
            'change_request_id' => $event->changeRequest->id,
            'change_request_code' => $event->changeRequest->code,
            'project_id' => $event->changeRequest->project_id,
            'submitted_by' => $event->actorId
        ]);

        try {
            // Gửi notification cho approvers
            $this->notifyApproversOnSubmission($event->changeRequest);
            
            // Tạo approval workflow tasks
            $this->createApprovalWorkflowTasks($event->changeRequest);
            
            // Cập nhật project dashboard với pending approvals
            $this->updateProjectDashboard($event->changeRequest, 'pending_approval');
            
            // Set deadline cho approval process (7 ngày từ khi submit)
            $this->setApprovalDeadline($event->changeRequest, 7);
            
            // Tạo activity log
            $this->createActivityLog($event->changeRequest, 'submitted', 'Change Request được gửi để phê duyệt');
            
        } catch (\Exception $e) {
            Log::error('Error processing Change Request submission', [
                'change_request_id' => $event->changeRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý khi Change Request được phê duyệt
     * Đây là sự kiện quan trọng nhất - sẽ trigger các thay đổi trong hệ thống
     *
     * @param ChangeRequestApproved $event
     * @return void
     */
    public function handleChangeRequestApproved(ChangeRequestApproved $event): void
    {
        $changeRequest = $event->changeRequest;
        
        Log::info('Change Request approved', [
            'change_request_id' => $changeRequest->id,
            'change_request_code' => $changeRequest->code,
            'project_id' => $changeRequest->project_id,
            'approved_by' => $event->actorId,
            'impact_days' => $changeRequest->impact_days,
            'impact_cost' => $changeRequest->impact_cost
        ]);

        try {
            DB::beginTransaction();

            // Phát sự kiện để các module khác xử lý impact
            $this->publishChangeRequestImpactEvent($changeRequest, $event->actorId);

            // Cập nhật project baseline nếu cần
            $this->updateProjectBaseline($changeRequest);

            // Gửi notifications
            $this->sendApprovalNotifications($changeRequest, $event->actorId);

            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing approved Change Request', [
                'change_request_id' => $changeRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Xử lý khi Change Request bị từ chối
     *
     * @param ChangeRequestRejected $event
     * @return void
     */
    public function handleChangeRequestRejected(ChangeRequestRejected $event): void
    {
        Log::info('Change Request rejected', [
            'change_request_id' => $event->changeRequest->id,
            'change_request_code' => $event->changeRequest->code,
            'project_id' => $event->changeRequest->project_id,
            'rejected_by' => $event->actorId,
            'decision_note' => $event->changeRequest->decision_note
        ]);

        try {
            // Gửi notification cho requester
            $this->notifyRequesterOnRejection($event->changeRequest);
            
            // Cập nhật project activity log
            $this->createActivityLog($event->changeRequest, 'rejected', 'Change Request bị từ chối', [
                'decision_note' => $event->changeRequest->decision_note,
                'rejected_by' => $event->actorId
            ]);
            
            // Archive related documents
            $this->archiveRelatedDocuments($event->changeRequest);
            
            // Close any related workflow tasks
            $this->closeRelatedWorkflowTasks($event->changeRequest);
            
        } catch (\Exception $e) {
            Log::error('Error processing Change Request rejection', [
                'change_request_id' => $event->changeRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý khi Change Request bị xóa
     *
     * @param ChangeRequestDeleted $event
     * @return void
     */
    public function handleChangeRequestDeleted(ChangeRequestDeleted $event): void
    {
        Log::info('Change Request deleted', [
            'change_request_id' => $event->entityId,
            'change_request_code' => $event->changeRequestCode,
            'project_id' => $event->projectId,
            'deleted_by' => $event->actorId
        ]);

        try {
            // Cleanup related data
            $this->cleanupRelatedData($event->entityId);
            
            // Archive audit trail
            $this->archiveAuditTrail($event->entityId);
            
            // Notify stakeholders
            $this->notifyStakeholdersOnDeletion($event->projectId, $event->changeRequestCode, $event->actorId);
            
            // Update project metrics
            $this->updateProjectMetrics($event->projectId);
            
        } catch (\Exception $e) {
            Log::error('Error processing Change Request deletion', [
                'change_request_id' => $event->entityId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Phát sự kiện impact của Change Request để các module khác xử lý
     *
     * @param ChangeRequest $changeRequest
     * @param string $actorId
     * @return void
     */
    private function publishChangeRequestImpactEvent(ChangeRequest $changeRequest, string $actorId): void
    {
        // Phát sự kiện theo EventBus pattern để loose coupling
        EventBus::publish('ChangeRequest.ChangeRequest.Approved', [
            'entityId' => $changeRequest->id,
            'projectId' => $changeRequest->project_id,
            'actorId' => $actorId,
            'changedFields' => ['status' => ['old' => 'awaiting_approval', 'new' => 'approved']],
            'timestamp' => now()->toISOString(),
            'eventId' => uniqid('cr_approved_', true),
            'impactData' => [
                'impact_days' => $changeRequest->impact_days,
                'impact_cost' => $changeRequest->impact_cost,
                'impact_kpi' => $changeRequest->impact_kpi,
                'change_request_code' => $changeRequest->code,
                'change_request_title' => $changeRequest->title
            ]
        ]);
    }

    /**
     * Cập nhật project baseline khi có Change Request được approve
     *
     * @param ChangeRequest $changeRequest
     * @return void
     */
    private function updateProjectBaseline(ChangeRequest $changeRequest): void
    {
        try {
            $project = $changeRequest->project;
            
            // Lấy baseline execution hiện tại
            $currentBaseline = Baseline::getCurrentBaseline($project->id, Baseline::TYPE_EXECUTION);
            
            if ($currentBaseline) {
                // Tạo baseline version mới với impact từ Change Request
                $newEndDate = $currentBaseline->end_date->addDays($changeRequest->impact_days);
                $newCost = $currentBaseline->cost + $changeRequest->impact_cost;
                
                Baseline::createNewVersion([
                    'project_id' => $project->id,
                    'type' => Baseline::TYPE_EXECUTION,
                    'start_date' => $currentBaseline->start_date,
                    'end_date' => $newEndDate,
                    'cost' => $newCost,
                    'note' => "Updated due to approved Change Request {$changeRequest->code}",
                    'created_by' => AuthHelper::id() ?? $changeRequest->decided_by
                ]);
                
                Log::info('New baseline version created', [
                    'change_request_id' => $changeRequest->id,
                    'project_id' => $project->id,
                    'impact_days' => $changeRequest->impact_days,
                    'impact_cost' => $changeRequest->impact_cost
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Error updating project baseline', [
                'change_request_id' => $changeRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi notifications khi Change Request được approve
     *
     * @param ChangeRequest $changeRequest
     * @param string $approvedBy
     * @return void
     */
    private function sendApprovalNotifications(ChangeRequest $changeRequest, string $approvedBy): void
    {
        try {
            $project = $changeRequest->project;
            
            // Notification cho requester
            $this->notificationService->createNotification([
                'user_id' => $changeRequest->created_by,
                'priority' => Notification::PRIORITY_NORMAL,
                'title' => 'Change Request Approved',
                'body' => "Your Change Request {$changeRequest->code} has been approved.",
                'link_url' => "/projects/{$project->id}/change-requests/{$changeRequest->id}",
                'channel' => Notification::CHANNEL_INAPP
            ]);
            
            // Notification cho project team
            $projectUsers = $project->users()->get();
            foreach ($projectUsers as $user) {
                if ($user->id !== $changeRequest->created_by) {
                    $rules = $this->notificationRuleService->getApplicableRules(
                        $user->id,
                        'change_request.approved',
                        $project->id,
                        Notification::PRIORITY_NORMAL
                    );
                    
                    if (!$rules->isEmpty()) {
                        $this->notificationService->createNotification([
                            'user_id' => $user->id,
                            'priority' => Notification::PRIORITY_NORMAL,
                            'title' => 'Change Request Approved',
                            'body' => "Change Request {$changeRequest->code} has been approved and will impact the project.",
                            'link_url' => "/projects/{$project->id}/change-requests/{$changeRequest->id}",
                            'channel' => Notification::CHANNEL_INAPP
                        ]);
                    }
                }
            }
            
            Log::info('Approval notifications sent', [
                'change_request_id' => $changeRequest->id,
                'approved_by' => $approvedBy,
                'notifications_sent' => $projectUsers->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error sending approval notifications', [
                'change_request_id' => $changeRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi notification cho project manager khi CR được tạo
     */
    private function notifyProjectManagerOnCreated(ChangeRequest $changeRequest): void
    {
        $project = $changeRequest->project;
        $projectManagers = $project->users()
            ->whereHas('projectRoles', function ($query) use ($project) {
                $query->where('project_id', $project->id)
                      ->whereHas('role', function ($roleQuery) {
                          $roleQuery->where('name', 'Project Manager');
                      });
            })->get();

        foreach ($projectManagers as $manager) {
            $this->notificationService->createNotification([
                'user_id' => $manager->id,
                'priority' => Notification::PRIORITY_NORMAL,
                'title' => 'New Change Request Created',
                'body' => "A new Change Request {$changeRequest->code} has been created for project {$project->name}.",
                'link_url' => "/projects/{$project->id}/change-requests/{$changeRequest->id}",
                'channel' => Notification::CHANNEL_INAPP
            ]);
        }
    }

    /**
     * Tạo activity log cho Change Request
     */
    private function createActivityLog(ChangeRequest $changeRequest, string $action, string $description, array $metadata = []): void
    {
        $this->interactionLogService->createLog([
            'project_id' => $changeRequest->project_id,
            'type' => 'note',
            'description' => $description,
            'tag_path' => "ChangeRequest/{$changeRequest->code}",
            'visibility' => 'internal',
            'created_by' => AuthHelper::idOrSystem()
        ]);
    }

    /**
     * Tạo audit log entry
     */
    private function createAuditLog(ChangeRequest $changeRequest, string $action, array $details): void
    {
        // Sử dụng Laravel's built-in logging với structured data
        Log::channel('audit')->info('Change Request Audit', [
            'change_request_id' => $changeRequest->id,
            'project_id' => $changeRequest->project_id,
            'action' => $action,
            'actor_id' => AuthHelper::id(),
            'timestamp' => now()->toISOString(),
            'details' => $details
        ]);
    }

    /**
     * Notify stakeholders on update
     */
    private function notifyStakeholdersOnUpdate(ChangeRequest $changeRequest, array $changedFields): void
    {
        $project = $changeRequest->project;
        $stakeholders = $project->users()->get();
        
        foreach ($stakeholders as $user) {
            $rules = $this->notificationRuleService->getApplicableRules(
                $user->id,
                'change_request.updated',
                $project->id,
                Notification::PRIORITY_LOW
            );
            
            if (!$rules->isEmpty()) {
                $this->notificationService->createNotification([
                    'user_id' => $user->id,
                    'priority' => Notification::PRIORITY_LOW,
                    'title' => 'Change Request Updated',
                    'body' => "Change Request {$changeRequest->code} has been updated.",
                    'link_url' => "/projects/{$project->id}/change-requests/{$changeRequest->id}",
                    'channel' => Notification::CHANNEL_INAPP
                ]);
            }
        }
    }

    /**
     * Tạo version history cho Change Request
     */
    private function createVersionHistory(ChangeRequest $changeRequest, array $changedFields): void
    {
        // Log version history as structured audit log
        Log::channel('audit')->info('Change Request Version History', [
            'change_request_id' => $changeRequest->id,
            'version_timestamp' => now()->toISOString(),
            'changed_fields' => $changedFields,
            'actor_id' => AuthHelper::id()
        ]);
    }

    /**
     * Cập nhật approval workflow
     */
    private function updateApprovalWorkflow(ChangeRequest $changeRequest, array $statusChange): void
    {
        if ($statusChange['new'] === 'awaiting_approval') {
            $this->createApprovalWorkflowTasks($changeRequest);
        } elseif (in_array($statusChange['new'], ['approved', 'rejected'])) {
            $this->closeRelatedWorkflowTasks($changeRequest);
        }
    }

    /**
     * Notify approvers on submission
     */
    private function notifyApproversOnSubmission(ChangeRequest $changeRequest): void
    {
        $project = $changeRequest->project;
        $approvers = $project->users()
            ->whereHas('projectRoles', function ($query) use ($project) {
                $query->where('project_id', $project->id)
                      ->whereHas('role', function ($roleQuery) {
                          $roleQuery->whereIn('name', ['Project Manager', 'Project Director']);
                      });
            })->get();

        foreach ($approvers as $approver) {
            $this->notificationService->createNotification([
                'user_id' => $approver->id,
                'priority' => Notification::PRIORITY_HIGH,
                'title' => 'Change Request Awaiting Approval',
                'body' => "Change Request {$changeRequest->code} is awaiting your approval.",
                'link_url' => "/projects/{$project->id}/change-requests/{$changeRequest->id}",
                'channel' => Notification::CHANNEL_INAPP
            ]);
        }
    }

    /**
     * Tạo approval workflow tasks
     */
    private function createApprovalWorkflowTasks(ChangeRequest $changeRequest): void
    {
        $project = $changeRequest->project;
        
        // Tạo task approval cho project managers
        $approvers = $project->users()
            ->whereHas('projectRoles', function ($query) use ($project) {
                $query->where('project_id', $project->id)
                      ->whereHas('role', function ($roleQuery) {
                          $roleQuery->whereIn('name', ['Project Manager', 'Project Director']);
                      });
            })->get();

        foreach ($approvers as $approver) {
            Task::create([
                'project_id' => $project->id,
                'name' => "Approve Change Request {$changeRequest->code}",
                'description' => "Review and approve/reject Change Request: {$changeRequest->title}",
                'status' => Task::STATUS_PENDING,
                'priority' => Task::PRIORITY_MEDIUM, // Thay từ PRIORITY_NORMAL
                'start_date' => now(),
                'end_date' => now()->addDays(7),
                'tags' => ['approval', 'change-request'],
                'visibility' => 'internal'
            ]);
        }
    }

    /**
     * Cập nhật project dashboard
     */
    private function updateProjectDashboard(ChangeRequest $changeRequest, string $status): void
    {
        // Log dashboard update event
        EventBus::publish('Project.Dashboard.Updated', [
            'entityId' => $changeRequest->project_id,
            'projectId' => $changeRequest->project_id,
            'actorId' => AuthHelper::id(),
            'changedFields' => ['pending_approvals' => $status],
            'timestamp' => now()->toISOString(),
            'eventId' => uniqid('dashboard_', true)
        ]);
    }

    /**
     * Set approval deadline
     */
    private function setApprovalDeadline(ChangeRequest $changeRequest, int $days): void
    {
        // Tạo reminder task cho deadline
        Task::create([
            'project_id' => $changeRequest->project_id,
            'name' => "Change Request {$changeRequest->code} Approval Deadline",
            'description' => "Reminder: Change Request approval deadline approaching",
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_NORMAL,
            'start_date' => now()->addDays($days - 1),
            'end_date' => now()->addDays($days),
            'tags' => ['reminder', 'deadline', 'change-request'],
            'visibility' => 'internal'
        ]);
    }

    /**
     * Notify requester on rejection
     */
    private function notifyRequesterOnRejection(ChangeRequest $changeRequest): void
    {
        $this->notificationService->createNotification([
            'user_id' => $changeRequest->created_by,
            'priority' => Notification::PRIORITY_NORMAL,
            'title' => 'Change Request Rejected',
            'body' => "Your Change Request {$changeRequest->code} has been rejected.",
            'link_url' => "/projects/{$changeRequest->project_id}/change-requests/{$changeRequest->id}",
            'channel' => Notification::CHANNEL_INAPP
        ]);
    }

    /**
     * Archive related documents
     */
    private function archiveRelatedDocuments(ChangeRequest $changeRequest): void
    {
        // Log document archival
        Log::info('Archiving documents for rejected Change Request', [
            'change_request_id' => $changeRequest->id,
            'project_id' => $changeRequest->project_id
        ]);
    }

    /**
     * Close related workflow tasks
     */
    private function closeRelatedWorkflowTasks(ChangeRequest $changeRequest): void
    {
        Task::where('project_id', $changeRequest->project_id)
            ->where('name', 'like', "%{$changeRequest->code}%")
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->update(['status' => Task::STATUS_CANCELLED]);
    }

    /**
     * Cleanup related data on deletion
     */
    private function cleanupRelatedData(string $changeRequestId): void
    {
        // Cleanup workflow tasks, notifications, etc.
        Log::info('Cleaning up data for deleted Change Request', [
            'change_request_id' => $changeRequestId
        ]);
    }

    /**
     * Archive audit trail
     */
    private function archiveAuditTrail(string $changeRequestId): void
    {
        Log::channel('audit')->info('Change Request Deleted - Audit Trail Archived', [
            'change_request_id' => $changeRequestId,
            'archived_at' => now()->toISOString()
        ]);
    }

    /**
     * Notify stakeholders on deletion
     */
    private function notifyStakeholdersOnDeletion(string $projectId, string $changeRequestCode, string $deletedBy): void
    {
        $project = Project::find($projectId);
        if (!$project) return;
        
        $stakeholders = $project->users()->get();
        
        foreach ($stakeholders as $user) {
            if ($user->id !== $deletedBy) {
                $this->notificationService->createNotification([
                    'user_id' => $user->id,
                    'priority' => Notification::PRIORITY_LOW,
                    'title' => 'Change Request Deleted',
                    'body' => "Change Request {$changeRequestCode} has been deleted.",
                    'link_url' => "/projects/{$projectId}/change-requests",
                    'channel' => Notification::CHANNEL_INAPP
                ]);
            }
        }
    }

    /**
     * Update project metrics
     */
    private function updateProjectMetrics(string $projectId): void
    {
        // Trigger project metrics recalculation
        EventBus::publish('Project.Metrics.Updated', [
            'entityId' => $projectId,
            'projectId' => $projectId,
            'actorId' => AuthHelper::id(),
            'changedFields' => ['change_requests_count'],
            'timestamp' => now()->toISOString(),
            'eventId' => uniqid('metrics_', true)
        ]);
    }
}