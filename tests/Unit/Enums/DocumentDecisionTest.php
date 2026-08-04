<?php declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\DocumentDecision;
use App\Enums\DocumentWorkflowStatus;
use PHPUnit\Framework\TestCase;

class DocumentDecisionTest extends TestCase
{
    public function test_approved_to_workflow_status_maps_to_approved(): void
    {
        $this->assertSame(DocumentWorkflowStatus::APPROVED, DocumentDecision::APPROVED->toWorkflowStatus());
    }

    public function test_rejected_to_workflow_status_maps_to_rejected(): void
    {
        $this->assertSame(DocumentWorkflowStatus::REJECTED, DocumentDecision::REJECTED->toWorkflowStatus());
    }

    public function test_decision_values_match_workflow_status_values(): void
    {
        $this->assertSame(DocumentWorkflowStatus::APPROVED->value, DocumentDecision::APPROVED->value);
        $this->assertSame(DocumentWorkflowStatus::REJECTED->value, DocumentDecision::REJECTED->value);
    }
}
