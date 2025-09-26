<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class InputValidationService
{
    /**
     * Validate and sanitize input
     */
    public function validateInput(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }
        
        return $this->sanitizeInput($data);
    }
    
    /**
     * Sanitize input data
     */
    private function sanitizeInput(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            }
        }
        
        return $data;
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file, array $allowedTypes = ['jpg', 'png', 'pdf']): bool
    {
        if (!$file || !$file->isValid()) {
            return false;
        }
        
        $extension = $file->getClientOriginalExtension();
        return in_array(strtolower($extension), $allowedTypes);
    }
}