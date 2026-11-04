<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use RuntimeException;

/**
 * GAP-109 — a staff or payroll business rule was broken; the message is shown to the user, the field names the input.
 */
class PayrollRuleViolation extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
