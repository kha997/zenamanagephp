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

    public static function invalidReopenTransition(string $currentStatus): self
    {
        return new self(
            'INVALID_REOPEN_TRANSITION',
            "Document can only be reopened after an approval decision (current: {$currentStatus})."
        );
    }

    public static function invalidReactivateTransition(string $currentStatus): self
    {
        return new self(
            'INVALID_REACTIVATE_TRANSITION',
            "Document can only be reactivated from archived lifecycle state (current: {$currentStatus})."
        );
    }

    public static function invalidCanonicalState(string $currentStatus): self
    {
        return new self(
            'INVALID_CANONICAL_STATE',
            "Document status cannot be resolved safely (current: {$currentStatus})."
        );
    }

    public static function invalidCurrentVersion(): self
    {
        return new self(
            'INVALID_CURRENT_VERSION',
            'Document current version is missing or does not belong to the tenant document.'
        );
    }

    public static function legacyApprovalReconciliationRequired(): self
    {
        return new self(
            'LEGACY_APPROVAL_RECONCILIATION_REQUIRED',
            'Document approval history lacks consistent version-bound event lineage.'
        );
    }

    public static function documentNotFound(): self
    {
        return new self('DOCUMENT_NOT_FOUND', 'Document not found for this tenant.');
    }
}
