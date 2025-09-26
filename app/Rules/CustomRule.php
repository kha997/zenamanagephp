<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class CustomRule implements Rule
{
    protected $callback;
    protected $message;

    /**
     * Create a new rule instance.
     */
    public function __construct(callable $callback, string $message = 'The :attribute is invalid.')
    {
        $this->callback = $callback;
        $this->message = $message;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        return call_user_func($this->callback, $attribute, $value);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return $this->message;
    }
}
