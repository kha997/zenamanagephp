<?php declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\DocumentWorkflowStatus;
use PHPUnit\Framework\TestCase;

class DocumentWorkflowStatusTest extends TestCase
{
    public function test_reserved_returns_submitted_approved_rejected(): void
    {
        $this->assertSame(
            [DocumentWorkflowStatus::SUBMITTED, DocumentWorkflowStatus::APPROVED, DocumentWorkflowStatus::REJECTED],
            DocumentWorkflowStatus::reserved()
        );
    }

    public function test_reserved_values_returns_string_array(): void
    {
        $this->assertSame(['submitted', 'approved', 'rejected'], DocumentWorkflowStatus::reservedValues());
    }

    public function test_is_reserved_true_for_workflow_statuses(): void
    {
        $this->assertTrue(DocumentWorkflowStatus::isReserved('submitted'));
        $this->assertTrue(DocumentWorkflowStatus::isReserved('approved'));
        $this->assertTrue(DocumentWorkflowStatus::isReserved('rejected'));
    }

    public function test_is_reserved_false_for_draft_and_legacy_statuses(): void
    {
        $this->assertFalse(DocumentWorkflowStatus::isReserved('draft'));
        $this->assertFalse(DocumentWorkflowStatus::isReserved('active'));
        $this->assertFalse(DocumentWorkflowStatus::isReserved('review'));
    }
}
