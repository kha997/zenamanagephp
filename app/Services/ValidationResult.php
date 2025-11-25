<?php declare(strict_types=1);

namespace App\Services;

/**
 * Validation Result Helper
 * 
 * Represents the result of a validation operation with optional error and warning messages.
 */
class ValidationResult
{
    /**
     * Whether the validation passed
     * 
     * @var bool
     */
    public bool $isValid;

    /**
     * Error message if validation failed
     * 
     * @var string|null
     */
    public ?string $error = null;

    /**
     * Warning message if validation passed but with warnings
     * 
     * @var string|null
     */
    public ?string $warning = null;

    /**
     * Error code for structured error handling
     * 
     * @var string|null
     */
    public ?string $errorCode = null;

    /**
     * Additional error details (e.g., related IDs, allowed values)
     * 
     * @var array|null
     */
    public ?array $details = null;

    /**
     * Constructor
     * 
     * @param bool $isValid
     * @param string|null $error
     * @param string|null $warning
     * @param string|null $errorCode
     * @param array|null $details
     */
    public function __construct(
        bool $isValid,
        ?string $error = null,
        ?string $warning = null,
        ?string $errorCode = null,
        ?array $details = null
    ) {
        $this->isValid = $isValid;
        $this->error = $error;
        $this->warning = $warning;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    /**
     * Create a successful validation result
     * 
     * @return self
     */
    public static function success(): self
    {
        return new self(true);
    }

    /**
     * Create a failed validation result with error message
     * 
     * @param string $message
     * @param string|null $errorCode
     * @param array|null $details
     * @return self
     */
    public static function error(string $message, ?string $errorCode = null, ?array $details = null): self
    {
        return new self(false, error: $message, errorCode: $errorCode, details: $details);
    }

    /**
     * Create a successful validation result with warning
     * 
     * @param string $message
     * @param string|null $errorCode
     * @param array|null $details
     * @return self
     */
    public static function warning(string $message, ?string $errorCode = null, ?array $details = null): self
    {
        return new self(true, warning: $message, errorCode: $errorCode, details: $details);
    }
}

