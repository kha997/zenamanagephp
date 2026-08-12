<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
use App\Models\Document;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DocumentCreationService
{
    /** @var list<string> */
    private const FORCED_ATTRIBUTES = [
        'tenant_id',
        'uploaded_by',
        'created_by',
        'updated_by',
        'status',
        'lifecycle_status',
        'approval_status',
    ];

    public function __construct(private readonly DocumentStatusService $statusService)
    {
    }

    /**
     * The caller supplies server-built document attributes only. Tenant, actor, and
     * canonical status are intentionally reconstructed at this boundary.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes, string $tenantId, string $actorId): Document
    {
        return DB::transaction(function () use ($attributes, $tenantId, $actorId): Document {
            $document = new Document();
            $document->forceFill(Arr::except($attributes, self::FORCED_ATTRIBUTES));
            $document->forceFill([
                'tenant_id' => $tenantId,
                'uploaded_by' => $actorId,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->statusService->writeState(
                $document,
                DocumentLifecycleStatus::DRAFT,
                DocumentApprovalStatus::NOT_SUBMITTED,
                $actorId,
            );
            $document->save();

            return $document;
        });
    }
}
