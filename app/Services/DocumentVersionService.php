<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
use App\Exceptions\DocumentWorkflowException;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Support\DocumentStatusResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Owns every production write that creates a `DocumentVersion` row or moves
 * `documents.current_version_id`.
 *
 * The service never authorizes: the calling adapter performs its tenant-scoped
 * lookup and resource authorization first, and the service then re-reads and
 * locks the same tenant/document row itself, trusting nothing from the caller's
 * pre-lock instance except the tenant and document identifiers.
 *
 * The service never mutates the Approval dimension. It only reads the resolved
 * Approval/Lifecycle state under the lock to decide version eligibility, and may
 * apply the generic Lifecycle compatibility normalisation through
 * {@see DocumentStatusService::writeState()} when — and only when — the adapter
 * explicitly requested a generic lifecycle target.
 */
class DocumentVersionService
{
    /**
     * Canonical status and workflow-audit keys that a client payload may never plant
     * in a version snapshot. The authoritative values are written by this service.
     *
     * @var list<string>
     */
    public const PROTECTED_VERSION_METADATA_KEYS = [
        'status',
        'lifecycle_status',
        'approval_status',
        'submitted_by',
        'submitted_at',
        'decision',
        'decision_by',
        'decision_at',
        'decision_note',
        'previous_version_id',
        'approval_event_id',
    ];

    /**
     * Document columns an adapter may never hand to this service; canonical state,
     * identity, and version pointers are reconstructed at this boundary.
     *
     * @var list<string>
     */
    private const PROTECTED_DOCUMENT_ATTRIBUTES = [
        'id',
        'tenant_id',
        'status',
        'lifecycle_status',
        'approval_status',
        'current_version_id',
        'version',
        'metadata',
        'created_by',
        'deleted_at',
    ];

    public function __construct(
        private readonly DocumentStatusService $statusService,
        private readonly DocumentStatusResolver $resolver,
    ) {
    }

    /**
     * Create the next document version under the governed documents row lock.
     *
     * Supported `$versionData` keys:
     *  - `file_path`, `storage_driver`, `comment`, `reverted_from_version_number`
     *  - `metadata`: version-only metadata (file evidence); protected keys are stripped
     *  - `expected_version_number`: client-declared sequence number, validated under the lock
     *  - `generic_lifecycle_status`: optional generic Lifecycle compatibility target
     *  - `document_metadata`: patch merged into the locked document's metadata
     *  - `document_attributes`: non-state document columns applied atomically
     *
     * @param array<string, mixed> $versionData
     */
    public function createVersion(string $tenantId, string $documentId, string $actorId, array $versionData): DocumentVersion
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId, $versionData): DocumentVersion {
            $document = $this->lockDocument($tenantId, $documentId);
            [$lifecycle, $approval] = $this->resolveState($document);

            // Version eligibility: only a not-submitted document may receive a new version.
            // Awaiting content must finish its decision; approved/rejected content must pass
            // through an explicit Reopen first. Version creation never performs that reopen.
            if ($approval !== DocumentApprovalStatus::NOT_SUBMITTED) {
                throw DocumentWorkflowException::versionCreationBlocked($approval->value);
            }

            $genericLifecycle = $versionData['generic_lifecycle_status'] ?? null;
            if (is_string($genericLifecycle)) {
                $lifecycle = $this->mapGenericLifecycle($genericLifecycle);
            }

            $nextVersionNumber = $this->nextVersionNumber($document);
            $expectedVersionNumber = $versionData['expected_version_number'] ?? null;
            if ($expectedVersionNumber !== null && (int) $expectedVersionNumber !== $nextVersionNumber) {
                throw DocumentWorkflowException::versionSequenceMismatch($nextVersionNumber);
            }

            $previousVersionId = $document->current_version_id === null
                ? null
                : (string) $document->current_version_id;
            $approvalEventId = $this->currentApprovalEventId($tenantId, (string) $document->id);

            $documentMetadata = array_merge(
                is_array($document->metadata) ? $document->metadata : [],
                Arr::except(
                    is_array($versionData['document_metadata'] ?? null) ? $versionData['document_metadata'] : [],
                    self::PROTECTED_VERSION_METADATA_KEYS
                )
            );

            $versionMetadata = array_merge(
                Arr::except($documentMetadata, self::PROTECTED_VERSION_METADATA_KEYS),
                Arr::except(
                    is_array($versionData['metadata'] ?? null) ? $versionData['metadata'] : [],
                    self::PROTECTED_VERSION_METADATA_KEYS
                ),
                [
                    'lifecycle_status' => $lifecycle->value,
                    'approval_status' => $approval->value,
                    'status' => $this->resolver->project($lifecycle, $approval),
                    'previous_version_id' => $previousVersionId,
                    'approval_event_id' => $approvalEventId,
                ]
            );

            $version = new DocumentVersion();
            $version->forceFill([
                'document_id' => (string) $document->id,
                'version_number' => $nextVersionNumber,
                'file_path' => (string) ($versionData['file_path'] ?? ''),
                'storage_driver' => (string) ($versionData['storage_driver'] ?? config('filesystems.default', 'local')),
                'comment' => $versionData['comment'] ?? null,
                'metadata' => $versionMetadata,
                'created_by' => $actorId,
                'reverted_from_version_number' => $versionData['reverted_from_version_number'] ?? null,
            ]);
            $version->save();

            $documentAttributes = Arr::except(
                is_array($versionData['document_attributes'] ?? null) ? $versionData['document_attributes'] : [],
                self::PROTECTED_DOCUMENT_ATTRIBUTES
            );
            $document->forceFill(array_merge($documentAttributes, [
                'metadata' => $documentMetadata,
                'current_version_id' => (string) $version->id,
                'version' => $nextVersionNumber,
                'updated_by' => $actorId,
            ]));

            // An unrelated version write never touches canonical state: writeState() runs
            // only for an explicit generic Lifecycle request, so untouched legacy rows keep
            // their NULL canonical columns and exact legacy values.
            if (is_string($genericLifecycle)) {
                $this->statusService->writeState($document, $lifecycle, $approval, $actorId);
            }

            $document->save();

            return $version;
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

    private function mapGenericLifecycle(string $requested): DocumentLifecycleStatus
    {
        return match ($requested) {
            'active', 'draft' => DocumentLifecycleStatus::DRAFT,
            'review', 'in-review' => DocumentLifecycleStatus::IN_REVIEW,
            default => throw DocumentWorkflowException::invalidGenericLifecycleTarget($requested),
        };
    }

    /**
     * Allocated under the row lock, so concurrent writers can never collide.
     */
    private function nextVersionNumber(Document $document): int
    {
        $highest = (int) DB::table('document_versions')
            ->where('document_id', (string) $document->id)
            ->max('version_number');

        if ($highest === 0) {
            return max(1, (int) $document->version);
        }

        return max($highest, (int) $document->version) + 1;
    }

    private function currentApprovalEventId(string $tenantId, string $documentId): ?string
    {
        $id = DB::table('document_approval_events')
            ->where('tenant_id', $tenantId)
            ->where('document_id', $documentId)
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (string) $id;
    }
}
