<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentDecision;
use App\Enums\DocumentWorkflowStatus;
use App\Exceptions\DocumentWorkflowException;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DocumentWorkflowService
{
    public function findForTenant(string $tenantId, string $documentId): ?Document
    {
        return Document::query()
            ->where('tenant_id', $tenantId)
            ->with('currentVersion')
            ->find($documentId);
    }

    public function submit(string $tenantId, string $documentId, string $actorId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId) {
            $document = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw DocumentWorkflowException::documentNotFound();
            }
            /** @var \App\Models\Document $document */

            if ($document->status !== DocumentWorkflowStatus::DRAFT->value) {
                throw DocumentWorkflowException::invalidSubmitTransition($document->status);
            }

            $metadata = $document->metadata ?? [];
            $metadata['status'] = DocumentWorkflowStatus::SUBMITTED->value;
            $metadata['submitted_at'] = now()->toISOString();
            $metadata['submitted_by'] = $actorId;

            $document->forceFill([
                'status' => DocumentWorkflowStatus::SUBMITTED->value,
                'metadata' => $metadata,
                'updated_by' => $actorId,
            ])->save();

            return $document->fresh(['currentVersion']);
        });
    }

    /**
     * @param string|null $note Luôn optional — kể cả khi $decision === REJECTED.
     *   Ràng buộc "bắt buộc khi từ chối" là quy tắc form của Web, không phải
     *   quy tắc của service/API.
     */
    public function decide(
        string $tenantId,
        string $documentId,
        string $actorId,
        DocumentDecision $decision,
        ?string $note,
    ): Document {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId, $decision, $note) {
            $document = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw DocumentWorkflowException::documentNotFound();
            }
            /** @var \App\Models\Document $document */

            if ($document->status !== DocumentWorkflowStatus::SUBMITTED->value) {
                throw DocumentWorkflowException::invalidDecisionTransition($document->status);
            }

            $metadata = $document->metadata ?? [];
            $metadata['status'] = $decision->value;
            $metadata['decision'] = $decision->value;
            $metadata['decision_at'] = now()->toISOString();
            $metadata['decision_by'] = $actorId;
            $metadata['decision_note'] = $note;

            $document->forceFill([
                'status' => $decision->value,
                'metadata' => $metadata,
                'updated_by' => $actorId,
            ])->save();

            return $document->fresh(['currentVersion']);
        });
    }
}
