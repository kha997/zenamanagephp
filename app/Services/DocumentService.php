<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\DocumentManagement\Events\DocumentApprovedForClient;
use Src\DocumentManagement\Events\DocumentCreated;
use Src\DocumentManagement\Events\DocumentDeleted;
use Src\DocumentManagement\Events\DocumentUpdated;
use Src\DocumentManagement\Events\DocumentVersionCreated;
use Src\DocumentManagement\Events\DocumentVersionReverted;
use Src\DocumentManagement\Models\Document;
use Src\DocumentManagement\Models\DocumentVersion;
use Src\Foundation\EventBus;

/**
 * Service xử lý business logic cho Document Management
 */
class DocumentService
{
    /**
     * Lấy danh sách documents theo project
     */
    public function getDocumentsByProject(int $projectId, array $filters = [])
    {
        $query = Document::with(['currentVersion', 'creator', 'project'])
                        ->forProject($projectId);

        // Lọc theo entity type
        if (!empty($filters['entity_type'])) {
            $query->forEntityType($filters['entity_type']);
        }

        // Lọc theo entity
        if (!empty($filters['entity_type']) && !empty($filters['entity_id'])) {
            $query->forEntity($filters['entity_type'], $filters['entity_id']);
        }

        // Lọc theo visibility
        if (!empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        // Lọc theo client approval
        if (isset($filters['client_approved'])) {
            $query->where('client_approved', $filters['client_approved']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Lấy document theo ID
     */
    public function getDocumentById(int $documentId): ?Document
    {
        return Document::with(['versions.creator', 'currentVersion', 'creator', 'project'])
                      ->find($documentId);
    }

    /**
     * Tạo document mới với version đầu tiên
     */
    public function createDocument(array $documentData, UploadedFile $file, int $userId): Document
    {
        return DB::transaction(function () use ($documentData, $file, $userId) {
            // Tạo document
            $document = Document::create([
                'name' => $documentData['name'],
                'description' => $documentData['description'] ?? null,
                'project_id' => $documentData['project_id'],
                'category' => $documentData['category'] ?? 'general',
                'created_by' => $userId,
            ]);

            // Tạo version đầu tiên
            $version = $this->createDocumentVersion(
                $document,
                $file,
                $documentData['comment'] ?? 'Initial version',
                $userId
            );

            // Cập nhật current_version_id
            $document->update(['current_version_id' => $version->id]);

            // Dispatch events
            EventBus::dispatch(new DocumentCreated($document, $userId));
            EventBus::dispatch(new DocumentVersionCreated($version, $userId));

            return $document->fresh(['currentVersion', 'creator']);
        });
    }

    /**
     * Cập nhật thông tin document
     */
    public function updateDocument(int $documentId, array $data, int $userId): Document
    {
        $document = Document::findOrFail($documentId);
        
        $oldData = $document->toArray();
        $document->update(array_merge($data, ['updated_by' => $userId]));
        
        // Dispatch event
        EventBus::dispatch(new DocumentUpdated($document, $userId, $oldData));
        
        return $document->fresh(['currentVersion', 'creator']);
    }

    /**
     * Tạo version mới cho document
     */
    public function createNewVersion(int $documentId, UploadedFile $file, string $comment, int $userId): DocumentVersion
    {
        return DB::transaction(function () use ($documentId, $file, $comment, $userId) {
            $document = Document::findOrFail($documentId);
            
            // Tạo version mới
            $version = $this->createDocumentVersion($document, $file, $comment, $userId);
            
            // Cập nhật current_version_id
            $document->update([
                'current_version_id' => $version->id,
                'updated_by' => $userId
            ]);
            
            // Dispatch event
            EventBus::dispatch(new DocumentVersionCreated($version, $userId));
            
            return $version;
        });
    }

    /**
     * Revert document về version cũ
     */
    public function revertToVersion(int $documentId, int $targetVersionNumber, string $comment, int $userId): DocumentVersion
    {
        return DB::transaction(function () use ($documentId, $targetVersionNumber, $comment, $userId) {
            $document = Document::findOrFail($documentId);
            $targetVersion = DocumentVersion::forDocument($documentId)
                                          ->where('version_number', $targetVersionNumber)
                                          ->firstOrFail();
            
            // Tạo version mới từ target version
            $newVersionNumber = $document->getNextVersionNumber();
            $newVersion = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $newVersionNumber,
                'file_path' => $targetVersion->file_path, // Sử dụng lại file path
                'storage_driver' => $targetVersion->storage_driver,
                'comment' => $comment,
                'metadata' => $targetVersion->metadata,
                'reverted_from_version_number' => $targetVersionNumber,
                'created_by' => $userId,
            ]);
            
            // Cập nhật current_version_id
            $document->update([
                'current_version_id' => $newVersion->id,
                'updated_by' => $userId
            ]);
            
            // Dispatch event
            EventBus::dispatch(new DocumentVersionReverted($newVersion, $targetVersion, $userId));
            
            return $newVersion;
        });
    }

    /**
     * Phê duyệt document cho client
     */
    public function approveForClient(int $documentId, int $userId): Document
    {
        $document = Document::findOrFail($documentId);
        
        $document->update([
            'visibility' => 'client',
            'client_approved' => true,
            'updated_by' => $userId
        ]);
        
        // Dispatch event
        EventBus::dispatch(new DocumentApprovedForClient($document, $userId));
        
        return $document->fresh(['currentVersion', 'creator']);
    }

    /**
     * Xóa document
     */
    public function deleteDocument(int $documentId, int $userId): bool
    {
        return DB::transaction(function () use ($documentId, $userId) {
            $document = Document::with('versions')->findOrFail($documentId);
            
            // Xóa tất cả files của document
            foreach ($document->versions as $version) {
                $this->deleteVersionFile($version);
            }
            
            // Xóa document (cascade sẽ xóa versions)
            $document->delete();
            
            // Dispatch event
            EventBus::dispatch(new DocumentDeleted($document, $userId));
            
            return true;
        });
    }

    /**
     * Lấy thống kê documents
     */
    public function getDocumentStats(int $projectId): array
    {
        $totalDocuments = Document::forProject($projectId)->count();
        $clientApprovedDocuments = Document::forProject($projectId)->clientApproved()->count();
        $internalDocuments = Document::forProject($projectId)->where('visibility', 'internal')->count();
        
        return [
            'total_documents' => $totalDocuments,
            'client_approved_documents' => $clientApprovedDocuments,
            'internal_documents' => $internalDocuments,
            'pending_approval' => Document::forProject($projectId)
                                        ->where('visibility', 'client')
                                        ->where('client_approved', false)
                                        ->count(),
        ];
    }

    /**
     * Helper: Tạo document version
     */
    private function createDocumentVersion(Document $document, UploadedFile $file, string $comment, int $userId): DocumentVersion
    {
        // Lưu file
        $filePath = $this->storeFile($file, $document->project_id);
        
        // Tạo version
        return DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $document->getNextVersionNumber(),
            'file_path' => $filePath,
            'storage_driver' => 'local', 
            'comment' => $comment,
            'metadata' => [
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
            'created_by' => $userId,
        ]);
    }

    /**
     * Helper: Lưu file
     */
    private function storeFile(UploadedFile $file, int $projectId): string
    {
        $directory = "documents/project_{$projectId}";
        return $file->store($directory, 'local');
    }

    /**
     * Helper: Xóa file của version
     */
    private function deleteVersionFile(DocumentVersion $version): void
    {
        if ($version->storage_driver === 'local' && Storage::disk('local')->exists($version->file_path)) {
            Storage::disk('local')->delete($version->file_path);
        }
        
    }
}