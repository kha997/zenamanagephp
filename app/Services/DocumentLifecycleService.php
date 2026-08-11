<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
use App\Exceptions\DocumentWorkflowException;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DocumentLifecycleService
{
    public function __construct(
        private readonly DocumentStatusService $statusService,
        private readonly DocumentWorkflowService $workflow,
    ) {
    }

    public function findForTenant(string $tenantId, string $documentId): ?Document
    {
        return $this->workflow->findForTenant($tenantId, $documentId);
    }

    public function publish(string $tenantId, string $documentId, string $actorId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId): Document {
            $document = $this->lockDocument($tenantId, $documentId);
            [$lifecycle, $approval] = $this->resolveState($document);

            if (! in_array($lifecycle, [DocumentLifecycleStatus::DRAFT, DocumentLifecycleStatus::IN_REVIEW], true)
                || ! in_array($approval, [DocumentApprovalStatus::NOT_SUBMITTED, DocumentApprovalStatus::APPROVED], true)) {
                throw DocumentWorkflowException::invalidPublishTransition((string) $document->getRawOriginal('status'));
            }

            $this->statusService->writeState($document, DocumentLifecycleStatus::PUBLISHED, $approval, $actorId);
            $document->save();

            return $this->freshDocument($document);
        });
    }

    public function archive(string $tenantId, string $documentId, string $actorId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId): Document {
            $document = $this->lockDocument($tenantId, $documentId);
            [$lifecycle, $approval] = $this->resolveState($document);

            if ($lifecycle !== DocumentLifecycleStatus::PUBLISHED) {
                throw DocumentWorkflowException::invalidArchiveTransition((string) $document->getRawOriginal('status'));
            }

            $this->statusService->writeState($document, DocumentLifecycleStatus::ARCHIVED, $approval, $actorId);
            $document->save();

            return $this->freshDocument($document);
        });
    }

    public function reopen(string $tenantId, string $documentId, string $actorId): Document
    {
        return $this->workflow->reopenForRevision($tenantId, $documentId, $actorId);
    }

    public function reactivate(string $tenantId, string $documentId, string $actorId): Document
    {
        return $this->workflow->reactivateForRevision($tenantId, $documentId, $actorId);
    }

    private function lockDocument(string $tenantId, string $documentId): Document
    {
        $row = DB::table('documents')
            ->where('tenant_id', $tenantId)
            ->where('id', $documentId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw DocumentWorkflowException::documentNotFound();
        }

        $document = new Document();
        $document->setRawAttributes((array) $row, true);
        $document->exists = true;

        return $document;
    }

    /** @return array{DocumentLifecycleStatus, DocumentApprovalStatus} */
    private function resolveState(Document $document): array
    {
        $lifecycle = $this->statusService->lifecycle($document);
        $approval = $this->statusService->approval($document);

        if ($lifecycle === null || $approval === null) {
            throw DocumentWorkflowException::invalidCanonicalState((string) $document->getRawOriginal('status'));
        }

        return [$lifecycle, $approval];
    }

    private function freshDocument(Document $document): Document
    {
        $fresh = $document->fresh(['currentVersion']);
        if (! $fresh instanceof Document) {
            throw DocumentWorkflowException::documentNotFound();
        }

        return $fresh;
    }
}
