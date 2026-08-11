<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
use App\Models\Document;
use App\Models\Support\DocumentStatusResolver;
use App\Services\DocumentStatusService;
use PHPUnit\Framework\TestCase;

class DocumentStatusServiceTest extends TestCase
{
    private DocumentStatusResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new DocumentStatusResolver();
    }

    public function test_legacy_statuses_resolve_to_deterministic_canonical_dimensions(): void
    {
        $cases = [
            ['active', DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED],
            ['draft', DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED],
            ['review', DocumentLifecycleStatus::IN_REVIEW, DocumentApprovalStatus::NOT_SUBMITTED],
            ['published', DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED],
            ['archived', DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::NOT_SUBMITTED],
        ];

        foreach ($cases as [$legacy, $lifecycle, $approval]) {
            self::assertSame($lifecycle, $this->resolver->lifecycle(null, $legacy));
            self::assertSame($approval, $this->resolver->approval(null, $legacy));
        }
    }

    public function test_legacy_submitted_resolves_null_lifecycle_and_awaiting_approval(): void
    {
        self::assertNull($this->resolver->lifecycle(null, 'submitted'));
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL, $this->resolver->approval(null, 'submitted'));
    }

    public function test_legacy_approved_resolves_null_lifecycle_and_approved(): void
    {
        self::assertNull($this->resolver->lifecycle(null, 'approved'));
        self::assertSame(DocumentApprovalStatus::APPROVED, $this->resolver->approval(null, 'approved'));
    }

    public function test_legacy_rejected_resolves_null_lifecycle_and_rejected(): void
    {
        self::assertNull($this->resolver->lifecycle(null, 'rejected'));
        self::assertSame(DocumentApprovalStatus::REJECTED, $this->resolver->approval(null, 'rejected'));
    }

    public function test_unknown_legacy_status_remains_unresolved_without_being_rewritten(): void
    {
        self::assertNull($this->resolver->lifecycle(null, 'legacy-custom-state'));
        self::assertNull($this->resolver->approval(null, 'legacy-custom-state'));
        self::assertSame('legacy-custom-state', $this->resolver->compatibilityStatus(null, null, 'legacy-custom-state'));
    }

    public function test_all_canonical_combinations_project_to_the_binding_legacy_status(): void
    {
        $cases = [
            [DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED, 'draft'],
            [DocumentLifecycleStatus::IN_REVIEW, DocumentApprovalStatus::NOT_SUBMITTED, 'review'],
            [DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::NOT_SUBMITTED, 'published'],
            [DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::NOT_SUBMITTED, 'archived'],
            [DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::AWAITING_APPROVAL, 'submitted'],
            [DocumentLifecycleStatus::IN_REVIEW, DocumentApprovalStatus::AWAITING_APPROVAL, 'submitted'],
            [DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::AWAITING_APPROVAL, 'submitted'],
            [DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::AWAITING_APPROVAL, 'submitted'],
            [DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::APPROVED, 'approved'],
            [DocumentLifecycleStatus::IN_REVIEW, DocumentApprovalStatus::APPROVED, 'approved'],
            [DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::APPROVED, 'approved'],
            [DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::APPROVED, 'approved'],
            [DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::REJECTED, 'rejected'],
            [DocumentLifecycleStatus::IN_REVIEW, DocumentApprovalStatus::REJECTED, 'rejected'],
            [DocumentLifecycleStatus::PUBLISHED, DocumentApprovalStatus::REJECTED, 'rejected'],
            [DocumentLifecycleStatus::ARCHIVED, DocumentApprovalStatus::REJECTED, 'rejected'],
        ];

        foreach ($cases as [$lifecycle, $approval, $legacy]) {
            self::assertSame($legacy, $this->resolver->project($lifecycle, $approval));
        }
    }

    public function test_write_state_sets_both_columns_and_identical_status_projections(): void
    {
        $document = new Document();
        $document->setRawAttributes([
            'status' => 'active',
            'metadata' => json_encode(['status' => 'active', 'preserved' => true], JSON_THROW_ON_ERROR),
        ]);

        (new DocumentStatusService($this->resolver))->writeState(
            $document,
            DocumentLifecycleStatus::PUBLISHED,
            DocumentApprovalStatus::AWAITING_APPROVAL,
            '01JACTOR000000000000000001'
        );

        self::assertSame('published', $document->getAttributes()['lifecycle_status']);
        self::assertSame('awaiting-approval', $document->getAttributes()['approval_status']);
        self::assertSame('submitted', $document->getAttribute('status'));
        self::assertSame('submitted', $document->metadata['status']);
        self::assertTrue($document->metadata['preserved']);
        self::assertSame('01JACTOR000000000000000001', $document->getAttribute('updated_by'));
    }

    public function test_canonical_accessors_resolve_raw_originals_instead_of_pending_dirty_values(): void
    {
        $document = new Document();
        $document->setRawAttributes([
            'status' => 'review',
            'lifecycle_status' => null,
            'approval_status' => null,
        ], true);
        $document->forceFill([
            'lifecycle_status' => 'published',
            'approval_status' => 'approved',
        ]);

        self::assertSame('in-review', $document->getAttribute('lifecycle_status'));
        self::assertSame('not-submitted', $document->getAttribute('approval_status'));
        self::assertSame('published', $document->getAttributes()['lifecycle_status']);
        self::assertSame('approved', $document->getAttributes()['approval_status']);
    }
}
