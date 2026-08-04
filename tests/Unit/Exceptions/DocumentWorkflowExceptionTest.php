<?php declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\DocumentWorkflowException;
use PHPUnit\Framework\TestCase;

class DocumentWorkflowExceptionTest extends TestCase
{
    public function test_invalid_submit_transition_has_reason_code_and_current_status_in_message(): void
    {
        $e = DocumentWorkflowException::invalidSubmitTransition('approved');

        $this->assertSame('INVALID_SUBMIT_TRANSITION', $e->reasonCode);
        $this->assertStringContainsString('approved', $e->getMessage());
    }

    public function test_invalid_decision_transition_has_reason_code_and_current_status_in_message(): void
    {
        $e = DocumentWorkflowException::invalidDecisionTransition('draft');

        $this->assertSame('INVALID_DECISION_TRANSITION', $e->reasonCode);
        $this->assertStringContainsString('draft', $e->getMessage());
    }

    public function test_document_not_found_has_reason_code(): void
    {
        $e = DocumentWorkflowException::documentNotFound();

        $this->assertSame('DOCUMENT_NOT_FOUND', $e->reasonCode);
    }
}
