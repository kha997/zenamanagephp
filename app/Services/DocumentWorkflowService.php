<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentDecision;
use App\Enums\DocumentLifecycleStatus;
use App\Exceptions\DocumentWorkflowException;
use App\Models\Document;
use App\Models\DocumentApprovalEvent;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;

class DocumentWorkflowService
{
    public function __construct(private readonly DocumentStatusService $statusService)
    {
    }

    public function findForTenant(string $tenantId, string $documentId): ?Document
    {
        return Document::query()
            ->where('tenant_id', $tenantId)
            ->with('currentVersion')
            ->find($documentId);
    }

    public function submit(string $tenantId, string $documentId, string $actorId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId): Document {
            $document = $this->lockDocument($tenantId, $documentId);
            [$lifecycle, $approval] = $this->resolveState($document);

            $materialized = $document->getRawOriginal('lifecycle_status') !== null
                && $document->getRawOriginal('approval_status') !== null;
            if (! $materialized
                || ! in_array($lifecycle, [DocumentLifecycleStatus::DRAFT, DocumentLifecycleStatus::IN_REVIEW], true)
                || $approval !== DocumentApprovalStatus::NOT_SUBMITTED) {
                throw DocumentWorkflowException::invalidSubmitTransition((string) $document->getRawOriginal('status'));
            }

            $version = $this->lockCurrentVersion($document, $tenantId);
            $submittedAt = now()->toISOString();

            $this->appendEvent(
                $document,
                $version->id,
                'submitted',
                DocumentApprovalStatus::NOT_SUBMITTED,
                DocumentApprovalStatus::AWAITING_APPROVAL,
                $actorId,
                null,
                [
                    'submitted_by' => $actorId,
                    'submitted_at' => $submittedAt,
                ]
            );

            $metadata = $document->metadata ?? [];
            $metadata['submitted_at'] = $submittedAt;
            $metadata['submitted_by'] = $actorId;
            $document->forceFill(['metadata' => $metadata]);
            $this->statusService->writeState(
                $document,
                $lifecycle,
                DocumentApprovalStatus::AWAITING_APPROVAL,
                $actorId
            );
            $document->save();

            return $this->freshDocument($document);
        });
    }

    /**
     * @param string|null $note Always optional, including rejected decisions.
     *   A required rejection note is an adapter/form rule, not a service rule.
     */
    public function decide(
        string $tenantId,
        string $documentId,
        string $actorId,
        DocumentDecision $decision,
        ?string $note,
    ): Document {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId, $decision, $note): Document {
            $document = $this->lockDocument($tenantId, $documentId);
            [$lifecycle, $approval] = $this->resolveState($document);

            if ($approval !== DocumentApprovalStatus::AWAITING_APPROVAL) {
                throw DocumentWorkflowException::invalidDecisionTransition((string) $document->getRawOriginal('status'));
            }

            $submitted = $this->latestEvent($document);
            if ($submitted === null
                || $submitted->event !== 'submitted'
                || $submitted->to_approval_status !== DocumentApprovalStatus::AWAITING_APPROVAL->value
                || ! $this->hasValidVersionEvidence($document, $tenantId, $submitted->document_version_id)) {
                throw DocumentWorkflowException::legacyApprovalReconciliationRequired();
            }

            $decidedAt = now()->toISOString();
            $toApproval = DocumentApprovalStatus::from($decision->value);
            $this->appendEvent(
                $document,
                $submitted->document_version_id,
                $decision->value,
                DocumentApprovalStatus::AWAITING_APPROVAL,
                $toApproval,
                $actorId,
                $note,
                [
                    'decision' => $decision->value,
                    'decision_by' => $actorId,
                    'decision_at' => $decidedAt,
                    'decision_note' => $note,
                ]
            );

            $metadata = $document->metadata ?? [];
            $metadata['decision'] = $decision->value;
            $metadata['decision_at'] = $decidedAt;
            $metadata['decision_by'] = $actorId;
            $metadata['decision_note'] = $note;
            $document->forceFill(['metadata' => $metadata]);
            $this->statusService->writeState($document, $lifecycle, $toApproval, $actorId);
            $document->save();

            return $this->freshDocument($document);
        });
    }

    public function reopenForRevision(string $tenantId, string $documentId, string $actorId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId): Document {
            $document = $this->lockDocument($tenantId, $documentId);
            [, $approval] = $this->resolveState($document);

            if (! in_array($approval, [DocumentApprovalStatus::APPROVED, DocumentApprovalStatus::REJECTED], true)) {
                throw DocumentWorkflowException::invalidReopenTransition((string) $document->getRawOriginal('status'));
            }

            $decision = $this->latestEvent($document);
            if ($decision === null
                || $decision->event !== $approval->value
                || $decision->to_approval_status !== $approval->value
                || ! $this->hasValidVersionEvidence($document, $tenantId, $decision->document_version_id)) {
                throw DocumentWorkflowException::legacyApprovalReconciliationRequired();
            }

            $this->appendEvent(
                $document,
                $decision->document_version_id,
                'reopened',
                $approval,
                DocumentApprovalStatus::NOT_SUBMITTED,
                $actorId
            );

            $metadata = $this->withoutCurrentDecisionMetadata($document->metadata ?? []);
            $document->forceFill(['metadata' => $metadata]);
            $this->statusService->writeState(
                $document,
                DocumentLifecycleStatus::DRAFT,
                DocumentApprovalStatus::NOT_SUBMITTED,
                $actorId
            );
            $document->save();

            return $this->freshDocument($document);
        });
    }

    public function reactivateForRevision(string $tenantId, string $documentId, string $actorId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId): Document {
            $document = $this->lockDocument($tenantId, $documentId);
            [$lifecycle, $approval] = $this->resolveState($document);

            if ($lifecycle !== DocumentLifecycleStatus::ARCHIVED) {
                throw DocumentWorkflowException::invalidReactivateTransition((string) $document->getRawOriginal('status'));
            }

            $versionId = null;
            $context = ['legacy_without_current_version' => true];
            if ($document->current_version_id !== null) {
                $versionId = $this->lockCurrentVersion($document, $tenantId)->id;
                $context = [];
            }

            $this->appendEvent(
                $document,
                $versionId,
                'reactivated',
                $approval,
                DocumentApprovalStatus::NOT_SUBMITTED,
                $actorId,
                null,
                $context
            );

            $metadata = $this->withoutCurrentDecisionMetadata($document->metadata ?? []);
            $document->forceFill(['metadata' => $metadata]);
            $this->statusService->writeState(
                $document,
                DocumentLifecycleStatus::DRAFT,
                DocumentApprovalStatus::NOT_SUBMITTED,
                $actorId
            );
            $document->save();

            return $this->freshDocument($document);
        });
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

    private function lockCurrentVersion(Document $document, string $tenantId): DocumentVersion
    {
        if ($document->current_version_id === null) {
            throw DocumentWorkflowException::invalidCurrentVersion();
        }

        $row = DB::table('document_versions')
            ->where('id', $document->current_version_id)
            ->where('document_id', $document->id)
            ->lockForUpdate()
            ->first();

        $parentBelongsToTenant = DB::table('documents')
            ->where('id', $document->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->exists();
        if ($row === null || ! $parentBelongsToTenant) {
            throw DocumentWorkflowException::invalidCurrentVersion();
        }

        $version = new DocumentVersion();
        $version->setRawAttributes((array) $row, true);
        $version->exists = true;

        return $version;
    }

    private function latestEvent(Document $document): ?DocumentApprovalEvent
    {
        $row = DB::table('document_approval_events')
            ->where('tenant_id', (string) $document->getAttribute('tenant_id'))
            ->where('document_id', $document->id)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $event = new DocumentApprovalEvent();
        $event->setRawAttributes((array) $row, true);
        $event->exists = true;

        return $event;
    }

    private function hasValidVersionEvidence(Document $document, string $tenantId, ?string $versionId): bool
    {
        if ($versionId === null) {
            return false;
        }

        return DB::table('document_versions')
            ->join('documents', 'documents.id', '=', 'document_versions.document_id')
            ->where('document_versions.id', $versionId)
            ->where('document_versions.document_id', $document->id)
            ->where('documents.tenant_id', $tenantId)
            ->whereNull('documents.deleted_at')
            ->exists();
    }

    /** @param array<string, mixed> $context */
    private function appendEvent(
        Document $document,
        ?string $versionId,
        string $event,
        DocumentApprovalStatus $from,
        DocumentApprovalStatus $to,
        string $actorId,
        ?string $note = null,
        array $context = [],
    ): void {
        DocumentApprovalEvent::query()->create([
            'tenant_id' => (string) $document->getAttribute('tenant_id'),
            'document_id' => $document->id,
            'document_version_id' => $versionId,
            'event' => $event,
            'from_approval_status' => $from->value,
            'to_approval_status' => $to->value,
            'actor_id' => $actorId,
            'note' => $note,
            'context' => $context,
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function withoutCurrentDecisionMetadata(array $metadata): array
    {
        unset(
            $metadata['decision'],
            $metadata['decision_at'],
            $metadata['decision_by'],
            $metadata['decision_note']
        );

        return $metadata;
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
