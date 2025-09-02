<?php declare(strict_types=1);

namespace Src\DocumentManagement\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
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
            'document_title' => $event->document->title,
            'project_id' => $event->document->project_id,
            'created_by' => $event->userId
        ]);

        // TODO: Gửi notification cho project members
        // TODO: Cập nhật project activity log
        // TODO: Index document cho search engine
    }

    /**
     * Xử lý khi document được cập nhật
     */
    public function handleDocumentUpdated(DocumentUpdated $event): void
    {
        Log::info('Document updated', [
            'document_id' => $event->document->id,
            'document_title' => $event->document->title,
            'changes' => $event->changedFields,
            'updated_by' => $event->userId
        ]);

        // TODO: Gửi notification nếu có thay đổi quan trọng
        // TODO: Cập nhật search index
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

        // TODO: Gửi notification cho project members
        // TODO: Xóa khỏi search index
        // TODO: Cleanup file storage nếu cần
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

        // TODO: Gửi notification cho client
        // TODO: Cập nhật client portal
        // TODO: Log compliance audit
    }
}