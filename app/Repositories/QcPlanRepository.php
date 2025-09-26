<?php

namespace App\Repositories;

use App\Models\QcPlan;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class QcPlanRepository
{
    protected $model;

    public function __construct(QcPlan $model)
    {
        $this->model = $model;
    }

    /**
     * Get all QC plans with pagination.
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

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
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
     * Get QC plan by ID.
     */
    public function getById(int $id): ?QcPlan
    {
        return $this->model->with(['project', 'creator', 'assignee', 'tenant'])->find($id);
    }

    /**
     * Get QC plans by project ID.
     */
    public function getByProjectId(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get QC plans by tenant ID.
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get QC plans by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get QC plans by type.
     */
    public function getByType(string $type): Collection
    {
        return $this->model->where('type', $type)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get QC plans by creator.
     */
    public function getByCreator(int $creatorId): Collection
    {
        return $this->model->where('created_by', $creatorId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get QC plans by assignee.
     */
    public function getByAssignee(int $assigneeId): Collection
    {
        return $this->model->where('assigned_to', $assigneeId)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Create a new QC plan.
     */
    public function create(array $data): QcPlan
    {
        $qcPlan = $this->model->create($data);

        Log::info('QC Plan created', [
            'qc_plan_id' => $qcPlan->id,
            'name' => $qcPlan->name,
            'project_id' => $qcPlan->project_id,
            'created_by' => $qcPlan->created_by
        ]);

        return $qcPlan->load(['project', 'creator', 'assignee', 'tenant']);
    }

    /**
     * Update QC plan.
     */
    public function update(int $id, array $data): ?QcPlan
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return null;
        }

        $qcPlan->update($data);

        Log::info('QC Plan updated', [
            'qc_plan_id' => $qcPlan->id,
            'name' => $qcPlan->name,
            'project_id' => $qcPlan->project_id
        ]);

        return $qcPlan->load(['project', 'creator', 'assignee', 'tenant']);
    }

    /**
     * Delete QC plan.
     */
    public function delete(int $id): bool
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return false;
        }

        $qcPlan->delete();

        Log::info('QC Plan deleted', [
            'qc_plan_id' => $id,
            'name' => $qcPlan->name,
            'project_id' => $qcPlan->project_id
        ]);

        return true;
    }

    /**
     * Soft delete QC plan.
     */
    public function softDelete(int $id): bool
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return false;
        }

        $qcPlan->delete();

        Log::info('QC Plan soft deleted', [
            'qc_plan_id' => $id,
            'name' => $qcPlan->name,
            'project_id' => $qcPlan->project_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted QC plan.
     */
    public function restore(int $id): bool
    {
        $qcPlan = $this->model->withTrashed()->find($id);

        if (!$qcPlan) {
            return false;
        }

        $qcPlan->restore();

        Log::info('QC Plan restored', [
            'qc_plan_id' => $id,
            'name' => $qcPlan->name,
            'project_id' => $qcPlan->project_id
        ]);

        return true;
    }

    /**
     * Get active QC plans.
     */
    public function getActive(): Collection
    {
        return $this->model->where('status', 'active')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get completed QC plans.
     */
    public function getCompleted(): Collection
    {
        return $this->model->where('status', 'completed')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get pending QC plans.
     */
    public function getPending(): Collection
    {
        return $this->model->where('status', 'pending')
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Get recent QC plans.
     */
    public function getRecent(int $days = 7): Collection
    {
        return $this->model->where('created_at', '>=', now()->subDays($days))
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Update QC plan status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return false;
        }

        $qcPlan->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('QC Plan status updated', [
            'qc_plan_id' => $id,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Assign QC plan.
     */
    public function assign(int $id, int $assigneeId): bool
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return false;
        }

        $qcPlan->update([
            'assigned_to' => $assigneeId,
            'assigned_at' => now()
        ]);

        Log::info('QC Plan assigned', [
            'qc_plan_id' => $id,
            'assigned_to' => $assigneeId
        ]);

        return true;
    }

    /**
     * Get QC plan statistics.
     */
    public function getStatistics(int $projectId = null): array
    {
        $query = $this->model->query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return [
            'total_qc_plans' => $query->count(),
            'active_qc_plans' => $query->where('status', 'active')->count(),
            'completed_qc_plans' => $query->where('status', 'completed')->count(),
            'pending_qc_plans' => $query->where('status', 'pending')->count(),
            'in_progress_qc_plans' => $query->where('status', 'in_progress')->count(),
            'recent_qc_plans' => $query->where('created_at', '>=', now()->subDays(7))->count(),
            'overdue_qc_plans' => $query->where('due_date', '<', now())
                                       ->where('status', '!=', 'completed')
                                       ->count()
        ];
    }

    /**
     * Search QC plans.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', '%' . $term . '%')
              ->orWhere('description', 'like', '%' . $term . '%');
        })->with(['project', 'creator', 'assignee', 'tenant'])
          ->orderBy('created_at', 'desc')
          ->limit($limit)
          ->get();
    }

    /**
     * Get QC plans by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['project', 'creator', 'assignee', 'tenant'])
                          ->get();
    }

    /**
     * Bulk update QC plans.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('QC Plans bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete QC plans.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('QC Plans bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get QC plan timeline.
     */
    public function getTimeline(int $id): array
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return [];
        }

        $timeline = [];

        // Add creation
        $timeline[] = [
            'type' => 'created',
            'date' => $qcPlan->created_at,
            'title' => 'QC Plan Created',
            'description' => 'QC Plan ' . $qcPlan->name . ' was created'
        ];

        // Add assignment
        if ($qcPlan->assigned_to && $qcPlan->assigned_at) {
            $timeline[] = [
                'type' => 'assigned',
                'date' => $qcPlan->assigned_at,
                'title' => 'QC Plan Assigned',
                'description' => 'QC Plan assigned to user'
            ];
        }

        // Add status changes
        if ($qcPlan->status_updated_at) {
            $timeline[] = [
                'type' => 'status_changed',
                'date' => $qcPlan->status_updated_at,
                'title' => 'Status Changed',
                'description' => 'Status changed to ' . $qcPlan->status
            ];
        }

        // Add completion
        if ($qcPlan->completed_at) {
            $timeline[] = [
                'type' => 'completed',
                'date' => $qcPlan->completed_at,
                'title' => 'QC Plan Completed',
                'description' => 'QC Plan was completed'
            ];
        }

        // Sort by date
        usort($timeline, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
    }

    /**
     * Get QC plan by external ID.
     */
    public function getByExternalId(string $externalId): ?QcPlan
    {
        return $this->model->where('external_id', $externalId)->first();
    }

    /**
     * Get QC plan by reference.
     */
    public function getByReference(string $reference): ?QcPlan
    {
        return $this->model->where('reference', $reference)->first();
    }

    /**
     * Get QC plan inspections.
     */
    public function getInspections(int $id): Collection
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return collect();
        }

        // This would typically use a relationship
        // For now, return empty collection
        return collect();
    }

    /**
     * Get QC plan progress.
     */
    public function getProgress(int $id): array
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return [];
        }

        // This would calculate progress based on inspections
        // For now, return basic progress
        $progress = 0;
        if ($qcPlan->status === 'completed') {
            $progress = 100;
        } elseif ($qcPlan->status === 'in_progress') {
            $progress = 50;
        }

        return [
            'status' => $qcPlan->status,
            'progress_percentage' => $progress,
            'estimated_completion' => $qcPlan->due_date?->toDateString()
        ];
    }

    /**
     * Get QC plan checklist.
     */
    public function getChecklist(int $id): array
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return [];
        }

        return $qcPlan->checklist ?? [];
    }

    /**
     * Update QC plan checklist.
     */
    public function updateChecklist(int $id, array $checklist): bool
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return false;
        }

        $qcPlan->update([
            'checklist' => $checklist
        ]);

        Log::info('QC Plan checklist updated', [
            'qc_plan_id' => $id
        ]);

        return true;
    }

    /**
     * Get QC plan requirements.
     */
    public function getRequirements(int $id): array
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return [];
        }

        return $qcPlan->requirements ?? [];
    }

    /**
     * Update QC plan requirements.
     */
    public function updateRequirements(int $id, array $requirements): bool
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return false;
        }

        $qcPlan->update([
            'requirements' => $requirements
        ]);

        Log::info('QC Plan requirements updated', [
            'qc_plan_id' => $id
        ]);

        return true;
    }

    /**
     * Get QC plan standards.
     */
    public function getStandards(int $id): array
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return [];
        }

        return $qcPlan->standards ?? [];
    }

    /**
     * Update QC plan standards.
     */
    public function updateStandards(int $id, array $standards): bool
    {
        $qcPlan = $this->model->find($id);

        if (!$qcPlan) {
            return false;
        }

        $qcPlan->update([
            'standards' => $standards
        ]);

        Log::info('QC Plan standards updated', [
            'qc_plan_id' => $id
        ]);

        return true;
    }
}
