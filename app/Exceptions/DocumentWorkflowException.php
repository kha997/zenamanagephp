<?php declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DocumentWorkflowException extends RuntimeException
{
    private function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalidSubmitTransition(string $currentStatus): self
    {
        return new self(
            'INVALID_SUBMIT_TRANSITION',
            "Document can only be submitted from draft status (current: {$currentStatus})."
        );
    }

    public static function invalidDecisionTransition(string $currentStatus): self
    {
        return new self(
            'INVALID_DECISION_TRANSITION',
            "Document can only be decided from submitted status (current: {$currentStatus})."
        );
    }

    public static function documentNotFound(): self
    {
        return new self('DOCUMENT_NOT_FOUND', 'Document not found for this tenant.');
    }
}
