<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PhoneRule implements Rule
{
    protected $format;
    protected $countryCode;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $format = 'international', string $countryCode = null)
    {
        $this->format = $format;
        $this->countryCode = $countryCode;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return true; // Let required rule handle empty values
        }

        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9+]/', '', $value);

        // Check if it's a valid phone number
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $cleaned)) {
            return false;
        }

        // Check country code if specified
        if ($this->countryCode) {
            $expectedPrefix = '+' . $this->countryCode;
            if (!str_starts_with($cleaned, $expectedPrefix)) {
                return false;
            }
        }

        // Check format-specific rules
        switch ($this->format) {
            case 'international':
                return str_starts_with($cleaned, '+');
            case 'national':
                return !str_starts_with($cleaned, '+');
            case 'any':
                return true;
            default:
                return true;
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        $message = 'The :attribute must be a valid phone number';

        if ($this->format === 'international') {
            $message .= ' in international format (starting with +)';
        } elseif ($this->format === 'national') {
            $message .= ' in national format (without +)';
        }

        if ($this->countryCode) {
            $message .= ' with country code ' . $this->countryCode;
        }

        return $message . '.';
    }
}
