<?php declare(strict_types=1);

namespace App\Enums;

enum DocumentDecision: string
{
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function toWorkflowStatus(): DocumentWorkflowStatus
    {
        return match ($this) {
            self::APPROVED => DocumentWorkflowStatus::APPROVED,
            self::REJECTED => DocumentWorkflowStatus::REJECTED,
        };
    }
}
