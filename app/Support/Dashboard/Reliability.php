<?php declare(strict_types=1);

namespace App\Support\Dashboard;

enum Reliability: string
{
    case RELIABLE = 'RELIABLE';
    case LIMITED = 'LIMITED';
    case LEGACY = 'LEGACY';
    case UNKNOWN = 'UNKNOWN';
}
