<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class UrlRule implements Rule
{
    protected $protocols;
    protected $requireProtocol;

    /**
     * Create a new rule instance.
     */
    public function __construct(array $protocols = ['http', 'https'], bool $requireProtocol = true)
    {
        $this->protocols = $protocols;
        $this->requireProtocol = $requireProtocol;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return true; // Let required rule handle empty values
        }

        // If protocol is required, check if URL starts with allowed protocol
        if ($this->requireProtocol) {
            $hasValidProtocol = false;
            foreach ($this->protocols as $protocol) {
                if (str_starts_with(strtolower($value), $protocol . '://')) {
                    $hasValidProtocol = true;
                    break;
                }
            }
            
            if (!$hasValidProtocol) {
                return false;
            }
        }

        // Use filter_var to validate URL
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        $message = 'The :attribute must be a valid URL';

        if ($this->requireProtocol) {
            $message .= ' starting with ' . implode(' or ', $this->protocols) . '://';
        }

        return $message . '.';
    }
}
