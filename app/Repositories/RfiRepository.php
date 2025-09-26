<?php

namespace App\Repositories;

use App\Models\Rfi;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class RfiRepository
{
    protected $model;

    public function __construct(Rfi $model)
    {
        $this->model = $model;
    }

    /**
     * Get all RFIs with pagination.
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
                $q->where('subject', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('question', 'like', '%' . $filters['search'] . '%');
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
     * Get RFI by ID.
     */
    public function getById(int $id): ?Rfi
    {
        return $this->model->with(['project', 'creator', 'assignee', 'tenant'])->find($id);
    }

    /**
     * Get RFIs by project ID.
     */
    public function getByProjectId(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get RFIs by tenant ID.
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get RFIs by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get RFIs by priority.
     */
    public function getByPriority(string $priority): Collection
    {
        return $this->model->where('priority', $priority)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get RFIs by creator.
     */
    public function getByCreator(int $creatorId): Collection
    {
        return $this->model->where('created_by', $creatorId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get RFIs by assignee.
     */
    public function getByAssignee(int $assigneeId): Collection
    {
        return $this->model->where('assigned_to', $assigneeId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Create a new RFI.
     */
    public function create(array $data): Rfi
    {
        $rfi = $this->model->create($data);

        Log::info('RFI created', [
            'rfi_id' => $rfi->id,
            'subject' => $rfi->subject,
            'project_id' => $rfi->project_id,
            'created_by' => $rfi->created_by
        ]);

        return $rfi->load(['project', 'creator', 'assignee', 'tenant']);
    }

    /**
     * Update RFI.
     */
    public function update(int $id, array $data): ?Rfi
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return null;
        }

        $rfi->update($data);

        Log::info('RFI updated', [
            'rfi_id' => $rfi->id,
            'subject' => $rfi->subject,
            'project_id' => $rfi->project_id
        ]);

        return $rfi->load(['project', 'creator', 'assignee', 'tenant']);
    }

    /**
     * Delete RFI.
     */
    public function delete(int $id): bool
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return false;
        }

        $rfi->delete();

        Log::info('RFI deleted', [
            'rfi_id' => $id,
            'subject' => $rfi->subject,
            'project_id' => $rfi->project_id
        ]);

        return true;
    }

    /**
     * Soft delete RFI.
     */
    public function softDelete(int $id): bool
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return false;
        }

        $rfi->delete();

        Log::info('RFI soft deleted', [
            'rfi_id' => $id,
            'subject' => $rfi->subject,
            'project_id' => $rfi->project_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted RFI.
     */
    public function restore(int $id): bool
    {
        $rfi = $this->model->withTrashed()->find($id);

        if (!$rfi) {
            return false;
        }

        $rfi->restore();

        Log::info('RFI restored', [
            'rfi_id' => $id,
            'subject' => $rfi->subject,
            'project_id' => $rfi->project_id
        ]);

        return true;
    }

    /**
     * Get pending RFIs.
     */
    public function getPending(): Collection
    {
        return $this->model->where('status', 'pending')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get answered RFIs.
     */
    public function getAnswered(): Collection
    {
        return $this->model->where('status', 'answered')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get closed RFIs.
     */
    public function getClosed(): Collection
    {
        return $this->model->where('status', 'closed')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get high priority RFIs.
     */
    public function getHighPriority(): Collection
    {
        return $this->model->where('priority', 'high')
                          ->where('status', '!=', 'closed')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get recent RFIs.
     */
    public function getRecent(int $days = 7): Collection
    {
        return $this->model->where('created_at', '>=', now()->subDays($days))
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Get overdue RFIs.
     */
    public function getOverdue(): Collection
    {
        return $this->model->where('due_date', '<', now())
                          ->where('status', '!=', 'closed')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Update RFI status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return false;
        }

        $rfi->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('RFI status updated', [
            'rfi_id' => $id,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Answer RFI.
     */
    public function answer(int $id, int $answeredBy, string $answer): bool
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return false;
        }

        $rfi->update([
            'status' => 'answered',
            'answer' => $answer,
            'answered_by' => $answeredBy,
            'answered_at' => now()
        ]);

        Log::info('RFI answered', [
            'rfi_id' => $id,
            'answered_by' => $answeredBy
        ]);

        return true;
    }

    /**
     * Close RFI.
     */
    public function close(int $id, int $closedBy, string $reason = null): bool
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return false;
        }

        $rfi->update([
            'status' => 'closed',
            'closed_by' => $closedBy,
            'closed_at' => now(),
            'closure_reason' => $reason
        ]);

        Log::info('RFI closed', [
            'rfi_id' => $id,
            'closed_by' => $closedBy,
            'reason' => $reason
        ]);

        return true;
    }

    /**
     * Assign RFI.
     */
    public function assign(int $id, int $assigneeId): bool
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return false;
        }

        $rfi->update([
            'assigned_to' => $assigneeId,
            'assigned_at' => now()
        ]);

        Log::info('RFI assigned', [
            'rfi_id' => $id,
            'assigned_to' => $assigneeId
        ]);

        return true;
    }

    /**
     * Get RFI statistics.
     */
    public function getStatistics(int $projectId = null): array
    {
        $query = $this->model->query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return [
            'total_rfis' => $query->count(),
            'pending_rfis' => $query->where('status', 'pending')->count(),
            'answered_rfis' => $query->where('status', 'answered')->count(),
            'closed_rfis' => $query->where('status', 'closed')->count(),
            'high_priority_rfis' => $query->where('priority', 'high')->count(),
            'medium_priority_rfis' => $query->where('priority', 'medium')->count(),
            'low_priority_rfis' => $query->where('priority', 'low')->count(),
            'overdue_rfis' => $query->where('due_date', '<', now())
                                   ->where('status', '!=', 'closed')
                                   ->count(),
            'recent_rfis' => $query->where('created_at', '>=', now()->subDays(7))->count()
        ];
    }

    /**
     * Search RFIs.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('subject', 'like', '%' . $term . '%')
              ->orWhere('question', 'like', '%' . $term . '%');
        })->with(['project', 'creator', 'assignee', 'tenant'])
          ->orderBy('created_at', 'desc')
          ->limit($limit)
          ->get();
    }

    /**
     * Get RFIs by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Bulk update RFIs.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('RFIs bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete RFIs.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('RFIs bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get RFI timeline.
     */
    public function getTimeline(int $id): array
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return [];
        }

        $timeline = [];

        // Add creation
        $timeline[] = [
            'type' => 'created',
            'date' => $rfi->created_at,
            'title' => 'RFI Created',
            'description' => 'RFI ' . $rfi->subject . ' was created'
        ];

        // Add assignment
        if ($rfi->assigned_to && $rfi->assigned_at) {
            $timeline[] = [
                'type' => 'assigned',
                'date' => $rfi->assigned_at,
                'title' => 'RFI Assigned',
                'description' => 'RFI assigned to user'
            ];
        }

        // Add status changes
        if ($rfi->status_updated_at) {
            $timeline[] = [
                'type' => 'status_changed',
                'date' => $rfi->status_updated_at,
                'title' => 'Status Changed',
                'description' => 'Status changed to ' . $rfi->status
            ];
        }

        // Add answer
        if ($rfi->answered_at) {
            $timeline[] = [
                'type' => 'answered',
                'date' => $rfi->answered_at,
                'title' => 'RFI Answered',
                'description' => 'RFI was answered'
            ];
        }

        // Add closure
        if ($rfi->closed_at) {
            $timeline[] = [
                'type' => 'closed',
                'date' => $rfi->closed_at,
                'title' => 'RFI Closed',
                'description' => 'RFI was closed'
            ];
        }

        // Sort by date
        usort($timeline, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
    }

    /**
     * Get RFI by external ID.
     */
    public function getByExternalId(string $externalId): ?Rfi
    {
        return $this->model->where('external_id', $externalId)->first();
    }

    /**
     * Get RFI by reference.
     */
    public function getByReference(string $reference): ?Rfi
    {
        return $this->model->where('reference', $reference)->first();
    }

    /**
     * Get RFI attachments.
     */
    public function getAttachments(int $id): array
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return [];
        }

        return $rfi->attachments ?? [];
    }

    /**
     * Add attachment to RFI.
     */
    public function addAttachment(int $id, string $filePath, string $fileName): bool
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return false;
        }

        $attachments = $rfi->attachments ?? [];
        $attachments[] = [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'uploaded_at' => now()->toISOString()
        ];

        $rfi->update([
            'attachments' => $attachments
        ]);

        Log::info('Attachment added to RFI', [
            'rfi_id' => $id,
            'file_name' => $fileName
        ]);

        return true;
    }

    /**
     * Remove attachment from RFI.
     */
    public function removeAttachment(int $id, string $filePath): bool
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return false;
        }

        $attachments = $rfi->attachments ?? [];
        $attachments = array_filter($attachments, function ($attachment) use ($filePath) {
            return $attachment['file_path'] !== $filePath;
        });

        $rfi->update([
            'attachments' => array_values($attachments)
        ]);

        Log::info('Attachment removed from RFI', [
            'rfi_id' => $id,
            'file_path' => $filePath
        ]);

        return true;
    }

    /**
     * Get RFI SLA status.
     */
    public function getSlaStatus(int $id): array
    {
        $rfi = $this->model->find($id);

        if (!$rfi) {
            return [];
        }

        $dueDate = $rfi->due_date;
        $now = now();

        if ($dueDate) {
            $isOverdue = $now->gt($dueDate);
            $daysRemaining = $isOverdue ? 0 : $now->diffInDays($dueDate);
            $daysOverdue = $isOverdue ? $now->diffInDays($dueDate) : 0;
        } else {
            $isOverdue = false;
            $daysRemaining = null;
            $daysOverdue = 0;
        }

        return [
            'due_date' => $dueDate,
            'is_overdue' => $isOverdue,
            'days_remaining' => $daysRemaining,
            'days_overdue' => $daysOverdue,
            'sla_status' => $isOverdue ? 'overdue' : ($daysRemaining <= 1 ? 'urgent' : 'normal')
        ];
    }

    /**
     * Get RFIs by SLA status.
     */
    public function getBySlaStatus(string $slaStatus): Collection
    {
        $query = $this->model->where('status', '!=', 'closed');

        if ($slaStatus === 'overdue') {
            $query->where('due_date', '<', now());
        } elseif ($slaStatus === 'urgent') {
            $query->where('due_date', '<=', now()->addDay())
                  ->where('due_date', '>=', now());
        } elseif ($slaStatus === 'normal') {
            $query->where('due_date', '>', now()->addDay());
        }

        return $query->with(['project', 'creator', 'assignee', 'tenant'])->get();
    }
}
