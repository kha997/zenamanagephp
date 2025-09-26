<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PasswordRule implements Rule
{
    protected $minLength;
    protected $requireUppercase;
    protected $requireLowercase;
    protected $requireNumbers;
    protected $requireSymbols;

    /**
     * Create a new rule instance.
     */
    public function __construct(
        int $minLength = 8,
        bool $requireUppercase = true,
        bool $requireLowercase = true,
        bool $requireNumbers = true,
        bool $requireSymbols = false
    ) {
        $this->minLength = $minLength;
        $this->requireUppercase = $requireUppercase;
        $this->requireLowercase = $requireLowercase;
        $this->requireNumbers = $requireNumbers;
        $this->requireSymbols = $requireSymbols;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return true; // Let required rule handle empty values
        }

        // Check minimum length
        if (strlen($value) < $this->minLength) {
            return false;
        }

        // Check for uppercase letters
        if ($this->requireUppercase && !preg_match('/[A-Z]/', $value)) {
            return false;
        }

        // Check for lowercase letters
        if ($this->requireLowercase && !preg_match('/[a-z]/', $value)) {
            return false;
        }

        // Check for numbers
        if ($this->requireNumbers && !preg_match('/[0-9]/', $value)) {
            return false;
        }

        // Check for symbols
        if ($this->requireSymbols && !preg_match('/[^A-Za-z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        $requirements = [];

        $requirements[] = 'at least ' . $this->minLength . ' characters';

        if ($this->requireUppercase) {
            $requirements[] = 'one uppercase letter';
        }

        if ($this->requireLowercase) {
            $requirements[] = 'one lowercase letter';
        }

        if ($this->requireNumbers) {
            $requirements[] = 'one number';
        }

        if ($this->requireSymbols) {
            $requirements[] = 'one symbol';
        }

        return 'The :attribute must contain ' . implode(', ', $requirements) . '.';
    }
}
