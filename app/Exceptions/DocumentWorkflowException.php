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

    public static function invalidPublishTransition(string $currentStatus): self
    {
        return new self(
            'INVALID_PUBLISH_TRANSITION',
            "Document can only be published from draft or in-review lifecycle state (current: {$currentStatus})."
        );
    }

    public static function invalidArchiveTransition(string $currentStatus): self
    {
        return new self(
            'INVALID_ARCHIVE_TRANSITION',
            "Document can only be archived from published lifecycle state (current: {$currentStatus})."
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

    /**
     * Version creation is only eligible while Approval is not-submitted. Awaiting content
     * must complete its decision first; approved/rejected content must pass through an
     * explicit Reopen. Version creation itself never reopens or normalises Approval.
     */
    public static function versionCreationBlocked(string $currentApprovalStatus): self
    {
        return new self(
            'VERSION_CREATION_BLOCKED',
            "Document versions can only be created while the approval dimension is not-submitted (current: {$currentApprovalStatus})."
        );
    }

    public static function versionSequenceMismatch(int $expectedVersionNumber): self
    {
        return new self(
            'VERSION_SEQUENCE_MISMATCH',
            "Version must match the next sequential version number ({$expectedVersionNumber})."
        );
    }

    public static function invalidGenericLifecycleTarget(string $requestedStatus): self
    {
        return new self(
            'INVALID_GENERIC_LIFECYCLE_TARGET',
            "Unsupported generic lifecycle status (requested: {$requestedStatus})."
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
