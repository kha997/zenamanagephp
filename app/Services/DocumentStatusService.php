<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;

class DocumentStatusService
{
    public function __construct(private readonly DocumentStatusResolver $resolver)
    {
    }

    public function lifecycle(Document $document): ?DocumentLifecycleStatus
    {
        return $this->resolver->lifecycle(
            $document->getRawOriginal('lifecycle_status'),
            (string) $document->getRawOriginal('status')
        );
    }

    public function approval(Document $document): ?DocumentApprovalStatus
    {
        return $this->resolver->approval(
            $document->getRawOriginal('approval_status'),
            (string) $document->getRawOriginal('status')
        );
    }

    public function applyLegacyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active', 'draft' => $this->applyLifecycleProjectionFilter(
                $query,
                DocumentLifecycleStatus::DRAFT,
                ['active', 'draft']
            ),
            'review', 'in-review' => $this->applyLifecycleProjectionFilter(
                $query,
                DocumentLifecycleStatus::IN_REVIEW,
                ['review']
            ),
            'published' => $this->applyLifecycleProjectionFilter(
                $query,
                DocumentLifecycleStatus::PUBLISHED,
                ['published']
            ),
            'archived' => $this->applyLifecycleProjectionFilter(
                $query,
                DocumentLifecycleStatus::ARCHIVED,
                ['archived']
            ),
            'submitted', 'pending', 'awaiting-approval' => $this->applyApprovalProjectionFilter(
                $query,
                DocumentApprovalStatus::AWAITING_APPROVAL,
                ['submitted']
            ),
            'approved' => $this->applyApprovalProjectionFilter($query, DocumentApprovalStatus::APPROVED, ['approved']),
            'rejected' => $this->applyApprovalProjectionFilter($query, DocumentApprovalStatus::REJECTED, ['rejected']),
            default => $query->where(function (Builder $legacy) use ($status): void {
                $legacy->whereNull('lifecycle_status')
                    ->whereNull('approval_status')
                    ->where('status', $status);
            }),
        };
    }

    public function applyLifecycleFilter(Builder $query, DocumentLifecycleStatus $status): Builder
    {
        $legacyStatuses = match ($status) {
            DocumentLifecycleStatus::DRAFT => ['active', 'draft'],
            DocumentLifecycleStatus::IN_REVIEW => ['review'],
            DocumentLifecycleStatus::PUBLISHED => ['published'],
            DocumentLifecycleStatus::ARCHIVED => ['archived'],
        };

        return $query->where(function (Builder $filter) use ($status, $legacyStatuses): void {
            $filter->where('lifecycle_status', $status->value)
                ->orWhere(function (Builder $legacy) use ($legacyStatuses): void {
                    $legacy->whereNull('lifecycle_status')
                        ->whereIn('status', $legacyStatuses);
                });
        });
    }

    public function applyApprovalFilter(Builder $query, DocumentApprovalStatus $status): Builder
    {
        $legacyStatuses = match ($status) {
            DocumentApprovalStatus::NOT_SUBMITTED => ['active', 'draft', 'review', 'published', 'archived'],
            DocumentApprovalStatus::AWAITING_APPROVAL => ['submitted'],
            DocumentApprovalStatus::APPROVED => ['approved'],
            DocumentApprovalStatus::REJECTED => ['rejected'],
        };

        return $query->where(function (Builder $filter) use ($status, $legacyStatuses): void {
            $filter->where('approval_status', $status->value)
                ->orWhere(function (Builder $legacy) use ($legacyStatuses): void {
                    $legacy->whereNull('approval_status')
                        ->whereIn('status', $legacyStatuses);
                });
        });
    }

    public function writeState(
        Document $document,
        DocumentLifecycleStatus $lifecycle,
        DocumentApprovalStatus $approval,
        string $actorId,
    ): void {
        $metadata = $document->metadata ?? [];
        $legacy = $this->resolver->project($lifecycle, $approval);
        $metadata['status'] = $legacy;
        $document->forceFill([
            'lifecycle_status' => $lifecycle->value,
            'approval_status' => $approval->value,
            'status' => $legacy,
            'metadata' => $metadata,
            'updated_by' => $actorId,
        ]);
    }

    /** @param array<int, string> $legacyStatuses */
    private function applyLifecycleProjectionFilter(Builder $query, DocumentLifecycleStatus $status, array $legacyStatuses): Builder
    {
        return $query->where(function (Builder $filter) use ($status, $legacyStatuses): void {
            $filter->where(function (Builder $canonical) use ($status): void {
                $canonical->where('lifecycle_status', $status->value)
                    ->where('approval_status', DocumentApprovalStatus::NOT_SUBMITTED->value);
            })->orWhere(function (Builder $legacy) use ($legacyStatuses): void {
                $legacy->whereNull('lifecycle_status')
                    ->whereNull('approval_status')
                    ->whereIn('status', $legacyStatuses);
            });
        });
    }

    /** @param array<int, string> $legacyStatuses */
    private function applyApprovalProjectionFilter(Builder $query, DocumentApprovalStatus $status, array $legacyStatuses): Builder
    {
        return $query->where(function (Builder $filter) use ($status, $legacyStatuses): void {
            $filter->where('approval_status', $status->value)
                ->orWhere(function (Builder $legacy) use ($legacyStatuses): void {
                    $legacy->whereNull('lifecycle_status')
                        ->whereNull('approval_status')
                        ->whereIn('status', $legacyStatuses);
            });
        });
    }
}
