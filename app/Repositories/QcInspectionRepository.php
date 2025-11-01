<?php

namespace App\Repositories;

use App\Models\QcInspection;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class QcInspectionRepository
{
    protected $model;

    public function __construct(QcInspection $model)
    {
        $this->model = $model;
    }

    /**
     * Get all QC inspections with pagination.
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

        if (isset($filters['inspector_id'])) {
            $query->where('inspector_id', $filters['inspector_id']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['date_from'])) {
            $query->where('inspection_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('inspection_date', '<=', $filters['date_to']);
        }

        return $query->with(['project', 'inspector', 'tenant'])->paginate($perPage);
    }

    /**
     * Get QC inspection by ID.
     */
    public function getById(int $id): ?QcInspection
    {
        return $this->model->with(['project', 'inspector', 'tenant'])->find($id);
    }

    /**
     * Get QC inspections by project ID.
     */
    public function getByProjectId(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Get QC inspections by tenant ID.
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Get QC inspections by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Get QC inspections by type.
     */
    public function getByType(string $type): Collection
    {
        return $this->model->where('type', $type)
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Get QC inspections by inspector.
     */
    public function getByInspector(int $inspectorId): Collection
    {
        return $this->model->where('inspector_id', $inspectorId)
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Create a new QC inspection.
     */
    public function create(array $data): QcInspection
    {
        $qcInspection = $this->model->create($data);

        Log::info('QC Inspection created', [
            'qc_inspection_id' => $qcInspection->id,
            'name' => $qcInspection->name,
            'project_id' => $qcInspection->project_id,
            'inspector_id' => $qcInspection->inspector_id
        ]);

        return $qcInspection->load(['project', 'inspector', 'tenant']);
    }

    /**
     * Update QC inspection.
     */
    public function update(int $id, array $data): ?QcInspection
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return null;
        }

        $qcInspection->update($data);

        Log::info('QC Inspection updated', [
            'qc_inspection_id' => $qcInspection->id,
            'name' => $qcInspection->name,
            'project_id' => $qcInspection->project_id
        ]);

        return $qcInspection->load(['project', 'inspector', 'tenant']);
    }

    /**
     * Delete QC inspection.
     */
    public function delete(int $id): bool
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return false;
        }

        $qcInspection->delete();

        Log::info('QC Inspection deleted', [
            'qc_inspection_id' => $id,
            'name' => $qcInspection->name,
            'project_id' => $qcInspection->project_id
        ]);

        return true;
    }

    /**
     * Soft delete QC inspection.
     */
    public function softDelete(int $id): bool
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return false;
        }

        $qcInspection->delete();

        Log::info('QC Inspection soft deleted', [
            'qc_inspection_id' => $id,
            'name' => $qcInspection->name,
            'project_id' => $qcInspection->project_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted QC inspection.
     */
    public function restore(int $id): bool
    {
        $qcInspection = $this->model->withTrashed()->find($id);

        if (!$qcInspection) {
            return false;
        }

        $qcInspection->restore();

        Log::info('QC Inspection restored', [
            'qc_inspection_id' => $id,
            'name' => $qcInspection->name,
            'project_id' => $qcInspection->project_id
        ]);

        return true;
    }

    /**
     * Get pending QC inspections.
     */
    public function getPending(): Collection
    {
        return $this->model->where('status', 'pending')
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Get completed QC inspections.
     */
    public function getCompleted(): Collection
    {
        return $this->model->where('status', 'completed')
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Get failed QC inspections.
     */
    public function getFailed(): Collection
    {
        return $this->model->where('status', 'failed')
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Get recent QC inspections.
     */
    public function getRecent(int $days = 7): Collection
    {
        return $this->model->where('created_at', '>=', now()->subDays($days))
                          ->with(['project', 'inspector', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Get QC inspections by date range.
     */
    public function getByDateRange($startDate, $endDate): Collection
    {
        return $this->model->whereBetween('inspection_date', [$startDate, $endDate])
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Update QC inspection status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return false;
        }

        $qcInspection->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('QC Inspection status updated', [
            'qc_inspection_id' => $id,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Complete QC inspection.
     */
    public function complete(int $id, array $results): bool
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return false;
        }

        $qcInspection->update([
            'status' => 'completed',
            'results' => $results,
            'completed_at' => now()
        ]);

        Log::info('QC Inspection completed', [
            'qc_inspection_id' => $id
        ]);

        return true;
    }

    /**
     * Get QC inspection statistics.
     */
    public function getStatistics(int $projectId = null): array
    {
        $query = $this->model->query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return [
            'total_qc_inspections' => $query->count(),
            'pending_qc_inspections' => $query->where('status', 'pending')->count(),
            'completed_qc_inspections' => $query->where('status', 'completed')->count(),
            'failed_qc_inspections' => $query->where('status', 'failed')->count(),
            'in_progress_qc_inspections' => $query->where('status', 'in_progress')->count(),
            'recent_qc_inspections' => $query->where('created_at', '>=', now()->subDays(7))->count(),
            'overdue_qc_inspections' => $query->where('inspection_date', '<', now())
                                             ->where('status', '!=', 'completed')
                                             ->count()
        ];
    }

    /**
     * Search QC inspections.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', '%' . $term . '%')
              ->orWhere('description', 'like', '%' . $term . '%');
        })->with(['project', 'inspector', 'tenant'])
          ->orderBy('created_at', 'desc')
          ->limit($limit)
          ->get();
    }

    /**
     * Get QC inspections by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['project', 'inspector', 'tenant'])
                          ->get();
    }

    /**
     * Bulk update QC inspections.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('QC Inspections bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete QC inspections.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('QC Inspections bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get QC inspection timeline.
     */
    public function getTimeline(int $id): array
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return [];
        }

        $timeline = [];

        // Add creation
        $timeline[] = [
            'type' => 'created',
            'date' => $qcInspection->created_at,
            'title' => 'QC Inspection Created',
            'description' => 'QC Inspection ' . $qcInspection->name . ' was created'
        ];

        // Add status changes
        if ($qcInspection->status_updated_at) {
            $timeline[] = [
                'type' => 'status_changed',
                'date' => $qcInspection->status_updated_at,
                'title' => 'Status Changed',
                'description' => 'Status changed to ' . $qcInspection->status
            ];
        }

        // Add completion
        if ($qcInspection->completed_at) {
            $timeline[] = [
                'type' => 'completed',
                'date' => $qcInspection->completed_at,
                'title' => 'QC Inspection Completed',
                'description' => 'QC Inspection was completed'
            ];
        }

        // Sort by date
        usort($timeline, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
    }

    /**
     * Get QC inspection by external ID.
     */
    public function getByExternalId(string $externalId): ?QcInspection
    {
        return $this->model->where('external_id', $externalId)->first();
    }

    /**
     * Get QC inspection by reference.
     */
    public function getByReference(string $reference): ?QcInspection
    {
        return $this->model->where('reference', $reference)->first();
    }

    /**
     * Get QC inspection results.
     */
    public function getResults(int $id): array
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return [];
        }

        return $qcInspection->results ?? [];
    }

    /**
     * Update QC inspection results.
     */
    public function updateResults(int $id, array $results): bool
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return false;
        }

        $qcInspection->update([
            'results' => $results
        ]);

        Log::info('QC Inspection results updated', [
            'qc_inspection_id' => $id
        ]);

        return true;
    }

    /**
     * Get QC inspection checklist.
     */
    public function getChecklist(int $id): array
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return [];
        }

        return $qcInspection->checklist ?? [];
    }

    /**
     * Update QC inspection checklist.
     */
    public function updateChecklist(int $id, array $checklist): bool
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return false;
        }

        $qcInspection->update([
            'checklist' => $checklist
        ]);

        Log::info('QC Inspection checklist updated', [
            'qc_inspection_id' => $id
        ]);

        return true;
    }

    /**
     * Get QC inspection attachments.
     */
    public function getAttachments(int $id): array
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return [];
        }

        return $qcInspection->attachments ?? [];
    }

    /**
     * Add attachment to QC inspection.
     */
    public function addAttachment(int $id, string $filePath, string $fileName): bool
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return false;
        }

        $attachments = $qcInspection->attachments ?? [];
        $attachments[] = [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'uploaded_at' => now()->toISOString()
        ];

        $qcInspection->update([
            'attachments' => $attachments
        ]);

        Log::info('Attachment added to QC Inspection', [
            'qc_inspection_id' => $id,
            'file_name' => $fileName
        ]);

        return true;
    }

    /**
     * Remove attachment from QC inspection.
     */
    public function removeAttachment(int $id, string $filePath): bool
    {
        $qcInspection = $this->model->find($id);

        if (!$qcInspection) {
            return false;
        }

        $attachments = $qcInspection->attachments ?? [];
        $attachments = array_filter($attachments, function ($attachment) use ($filePath) {
            return $attachment['file_path'] !== $filePath;
        });

        $qcInspection->update([
            'attachments' => array_values($attachments)
        ]);

        Log::info('Attachment removed from QC Inspection', [
            'qc_inspection_id' => $id,
            'file_path' => $filePath
        ]);

        return true;
    }
}
