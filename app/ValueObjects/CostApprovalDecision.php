<?php declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Cost Approval Decision Value Object
 * 
 * Round 239: Cost Approval Policies (Phase 1 - Thresholds & Blocking)
 * 
 * Represents the result of evaluating a cost approval policy.
 */
class CostApprovalDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $code = null,
        public readonly ?array $details = null
    ) {
    }

    /**
     * Create an allowed decision
     */
    public static function allowed(): self
    {
        return new self(true);
    }

    /**
     * Create a denied decision
     */
    public static function denied(string $reason, string $code, ?array $details = null): self
    {
        return new self(false, $reason, $code, $details);
    }

    /**
     * Check if decision is allowed
     */
    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Check if decision is denied
     */
    public function isDenied(): bool
    {
        return !$this->allowed;
    }
}
