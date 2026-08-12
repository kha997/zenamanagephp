<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DocumentApproverAssignmentException;
use App\Models\Document;
use App\Models\DocumentApproverAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sole owner of writes to documents.approver_id and the
 * document_approver_assignments audit trail (GAP-033).
 *
 * This service never authorizes (no Auth/Gate/authorize() calls) — the
 * calling adapter must authorize (DocumentPolicy::assignApprover) against a
 * tenant-scoped resource lookup before calling assign(). It never touches
 * lifecycle_status/approval_status/current_version_id/DocumentVersion — those
 * remain owned by DocumentWorkflowService/DocumentLifecycleService/
 * DocumentVersionService respectively (GAP-032).
 */
class DocumentApproverAssignmentService
{
    public function findForTenant(string $tenantId, string $documentId): ?Document
    {
        return Document::query()
            ->where('tenant_id', $tenantId)
            ->with('project')
            ->find($documentId);
    }

    public function assign(string $tenantId, string $documentId, string $actorId, ?string $newApproverId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId, $newApproverId): Document {
            $document = $this->lockDocument($tenantId, $documentId);

            if ($newApproverId !== null) {
                $target = User::query()->find($newApproverId);
                if ($target === null || $target->tenant_id !== $tenantId) {
                    throw DocumentApproverAssignmentException::tenantMismatch();
                }
                if (! $target->hasPermission('document.approve')) {
                    throw DocumentApproverAssignmentException::targetLacksApprovalPermission();
                }
            }

            $previousApproverId = $document->approver_id;

            $document->forceFill(['approver_id' => $newApproverId])->save();

            DocumentApproverAssignment::query()->create([
                'tenant_id' => $tenantId,
                'document_id' => $documentId,
                'actor_id' => $actorId,
                'previous_approver_id' => $previousApproverId,
                'new_approver_id' => $newApproverId,
            ]);

            return $document->fresh(['project']);
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
            throw DocumentApproverAssignmentException::documentNotFound();
        }

        $document = new Document();
        $document->setRawAttributes((array) $row, true);
        $document->exists = true;

        return $document;
    }
}
