<?php declare(strict_types=1);

namespace App\Support\Dashboard;

enum Freshness: string
{
    case CURRENT = 'CURRENT';
    case STALE = 'STALE';
    case UNKNOWN = 'UNKNOWN';
}
