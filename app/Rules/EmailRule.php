<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class EmailRule implements Rule
{
    protected $allowMultiple;
    protected $maxEmails;

    /**
     * Create a new rule instance.
     */
    public function __construct(bool $allowMultiple = false, int $maxEmails = 10)
    {
        $this->allowMultiple = $allowMultiple;
        $this->maxEmails = $maxEmails;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return true; // Let required rule handle empty values
        }

        if ($this->allowMultiple) {
            $emails = array_map('trim', explode(',', $value));
            
            if (count($emails) > $this->maxEmails) {
                return false;
            }

            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
            }
        } else {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        if ($this->allowMultiple) {
            return 'The :attribute must contain valid email addresses separated by commas (maximum ' . $this->maxEmails . ' emails).';
        }

        return 'The :attribute must be a valid email address.';
    }
}
