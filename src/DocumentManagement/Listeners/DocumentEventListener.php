<?php declare(strict_types=1);

namespace Src\DocumentManagement\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\Notification as NotificationModel;
use App\Models\ProjectActivity;
use App\Models\User;
use Src\DocumentManagement\Events\DocumentCreated;
use Src\DocumentManagement\Events\DocumentUpdated;
use Src\DocumentManagement\Events\DocumentDeleted;
use Src\DocumentManagement\Events\DocumentVersionCreated;
use Src\DocumentManagement\Events\DocumentVersionReverted;
use Src\DocumentManagement\Events\DocumentApprovedForClient;

/**
 * Event Listener cho các sự kiện của Document Management
 */
class DocumentEventListener
{
    /**
     * Đăng ký các event listeners
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            DocumentCreated::class,
            [DocumentEventListener::class, 'handleDocumentCreated']
        );

        $events->listen(
            DocumentUpdated::class,
            [DocumentEventListener::class, 'handleDocumentUpdated']
        );

        $events->listen(
            DocumentDeleted::class,
            [DocumentEventListener::class, 'handleDocumentDeleted']
        );

        $events->listen(
            DocumentVersionCreated::class,
            [DocumentEventListener::class, 'handleDocumentVersionCreated']
        );

        $events->listen(
            DocumentVersionReverted::class,
            [DocumentEventListener::class, 'handleDocumentVersionReverted']
        );

        $events->listen(
            DocumentApprovedForClient::class,
            [DocumentEventListener::class, 'handleDocumentApprovedForClient']
        );
    }

    /**
     * Xử lý khi document được tạo
     */
    public function handleDocumentCreated(DocumentCreated $event): void
    {
        Log::info('Document created', [
            'document_id' => $event->document->id,
            'document_title' => $event->document->original_name,
            'project_id' => $event->document->project_id,
            'created_by' => $event->userId
        ]);

        // Send notification to project members
        $this->sendDocumentNotification($event->document, 'created', $event->userId);
        
        // Update project activity log
        $this->logProjectActivity($event->document, 'document_created', $event->userId);
        
        // Index document for search engine
        $this->indexDocument($event->document);
    }

    /**
     * Xử lý khi document được cập nhật
     */
    public function handleDocumentUpdated(DocumentUpdated $event): void
    {
        Log::info('Document updated', [
            'document_id' => $event->document->id,
            'document_title' => $event->document->original_name,
            'changes' => $event->changedFields,
            'updated_by' => $event->userId
        ]);

        // Send notification if important changes
        if ($this->hasImportantChanges($event->changedFields)) {
            $this->sendDocumentNotification($event->document, 'updated', $event->userId);
        }
        
        // Update search index
        $this->updateDocumentIndex($event->document);
        
        // Log project activity
        $this->logProjectActivity($event->document, 'document_updated', $event->userId);
    }

    /**
     * Xử lý khi document bị xóa
     */
    public function handleDocumentDeleted(DocumentDeleted $event): void
    {
        Log::info('Document deleted', [
            'document_id' => $event->documentId,
            'document_title' => $event->documentTitle,
            'project_id' => $event->projectId,
            'deleted_by' => $event->userId
        ]);

        // Send notification to project members
        if ($event->projectId) {
            $this->sendDocumentDeletionNotification($event->documentId, $event->documentTitle, $event->projectId, $event->userId);
        }
        
        // Remove from search index
        $this->removeDocumentFromIndex($event->documentId);
        
        // Cleanup file storage
        $this->cleanupDocumentFiles($event->document);
        
        // Log project activity
        if ($event->projectId) {
            $this->logProjectActivityForDeletion($event->documentId, $event->documentTitle, $event->projectId, $event->userId);
        }
    }

    /**
     * Xử lý khi tạo version mới
     */
    public function handleDocumentVersionCreated(DocumentVersionCreated $event): void
    {
        Log::info('Document version created', [
            'document_id' => $event->version->document_id,
            'version_number' => $event->version->version_number,
            'file_name' => $event->version->file_name,
            'created_by' => $event->userId
        ]);

        // TODO: Gửi notification cho stakeholders
        // TODO: Backup version cũ nếu cần
    }

    /**
     * Xử lý khi revert version
     */
    public function handleDocumentVersionReverted(DocumentVersionReverted $event): void
    {
        Log::info('Document version reverted', [
            'document_id' => $event->newVersion->document_id,
            'new_version_number' => $event->newVersion->version_number,
            'reverted_from_version' => $event->revertedFromVersionNumber,
            'reverted_by' => $event->userId
        ]);

        // TODO: Gửi notification về việc revert
        // TODO: Log audit trail
    }

    /**
     * Xử lý khi document được phê duyệt cho client
     */
    public function handleDocumentApprovedForClient(DocumentApprovedForClient $event): void
    {
        Log::info('Document approved for client', [
            'document_id' => $event->document->id,
            'document_title' => $event->document->title,
            'approved_by' => $event->userId
        ]);

        // Send notification to client
        $this->sendClientNotification($event->document, $event->userId);
        
        // Update client portal
        $this->updateClientPortal($event->document);
        
        // Log compliance audit
        $this->logComplianceAudit($event->document, $event->userId);
    }

