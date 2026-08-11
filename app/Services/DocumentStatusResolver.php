<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;

final class DocumentStatusResolver
{
    public function lifecycle(?string $rawLifecycleStatus, string $legacyStatus): ?DocumentLifecycleStatus
    {
        if ($rawLifecycleStatus !== null) {
            return DocumentLifecycleStatus::tryFrom($rawLifecycleStatus);
        }

        return match ($legacyStatus) {
            'active', 'draft' => DocumentLifecycleStatus::DRAFT,
            'review' => DocumentLifecycleStatus::IN_REVIEW,
            'published' => DocumentLifecycleStatus::PUBLISHED,
            'archived' => DocumentLifecycleStatus::ARCHIVED,
            default => null,
        };
    }

    public function approval(?string $rawApprovalStatus, string $legacyStatus): ?DocumentApprovalStatus
    {
        if ($rawApprovalStatus !== null) {
            return DocumentApprovalStatus::tryFrom($rawApprovalStatus);
        }

        return match ($legacyStatus) {
            'active', 'draft', 'review', 'published', 'archived' => DocumentApprovalStatus::NOT_SUBMITTED,
            'submitted' => DocumentApprovalStatus::AWAITING_APPROVAL,
            'approved' => DocumentApprovalStatus::APPROVED,
            'rejected' => DocumentApprovalStatus::REJECTED,
            default => null,
        };
    }

    public function project(DocumentLifecycleStatus $lifecycle, DocumentApprovalStatus $approval): string
    {
        return match ($approval) {
            DocumentApprovalStatus::AWAITING_APPROVAL => 'submitted',
            DocumentApprovalStatus::APPROVED => 'approved',
            DocumentApprovalStatus::REJECTED => 'rejected',
            DocumentApprovalStatus::NOT_SUBMITTED => match ($lifecycle) {
                DocumentLifecycleStatus::DRAFT => 'draft',
                DocumentLifecycleStatus::IN_REVIEW => 'review',
                DocumentLifecycleStatus::PUBLISHED => 'published',
                DocumentLifecycleStatus::ARCHIVED => 'archived',
            },
        };
    }

    public function compatibilityStatus(?DocumentLifecycleStatus $lifecycle, ?DocumentApprovalStatus $approval, string $legacyStatus): string
    {
        if ($lifecycle === null || $approval === null) {
            return $legacyStatus;
        }

        return $this->project($lifecycle, $approval);
    }
}
