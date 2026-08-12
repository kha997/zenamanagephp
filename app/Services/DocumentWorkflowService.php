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
use JsonException;

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
            $approval = $this->statusService->approval($document);

            if ($approval !== DocumentApprovalStatus::AWAITING_APPROVAL) {
                throw DocumentWorkflowException::invalidDecisionTransition((string) $document->getRawOriginal('status'));
            }

            $metadata = $document->metadata ?? [];
            $submittedBy = $metadata['submitted_by'] ?? null;
            $submittedAt = $metadata['submitted_at'] ?? null;
            $submitted = is_string($submittedBy) && is_string($submittedAt)
                ? $this->matchingEvent(
                    $document,
                    $tenantId,
                    'submitted',
                    DocumentApprovalStatus::NOT_SUBMITTED,
                    DocumentApprovalStatus::AWAITING_APPROVAL,
                    $submittedBy,
                    null,
                    [
                        'submitted_by' => $submittedBy,
                        'submitted_at' => $submittedAt,
                    ]
                )
                : null;
            if ($submitted === null) {
                throw DocumentWorkflowException::legacyApprovalReconciliationRequired();
            }

            $lifecycle = $this->statusService->lifecycle($document);
            if ($lifecycle === null) {
                throw DocumentWorkflowException::invalidCanonicalState((string) $document->getRawOriginal('status'));
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
            $approval = $this->statusService->approval($document);

            if (! in_array($approval, [DocumentApprovalStatus::APPROVED, DocumentApprovalStatus::REJECTED], true)) {
                throw DocumentWorkflowException::invalidReopenTransition((string) $document->getRawOriginal('status'));
            }

            $metadata = $document->metadata ?? [];
            $decisionBy = $metadata['decision_by'] ?? null;
            $decisionAt = $metadata['decision_at'] ?? null;
            $decisionName = $metadata['decision'] ?? null;
            $decisionNote = $metadata['decision_note'] ?? null;
            $validDecisionMetadata = $decisionName === $approval->value
                && is_string($decisionBy)
                && is_string($decisionAt)
                && ($decisionNote === null || is_string($decisionNote));
            $decision = $validDecisionMetadata
                ? $this->matchingEvent(
                    $document,
                    $tenantId,
                    $approval->value,
                    DocumentApprovalStatus::AWAITING_APPROVAL,
                    $approval,
                    $decisionBy,
                    $decisionNote,
                    [
                        'decision' => $approval->value,
                        'decision_by' => $decisionBy,
                        'decision_at' => $decisionAt,
                        'decision_note' => $decisionNote,
                    ]
                )
                : null;
            if ($decision === null) {
                throw DocumentWorkflowException::legacyApprovalReconciliationRequired();
            }

            if ($this->statusService->lifecycle($document) === null) {
                throw DocumentWorkflowException::invalidCanonicalState((string) $document->getRawOriginal('status'));
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

    /** @param array<string, mixed> $context */
    private function matchingEvent(
        Document $document,
        string $tenantId,
        string $eventName,
        DocumentApprovalStatus $from,
        DocumentApprovalStatus $to,
        string $actorId,
        ?string $note,
        array $context,
    ): ?DocumentApprovalEvent
    {
        $query = DB::table('document_approval_events')
            ->where('tenant_id', $tenantId)
            ->where('document_id', $document->id)
            ->where('event', $eventName)
            ->where('from_approval_status', $from->value)
            ->where('to_approval_status', $to->value)
            ->where('actor_id', $actorId);

        $note === null ? $query->whereNull('note') : $query->where('note', $note);

        $matches = $query->get()->filter(function (object $row) use ($document, $tenantId, $context): bool {
            return $this->contextsMatch($this->eventContext($row->context ?? null), $context)
                && $this->hasValidVersionEvidence($document, $tenantId, $row->document_version_id ?? null);
        });

        if ($matches->count() !== 1) {
            return null;
        }

        $row = $matches->first();
        if (! is_object($row)) {
            return null;
        }

        $event = new DocumentApprovalEvent();
        $event->setRawAttributes((array) $row, true);
        $event->exists = true;

        return $event;
    }

    /** @return array<string, mixed>|null */
    private function eventContext(mixed $context): ?array
    {
        if (is_array($context)) {
            return $context;
        }

        if (! is_string($context)) {
            return null;
        }

        try {
            $decoded = json_decode($context, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed>|null $actual
     * @param array<string, mixed> $expected
     */
    private function contextsMatch(?array $actual, array $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        ksort($actual);
        ksort($expected);

        return $actual === $expected;
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
