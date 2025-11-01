<?php

namespace App\Repositories;

use App\Models\ChangeRequest;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ChangeRequestRepository
{
    protected $model;

    public function __construct(ChangeRequest $model)
    {
        $this->model = $model;
    }

    /**
     * Get all change requests with pagination.
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->with(['project', 'creator', 'assignee', 'tenant'])->paginate($perPage);
    }

    /**
     * Get change request by ID.
     */
    public function getById(int $id): ?ChangeRequest
    {
        return $this->model->with(['project', 'creator', 'assignee', 'tenant'])->find($id);
    }

    /**
     * Get change requests by project ID.
     */
    public function getByProjectId(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get change requests by tenant ID.
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get change requests by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get change requests by priority.
     */
    public function getByPriority(string $priority): Collection
    {
        return $this->model->where('priority', $priority)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get change requests by creator.
     */
    public function getByCreator(int $creatorId): Collection
    {
        return $this->model->where('created_by', $creatorId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get change requests by assignee.
     */
    public function getByAssignee(int $assigneeId): Collection
    {
        return $this->model->where('assigned_to', $assigneeId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Create a new change request.
     */
    public function create(array $data): ChangeRequest
    {
        $changeRequest = $this->model->create($data);

        Log::info('Change request created', [
            'change_request_id' => $changeRequest->id,
            'title' => $changeRequest->title,
            'project_id' => $changeRequest->project_id,
            'created_by' => $changeRequest->created_by
        ]);

        return $changeRequest->load(['project', 'creator', 'assignee', 'tenant']);
    }

    /**
     * Update change request.
     */
    public function update(int $id, array $data): ?ChangeRequest
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return null;
        }

        $changeRequest->update($data);

        Log::info('Change request updated', [
            'change_request_id' => $changeRequest->id,
            'title' => $changeRequest->title,
            'project_id' => $changeRequest->project_id
        ]);

        return $changeRequest->load(['project', 'creator', 'assignee', 'tenant']);
    }

    /**
     * Delete change request.
     */
    public function delete(int $id): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        $changeRequest->delete();

        Log::info('Change request deleted', [
            'change_request_id' => $id,
            'title' => $changeRequest->title,
            'project_id' => $changeRequest->project_id
        ]);

        return true;
    }

    /**
     * Soft delete change request.
     */
    public function softDelete(int $id): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        $changeRequest->delete();

        Log::info('Change request soft deleted', [
            'change_request_id' => $id,
            'title' => $changeRequest->title,
            'project_id' => $changeRequest->project_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted change request.
     */
    public function restore(int $id): bool
    {
        $changeRequest = $this->model->withTrashed()->find($id);

        if (!$changeRequest) {
            return false;
        }

        $changeRequest->restore();

        Log::info('Change request restored', [
            'change_request_id' => $id,
            'title' => $changeRequest->title,
            'project_id' => $changeRequest->project_id
        ]);

        return true;
    }

    /**
     * Get pending change requests.
     */
    public function getPending(): Collection
    {
        return $this->model->where('status', 'pending')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get approved change requests.
     */
    public function getApproved(): Collection
    {
        return $this->model->where('status', 'approved')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get rejected change requests.
     */
    public function getRejected(): Collection
    {
        return $this->model->where('status', 'rejected')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get in review change requests.
     */
    public function getInReview(): Collection
    {
        return $this->model->where('status', 'in_review')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get high priority change requests.
     */
    public function getHighPriority(): Collection
    {
        return $this->model->where('priority', 'high')
                          ->where('status', '!=', 'completed')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get recent change requests.
     */
    public function getRecent(int $days = 7): Collection
    {
        return $this->model->where('created_at', '>=', now()->subDays($days))
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Update change request status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        $changeRequest->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('Change request status updated', [
            'change_request_id' => $id,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Approve change request.
     */
    public function approve(int $id, int $approvedBy, string $comments = null): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        $changeRequest->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'approval_comments' => $comments
        ]);

        Log::info('Change request approved', [
            'change_request_id' => $id,
            'approved_by' => $approvedBy
        ]);

        return true;
    }

    /**
     * Reject change request.
     */
    public function reject(int $id, int $rejectedBy, string $reason = null): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        $changeRequest->update([
            'status' => 'rejected',
            'rejected_by' => $rejectedBy,
            'rejected_at' => now(),
            'rejection_reason' => $reason
        ]);

        Log::info('Change request rejected', [
            'change_request_id' => $id,
            'rejected_by' => $rejectedBy,
            'reason' => $reason
        ]);

        return true;
    }

    /**
     * Assign change request.
     */
    public function assign(int $id, int $assigneeId): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        $changeRequest->update([
            'assigned_to' => $assigneeId,
            'assigned_at' => now()
        ]);

        Log::info('Change request assigned', [
            'change_request_id' => $id,
            'assigned_to' => $assigneeId
        ]);

        return true;
    }

    /**
     * Add comment to change request.
     */
    public function addComment(int $id, int $userId, string $comment): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        // This would typically use a comments relationship
        // For now, we'll update the comments field
        $comments = $changeRequest->comments ?? [];
        $comments[] = [
            'user_id' => $userId,
            'comment' => $comment,
            'created_at' => now()->toISOString()
        ];

        $changeRequest->update([
            'comments' => $comments
        ]);

        Log::info('Comment added to change request', [
            'change_request_id' => $id,
            'user_id' => $userId
        ]);

        return true;
    }

    /**
     * Get change request statistics.
     */
    public function getStatistics(int $projectId = null): array
    {
        $query = $this->model->query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return [
            'total_change_requests' => $query->count(),
            'pending_change_requests' => $query->where('status', 'pending')->count(),
            'approved_change_requests' => $query->where('status', 'approved')->count(),
            'rejected_change_requests' => $query->where('status', 'rejected')->count(),
            'in_review_change_requests' => $query->where('status', 'in_review')->count(),
            'high_priority_change_requests' => $query->where('priority', 'high')->count(),
            'medium_priority_change_requests' => $query->where('priority', 'medium')->count(),
            'low_priority_change_requests' => $query->where('priority', 'low')->count(),
            'recent_change_requests' => $query->where('created_at', '>=', now()->subDays(7))->count()
        ];
    }

    /**
     * Search change requests.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('title', 'like', '%' . $term . '%')
              ->orWhere('description', 'like', '%' . $term . '%');
        })->with(['project', 'creator', 'assignee', 'tenant'])
          ->orderBy('created_at', 'desc')
          ->limit($limit)
          ->get();
    }

    /**
     * Get change requests by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Bulk update change requests.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('Change requests bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete change requests.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('Change requests bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get change request timeline.
     */
    public function getTimeline(int $id): array
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return [];
        }

        $timeline = [];

        // Add creation
        $timeline[] = [
            'type' => 'created',
            'date' => $changeRequest->created_at,
            'title' => 'Change Request Created',
            'description' => 'Change request ' . $changeRequest->title . ' was created'
        ];

        // Add assignment
        if ($changeRequest->assigned_to && $changeRequest->assigned_at) {
            $timeline[] = [
                'type' => 'assigned',
                'date' => $changeRequest->assigned_at,
                'title' => 'Change Request Assigned',
                'description' => 'Change request assigned to user'
            ];
        }

        // Add status changes
        if ($changeRequest->status_updated_at) {
            $timeline[] = [
                'type' => 'status_changed',
                'date' => $changeRequest->status_updated_at,
                'title' => 'Status Changed',
                'description' => 'Status changed to ' . $changeRequest->status
            ];
        }

        // Add approval
        if ($changeRequest->approved_at) {
            $timeline[] = [
                'type' => 'approved',
                'date' => $changeRequest->approved_at,
                'title' => 'Change Request Approved',
                'description' => 'Change request was approved'
            ];
        }

        // Add rejection
        if ($changeRequest->rejected_at) {
            $timeline[] = [
                'type' => 'rejected',
                'date' => $changeRequest->rejected_at,
                'title' => 'Change Request Rejected',
                'description' => 'Change request was rejected'
            ];
        }

        // Sort by date
        usort($timeline, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
    }

    /**
     * Get change request by external ID.
     */
    public function getByExternalId(string $externalId): ?ChangeRequest
    {
        return $this->model->where('external_id', $externalId)->first();
    }

    /**
     * Get change request by reference.
     */
    public function getByReference(string $reference): ?ChangeRequest
    {
        return $this->model->where('reference', $reference)->first();
    }

    /**
     * Get change request comments.
     */
    public function getComments(int $id): array
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return [];
        }

        return $changeRequest->comments ?? [];
    }

    /**
     * Get change request attachments.
     */
    public function getAttachments(int $id): array
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return [];
        }

        return $changeRequest->attachments ?? [];
    }

    /**
     * Add attachment to change request.
     */
    public function addAttachment(int $id, string $filePath, string $fileName): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        $attachments = $changeRequest->attachments ?? [];
        $attachments[] = [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'uploaded_at' => now()->toISOString()
        ];

        $changeRequest->update([
            'attachments' => $attachments
        ]);

        Log::info('Attachment added to change request', [
            'change_request_id' => $id,
            'file_name' => $fileName
        ]);

        return true;
    }

    /**
     * Remove attachment from change request.
     */
    public function removeAttachment(int $id, string $filePath): bool
    {
        $changeRequest = $this->model->find($id);

        if (!$changeRequest) {
            return false;
        }

        $attachments = $changeRequest->attachments ?? [];
        $attachments = array_filter($attachments, function ($attachment) use ($filePath) {
            return $attachment['file_path'] !== $filePath;
        });

        $changeRequest->update([
            'attachments' => array_values($attachments)
        ]);

        Log::info('Attachment removed from change request', [
            'change_request_id' => $id,
            'file_path' => $filePath
        ]);

        return true;
    }
}
