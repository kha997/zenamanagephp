<?php declare(strict_types=1);

namespace App\Enums;

enum DocumentApprovalStatus: string
{
    case NOT_SUBMITTED = 'not-submitted';
    case AWAITING_APPROVAL = 'awaiting-approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
