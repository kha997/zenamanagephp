<?php declare(strict_types=1);

namespace Src\DocumentManagement\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\DocumentManagement\Models\Document;
use Src\DocumentManagement\Models\DocumentVersion;
use Src\DocumentManagement\Events\DocumentCreated;
use Src\DocumentManagement\Events\DocumentUpdated;
use Src\DocumentManagement\Events\DocumentDeleted;
use Src\DocumentManagement\Events\DocumentVersionCreated;
use Src\DocumentManagement\Events\DocumentVersionReverted;
use Src\DocumentManagement\Events\DocumentApprovedForClient;
use Src\Foundation\EventBus;
use Src\Foundation\Events\BaseEvent;

/**
 * Service xử lý business logic cho Document Management
 */
class DocumentService
{
    /**
     * Lấy danh sách documents theo project
     */
    public function getDocumentsByProject(string $projectId, array $filters = [])
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

        if (!empty($filters['document_type'])) {
            $query->where('file_type', $filters['document_type']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Lấy document theo ID
     */
    public function getDocumentById(string $documentId): ?Document
    {
        return Document::with(['versions.creator', 'currentVersion', 'creator', 'project'])
                      ->find($documentId);
    }

    /**
     * Tạo document mới với version đầu tiên
     */
    public function createDocument(array $documentData, UploadedFile $file, string $userId): Document
    {
        return DB::transaction(function () use ($documentData, $file, $userId) {
            // Tạo document
            $document = Document::create([
                'project_id' => $documentData['project_id'],
                'uploaded_by' => $userId,
                'name' => $documentData['title'],
                'description' => $documentData['description'] ?? null,
                'tags' => $documentData['tags'] ?? null,
                'category' => $documentData['category'] ?? 'general',
                'metadata' => $documentData['metadata'] ?? null,
                'visibility' => $documentData['visibility'] ?? Document::VISIBILITY_INTERNAL,
                'client_approved' => $documentData['client_approved'] ?? false,
                'created_by' => $userId,
                'updated_by' => $userId,
                'file_path' => '',
                'file_type' => $documentData['document_type'] ?? 'other',
                'mime_type' => '',
                'file_size' => 0,
                'file_hash' => '',
                'original_name' => $file->getClientOriginalName(),
                'status' => 'active',
                'version' => 0,
                'is_current_version' => true,
            ]);

            // Tạo version đầu tiên
            $version = $this->createDocumentVersion(
                $document,
                $file,
                $documentData['comment'] ?? 'Initial version',
                $userId
            );

            $filePath = $version->file_path;
            $fileHash = hash_file('sha256', Storage::disk('local')->path($filePath));

            $document->update([
                'current_version_id' => $version->id,
                'file_path' => $filePath,
                'file_type' => $documentData['document_type'] ?? 'other',
                'mime_type' => $version->metadata['mime_type'] ?? null,
                'file_size' => $version->metadata['size'] ?? null,
                'file_hash' => $fileHash,
                'original_name' => $version->metadata['original_filename'] ?? null,
                'status' => 'active',
                'version' => $version->version_number,
                'is_current_version' => true,
            ]);

            // Dispatch events
            $this->dispatchEvent(new DocumentCreated($document, $userId));
            $this->dispatchEvent(new DocumentVersionCreated($version, $userId));

            return $document->fresh(['currentVersion', 'creator']);
        });
    }

    /**
     * Cập nhật thông tin document
     */
    public function updateDocument(string $documentId, array $data, string $userId): Document
    {
        $document = Document::findOrFail($documentId);
        
        $oldData = $document->toArray();
        $document->update(array_merge($data, ['updated_by' => $userId]));
        
        // Dispatch event
        $this->dispatchEvent(new DocumentUpdated($document, $oldData, $userId));
        
        return $document->fresh(['currentVersion', 'creator']);
    }

    /**
     * Tạo version mới cho document
     */
    public function createNewVersion(string $documentId, UploadedFile $file, string $comment, string $userId): DocumentVersion
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
            $this->dispatchEvent(new DocumentVersionCreated($version, $userId));
            
            return $version;
        });
    }

    /**
     * Revert document về version cũ
     */
    public function revertToVersion(string $documentId, int $targetVersionNumber, string $comment, string $userId): DocumentVersion
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
            $this->dispatchEvent(new DocumentVersionReverted($newVersion, $targetVersion->version_number, $userId));
            
            return $newVersion;
        });
    }

    /**
     * Phê duyệt document cho client
     */
    public function approveForClient(string $documentId, string $userId): Document
    {
        $document = Document::findOrFail($documentId);
        
        $document->update([
            'visibility' => 'client',
            'client_approved' => true,
            'updated_by' => $userId
        ]);
        
        // Dispatch event
        $this->dispatchEvent(new DocumentApprovedForClient($document, $userId));
        
        return $document->fresh(['currentVersion', 'creator']);
    }

    /**
     * Xóa document
     */
    public function deleteDocument(string $documentId, string $userId): bool
    {
        return DB::transaction(function () use ($documentId, $userId) {
            $document = Document::findOrFail($documentId);
            
            // Xóa tất cả files của document
            foreach ($document->versions as $version) {
                $this->deleteVersionFile($version);
            }
            if ($document->file_path) {
                Storage::disk('local')->delete($document->file_path);
            }
            
            // Xóa document (cascade sẽ xóa versions)
            $document->delete();
            
            // Dispatch event
            $this->dispatchEvent(new DocumentDeleted(
                $document->id,
                (string) $document->ulid,
                (string) ($document->title ?? $document->name ?? ''),
                $document->project_id,
                $userId
            ));
            
            return true;
        });
    }

    /**
     * Lấy thống kê documents
     */
    public function getDocumentStats(string $projectId): array
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
     * Download a version file
     */
    public function downloadVersion(string $documentId, ?int $versionNumber = null)
    {
        $query = DocumentVersion::where('document_id', $documentId);

        if ($versionNumber !== null) {
            $query->where('version_number', $versionNumber);
        }

        $version = $query->orderByDesc('version_number')->firstOrFail();

        $disk = Storage::disk($version->storage_driver);

        if (! $disk->exists($version->file_path)) {
            throw new \RuntimeException('Stored file not found');
        }

        $fileName = $version->getOriginalFileName() ?? basename($version->file_path);

        return $disk->download($version->file_path, $fileName);
    }

    /**
     * Helper: Tạo document version
     */
    private function createDocumentVersion(Document $document, UploadedFile $file, string $comment, string $userId): DocumentVersion
    {
        // Lưu file
        $filePath = $this->storeFile($file, $document->project_id);
        
        // Tạo version
        return DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $document->getNextVersionNumber(),
            'file_path' => $filePath,
            'storage_driver' => 'local', // TODO: Có thể config
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
    private function storeFile(UploadedFile $file, string $projectId): string
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
        // TODO: Xử lý cho S3, Google Drive
    }

    private function dispatchEvent(BaseEvent $event): array
    {
        return EventBus::dispatch($event->getEventName(), $event->getPayload());
    }
}
