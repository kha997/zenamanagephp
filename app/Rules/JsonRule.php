<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class JsonRule implements Rule
{
    protected $schema;
    protected $requiredFields;
    protected $allowedFields;

    /**
     * Create a new rule instance.
     */
    public function __construct(array $schema = [], array $requiredFields = [], array $allowedFields = [])
    {
        $this->schema = $schema;
        $this->requiredFields = $requiredFields;
        $this->allowedFields = $allowedFields;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if (empty($value)) {
            return true; // Let required rule handle empty values
        }

        // Check if value is valid JSON
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Check if decoded value is an array or object
        if (!is_array($decoded)) {
            return false;
        }

        // Check required fields
        foreach ($this->requiredFields as $field) {
            if (!array_key_exists($field, $decoded)) {
                return false;
            }
        }

        // Check allowed fields
        if (!empty($this->allowedFields)) {
            foreach (array_keys($decoded) as $field) {
                if (!in_array($field, $this->allowedFields)) {
                    return false;
                }
            }
        }

        // Check schema if provided
        if (!empty($this->schema)) {
            foreach ($this->schema as $field => $rules) {
                if (array_key_exists($field, $decoded)) {
                    if (!$this->validateField($decoded[$field], $rules)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Validate a field against its rules.
     */
    protected function validateField($value, $rules): bool
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        foreach ($rules as $rule) {
            if (!$this->applyRule($value, $rule)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply a single rule to a value.
     */
    protected function applyRule($value, string $rule): bool
    {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $ruleValue = $parts[1] ?? null;

        switch ($ruleName) {
            case 'required':
                return !empty($value);
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value);
            case 'numeric':
                return is_numeric($value);
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'min':
                return is_numeric($value) && $value >= $ruleValue;
            case 'max':
                return is_numeric($value) && $value <= $ruleValue;
            case 'min_length':
                return is_string($value) && strlen($value) >= $ruleValue;
            case 'max_length':
                return is_string($value) && strlen($value) <= $ruleValue;
            default:
                return true;
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        $message = 'The :attribute must be valid JSON';

        if (!empty($this->requiredFields)) {
            $message .= ' with required fields: ' . implode(', ', $this->requiredFields);
        }

        if (!empty($this->allowedFields)) {
            $message .= ' and only allowed fields: ' . implode(', ', $this->allowedFields);
        }

        return $message . '.';
    }
}
