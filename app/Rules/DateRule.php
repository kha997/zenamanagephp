<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Carbon\Carbon;

class DateRule implements Rule
{
    protected $format;
    protected $minDate;
    protected $maxDate;

    /**
     * Create a new rule instance.
     */
    public function __construct(string $format = 'Y-m-d', $minDate = null, $maxDate = null)
    {
        $this->format = $format;
        $this->minDate = $minDate;
        $this->maxDate = $maxDate;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return true; // Let required rule handle empty values
        }

        try {
            $date = Carbon::createFromFormat($this->format, $value);

            if ($this->minDate && $date->lt($this->minDate)) {
                return false;
            }

            if ($this->maxDate && $date->gt($this->maxDate)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        $message = 'The :attribute must be a valid date in format ' . $this->format;

        if ($this->minDate) {
            $message .= ' and must be after ' . $this->minDate->format($this->format);
        }

        if ($this->maxDate) {
            $message .= ' and must be before ' . $this->maxDate->format($this->format);
        }

        return $message . '.';
    }
}
