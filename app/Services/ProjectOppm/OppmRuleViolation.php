<?php

declare(strict_types=1);

namespace App\Services\ProjectOppm;

use RuntimeException;

/**
 * GAP-110 — an OPPM overlay / decision / snapshot rule was broken; the message is shown to the user, the field names
 * the input.
 */
class OppmRuleViolation extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
