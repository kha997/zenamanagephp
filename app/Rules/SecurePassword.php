<?php

namespace App\Rules;

use App\Services\PasswordSecurityService;
use Illuminate\Contracts\Validation\Rule;

class SecurePassword implements Rule
{
    protected $passwordService;
    protected $errors = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->passwordService = app(PasswordSecurityService::class);
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_string($value)) {
            $this->errors = ['Password must be a string'];
            return false;
        }

        $validation = $this->passwordService->validatePasswordStrength($value);
        
        if (!$validation['valid']) {
            $this->errors = $validation['errors'];
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        if (empty($this->errors)) {
            return 'The password does not meet security requirements.';
        }

        return implode(' ', $this->errors);
    }

    /**
     * Get all validation errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
