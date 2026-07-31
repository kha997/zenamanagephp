<?php declare(strict_types=1);

namespace App\Support\Dashboard;

enum Availability: string
{
    case AVAILABLE = 'AVAILABLE';
    case NO_DATA = 'NO_DATA';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
    case ERROR = 'ERROR';
}
