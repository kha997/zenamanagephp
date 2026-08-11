<?php declare(strict_types=1);

namespace App\Enums;

enum DocumentLifecycleStatus: string
{
    case DRAFT = 'draft';
    case IN_REVIEW = 'in-review';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
