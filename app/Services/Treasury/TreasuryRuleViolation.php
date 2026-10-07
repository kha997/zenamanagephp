<?php declare(strict_types=1);

namespace App\Services\Treasury;

use RuntimeException;

/**
 * GAP-064: a Treasury domain rule refused the write (insufficient balance,
 * reversal rules, cross-project reference, ...). The message is user-facing
 * Vietnamese; `field` names the input it relates to, when there is one.
 */
class TreasuryRuleViolation extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