    /**
     * Send notification to project members
     */
    private function sendDocumentNotification($document, string $action, string $userId): void
    {
        try {
            if (!$document->project_id) {
                return;
            }

            // Get project team members
            $projectMembers = User::whereHas('projects', function ($query) use ($document) {
                $query->where('project_id', $document->project_id);
            })->where('id', '!=', $userId)->get();

            foreach ($projectMembers as $member) {
                NotificationModel::create([
                    'user_id' => $member->id,
                    'tenant_id' => $document->tenant_id,
                    'title' => "Document {$action}",
                    'message' => "Document '{$document->original_name}' has been {$action}",
                    'type' => 'document',
                    'data' => [
                        'document_id' => $document->id,
                        'action' => $action,
                        'project_id' => $document->project_id
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send document notification: ' . $e->getMessage());
        }
    }

    /**
     * Log project activity
     */
    private function logProjectActivity($document, string $type, string $userId): void
    {
        try {
            if (!$document->project_id) {
                return;
            }

            ProjectActivity::create([
                'project_id' => $document->project_id,
                'tenant_id' => $document->tenant_id,
                'user_id' => $userId,
                'type' => $type,
                'description' => "Document '{$document->original_name}' was {$type}",
                'metadata' => [
                    'document_id' => $document->id,
                    'document_name' => $document->original_name
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log project activity: ' . $e->getMessage());
        }
    }

    /**
     * Index document for search
     */
    private function indexDocument($document): void
    {
        try {
            // TODO: Implement search indexing (Elasticsearch, Algolia, etc.)
            Log::info('Document indexed for search', [
                'document_id' => $document->id,
                'document_name' => $document->original_name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to index document: ' . $e->getMessage());
        }
    }

    /**
     * Update document in search index
     */
    private function updateDocumentIndex($document): void
    {
        try {
            // TODO: Implement search index update
            Log::info('Document search index updated', [
                'document_id' => $document->id,
                'document_name' => $document->original_name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update document index: ' . $e->getMessage());
        }
    }

    /**
     * Check if changes are important enough to notify
     */
    private function hasImportantChanges(array $changes): bool
    {
        $importantFields = ['status', 'category', 'description', 'is_public'];
        
        foreach ($importantFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Cleanup document files
     */
    private function cleanupDocumentFiles($document): void
    {
        try {
            if ($document->file_path && \Storage::exists($document->file_path)) {
                \Storage::delete($document->file_path);
                Log::info('Document file cleaned up', [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to cleanup document files: ' . $e->getMessage());
        }
    }

    /**
     * Send document deletion notification
     */
    private function sendDocumentDeletionNotification(string $documentId, string $documentTitle, string $projectId, string $userId): void
    {
        try {
            // Get project team members
            $projectMembers = User::whereHas('projects', function ($query) use ($projectId) {
                $query->where('project_id', $projectId);
            })->where('id', '!=', $userId)->get();

            foreach ($projectMembers as $member) {
                NotificationModel::create([
                    'user_id' => $member->id,
                    'tenant_id' => $member->tenant_id,
                    'title' => 'Document deleted',
                    'message' => "Document '{$documentTitle}' has been deleted",
                    'type' => 'document',
                    'data' => [
                        'document_id' => $documentId,
                        'action' => 'deleted',
                        'project_id' => $projectId
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send document deletion notification: ' . $e->getMessage());
        }
    }

    /**
     * Remove document from search index
     */
    private function removeDocumentFromIndex(string $documentId): void
    {
        try {
            // TODO: Implement search index removal
            Log::info('Document removed from search index', [
                'document_id' => $documentId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove document from index: ' . $e->getMessage());
        }
    }

    /**
     * Log project activity for document deletion
     */
    private function logProjectActivityForDeletion(string $documentId, string $documentTitle, string $projectId, string $userId): void
    {
        try {
            ProjectActivity::create([
                'project_id' => $projectId,
                'tenant_id' => User::find($userId)->tenant_id,
                'user_id' => $userId,
                'type' => 'document_deleted',
                'description' => "Document '{$documentTitle}' was deleted",
                'metadata' => [
                    'document_id' => $documentId,
                    'document_name' => $documentTitle
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log project activity for deletion: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to client
     */
    private function sendClientNotification($document, string $userId): void
    {
        try {
            // TODO: Implement client notification
            Log::info('Client notification sent', [
                'document_id' => $document->id,
                'document_name' => $document->original_name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send client notification: ' . $e->getMessage());
        }
    }

    /**
     * Update client portal
     */
    private function updateClientPortal($document): void
    {
        try {
            // TODO: Implement client portal update
            Log::info('Client portal updated', [
                'document_id' => $document->id,
                'document_name' => $document->original_name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update client portal: ' . $e->getMessage());
        }
    }

    /**
     * Log compliance audit
     */
    private function logComplianceAudit($document, string $userId): void
    {
        try {
            // TODO: Implement compliance audit logging
            Log::info('Compliance audit logged', [
                'document_id' => $document->id,
                'document_name' => $document->original_name,
                'approved_by' => $userId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log compliance audit: ' . $e->getMessage());
        }
    }
}