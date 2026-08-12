<?php declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DocumentApproverAssignmentException extends RuntimeException
{
    private function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function documentNotFound(): self
    {
        return new self('DOCUMENT_NOT_FOUND', 'Document not found for this tenant.');
    }

    public static function tenantMismatch(): self
    {
        return new self(
            'APPROVER_TENANT_MISMATCH',
            'The proposed approver does not belong to this document\'s tenant.'
        );
    }

    public static function targetLacksApprovalPermission(): self
    {
        return new self(
            'APPROVER_LACKS_PERMISSION',
            'The proposed approver does not currently hold document approval permission.'
        );
    }
}
