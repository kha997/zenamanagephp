<?php

declare(strict_types=1);

namespace App\Services\TimeTracking;

use RuntimeException;

/**
 * GAP-112 — a time-logging or review rule was broken; the message is shown to the user, the field names the input.
 */
class TimeRuleViolation extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
