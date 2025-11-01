<?php

namespace App\Repositories;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentRepository
{
    protected $model;

    public function __construct(Document $model)
    {
        $this->model = $model;
    }

    /**
     * Get all documents with pagination.
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

        if (isset($filters['mime_type'])) {
            $query->where('mime_type', 'like', '%' . $filters['mime_type'] . '%');
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        return $query->with(['project', 'creator', 'tenant'])->paginate($perPage);
    }

    /**
     * Get document by ID.
     */
    public function getById(int $id): ?Document
    {
        return $this->model->with(['project', 'creator', 'tenant'])->find($id);
    }

    /**
     * Get documents by project ID.
     */
    public function getByProjectId(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Get documents by tenant ID.
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Get documents by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Get documents by type.
     */
    public function getByType(string $type): Collection
    {
        return $this->model->where('type', $type)
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Get documents by MIME type.
     */
    public function getByMimeType(string $mimeType): Collection
    {
        return $this->model->where('mime_type', $mimeType)
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Create a new document.
     */
    public function create(array $data): Document
    {
        $document = $this->model->create($data);

        Log::info('Document created', [
            'document_id' => $document->id,
            'name' => $document->name,
            'project_id' => $document->project_id,
            'tenant_id' => $document->tenant_id
        ]);

        return $document->load(['project', 'creator', 'tenant']);
    }

    /**
     * Update document.
     */
    public function update(int $id, array $data): ?Document
    {
        $document = $this->model->find($id);

        if (!$document) {
            return null;
        }

        $document->update($data);

        Log::info('Document updated', [
            'document_id' => $document->id,
            'name' => $document->name,
            'project_id' => $document->project_id
        ]);

        return $document->load(['project', 'creator', 'tenant']);
    }

    /**
     * Delete document.
     */
    public function delete(int $id): bool
    {
        $document = $this->model->find($id);

        if (!$document) {
            return false;
        }

        // Delete file from storage
        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $document->delete();

        Log::info('Document deleted', [
            'document_id' => $id,
            'name' => $document->name,
            'project_id' => $document->project_id
        ]);

        return true;
    }

    /**
     * Soft delete document.
     */
    public function softDelete(int $id): bool
    {
        $document = $this->model->find($id);

        if (!$document) {
            return false;
        }

        $document->delete();

        Log::info('Document soft deleted', [
            'document_id' => $id,
            'name' => $document->name,
            'project_id' => $document->project_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted document.
     */
    public function restore(int $id): bool
    {
        $document = $this->model->withTrashed()->find($id);

        if (!$document) {
            return false;
        }

        $document->restore();

        Log::info('Document restored', [
            'document_id' => $id,
            'name' => $document->name,
            'project_id' => $document->project_id
        ]);

        return true;
    }

    /**
     * Get approved documents.
     */
    public function getApproved(): Collection
    {
        return $this->model->where('status', 'approved')
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Get pending documents.
     */
    public function getPending(): Collection
    {
        return $this->model->where('status', 'pending')
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Get draft documents.
     */
    public function getDraft(): Collection
    {
        return $this->model->where('status', 'draft')
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Get recent documents.
     */
    public function getRecent(int $days = 7): Collection
    {
        return $this->model->where('created_at', '>=', now()->subDays($days))
                          ->with(['project', 'creator', 'tenant'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    /**
     * Get large documents.
     */
    public function getLarge(int $sizeInMB = 10): Collection
    {
        $sizeInBytes = $sizeInMB * 1024 * 1024;

        return $this->model->where('file_size', '>', $sizeInBytes)
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Update document status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $document = $this->model->find($id);

        if (!$document) {
            return false;
        }

        $document->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('Document status updated', [
            'document_id' => $id,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Approve document.
     */
    public function approve(int $id, int $approvedBy): bool
    {
        $document = $this->model->find($id);

        if (!$document) {
            return false;
        }

        $document->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now()
        ]);

        Log::info('Document approved', [
            'document_id' => $id,
            'approved_by' => $approvedBy
        ]);

        return true;
    }

    /**
     * Reject document.
     */
    public function reject(int $id, int $rejectedBy, string $reason = null): bool
    {
        $document = $this->model->find($id);

        if (!$document) {
            return false;
        }

        $document->update([
            'status' => 'rejected',
            'rejected_by' => $rejectedBy,
            'rejected_at' => now(),
            'rejection_reason' => $reason
        ]);

        Log::info('Document rejected', [
            'document_id' => $id,
            'rejected_by' => $rejectedBy,
            'reason' => $reason
        ]);

        return true;
    }

    /**
     * Get document statistics.
     */
    public function getStatistics(int $projectId = null): array
    {
        $query = $this->model->query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return [
            'total_documents' => $query->count(),
            'approved_documents' => $query->where('status', 'approved')->count(),
            'pending_documents' => $query->where('status', 'pending')->count(),
            'draft_documents' => $query->where('status', 'draft')->count(),
            'rejected_documents' => $query->where('status', 'rejected')->count(),
            'total_size' => $query->sum('file_size'),
            'average_size' => $query->avg('file_size'),
            'recent_documents' => $query->where('created_at', '>=', now()->subDays(7))->count()
        ];
    }

    /**
     * Search documents.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', '%' . $term . '%')
              ->orWhere('description', 'like', '%' . $term . '%');
        })->with(['project', 'creator', 'tenant'])
          ->limit($limit)
          ->get();
    }

    /**
     * Get documents by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['project', 'creator', 'tenant'])
                          ->get();
    }

    /**
     * Bulk update documents.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('Documents bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete documents.
     */
    public function bulkDelete(array $ids): int
    {
        $documents = $this->model->whereIn('id', $ids)->get();

        // Delete files from storage
        foreach ($documents as $document) {
            if ($document->file_path && Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }
        }

        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('Documents bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get document versions.
     */
    public function getVersions(int $id): Collection
    {
        $document = $this->model->find($id);

        if (!$document) {
            return collect();
        }

        return $this->model->where('name', $document->name)
                          ->where('project_id', $document->project_id)
                          ->orderBy('version', 'desc')
                          ->get();
    }

    /**
     * Create new version.
     */
    public function createVersion(int $id, array $data): ?Document
    {
        $originalDocument = $this->model->find($id);

        if (!$originalDocument) {
            return null;
        }

        // Get next version number
        $lastVersion = $this->model->where('name', $originalDocument->name)
                                  ->where('project_id', $originalDocument->project_id)
                                  ->max('version');

        $nextVersion = $lastVersion ? $lastVersion + 1 : 1;

        $data = array_merge($data, [
            'name' => $originalDocument->name,
            'project_id' => $originalDocument->project_id,
            'tenant_id' => $originalDocument->tenant_id,
            'version' => $nextVersion,
            'status' => 'draft'
        ]);

        return $this->create($data);
    }

    /**
     * Get document by name and project.
     */
    public function getByNameAndProject(string $name, int $projectId): ?Document
    {
        return $this->model->where('name', $name)
                          ->where('project_id', $projectId)
                          ->orderBy('version', 'desc')
                          ->first();
    }

    /**
     * Get document file content.
     */
    public function getFileContent(int $id): ?string
    {
        $document = $this->model->find($id);

        if (!$document || !$document->file_path) {
            return null;
        }

        if (!Storage::exists($document->file_path)) {
            return null;
        }

        return Storage::get($document->file_path);
    }

    /**
     * Get document file URL.
     */
    public function getFileUrl(int $id): ?string
    {
        $document = $this->model->find($id);

        if (!$document || !$document->file_path) {
            return null;
        }

        return Storage::url($document->file_path);
    }

    /**
     * Download document.
     */
    public function download(int $id): ?string
    {
        $document = $this->model->find($id);

        if (!$document || !$document->file_path) {
            return null;
        }

        if (!Storage::exists($document->file_path)) {
            return null;
        }

        // Update download count
        $document->increment('download_count');
        $document->update(['last_downloaded_at' => now()]);

        Log::info('Document downloaded', [
            'document_id' => $id,
            'name' => $document->name
        ]);

        return $document->file_path;
    }

    /**
     * Get document metadata.
     */
    public function getMetadata(int $id): array
    {
        $document = $this->model->find($id);

        if (!$document) {
            return [];
        }

        return [
            'id' => $document->id,
            'name' => $document->name,
            'description' => $document->description,
            'file_size' => $document->file_size,
            'mime_type' => $document->mime_type,
            'version' => $document->version,
            'status' => $document->status,
            'created_at' => $document->created_at,
            'updated_at' => $document->updated_at,
            'download_count' => $document->download_count,
            'last_downloaded_at' => $document->last_downloaded_at
        ];
    }
}
