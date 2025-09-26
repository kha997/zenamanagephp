<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

class FileRule implements Rule
{
    protected $maxSize;
    protected $allowedTypes;
    protected $allowedExtensions;

    /**
     * Create a new rule instance.
     */
    public function __construct(int $maxSize = 10240, array $allowedTypes = [], array $allowedExtensions = [])
    {
        $this->maxSize = $maxSize; // Size in KB
        $this->allowedTypes = $allowedTypes;
        $this->allowedExtensions = $allowedExtensions;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (!$value instanceof UploadedFile) {
            return false;
        }

        // Check file size
        if ($value->getSize() > ($this->maxSize * 1024)) {
            return false;
        }

        // Check MIME type
        if (!empty($this->allowedTypes) && !in_array($value->getMimeType(), $this->allowedTypes)) {
            return false;
        }

        // Check file extension
        if (!empty($this->allowedExtensions)) {
            $extension = strtolower($value->getClientOriginalExtension());
            if (!in_array($extension, $this->allowedExtensions)) {
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
        $message = 'The :attribute must be a valid file';

        if ($this->maxSize) {
            $message .= ' with maximum size of ' . $this->maxSize . ' KB';
        }

        if (!empty($this->allowedTypes)) {
            $message .= ' and must be one of: ' . implode(', ', $this->allowedTypes);
        }

        if (!empty($this->allowedExtensions)) {
            $message .= ' with extension: ' . implode(', ', $this->allowedExtensions);
        }

        return $message . '.';
    }
}
