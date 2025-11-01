<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input Validation Middleware
 * 
 * Provides comprehensive input validation and sanitization:
 * - XSS prevention
 * - SQL injection prevention
 * - File upload validation
 * - Input sanitization
 */
class InputValidationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Sanitize all input data
            $this->sanitizeInput($request);
            
            // Validate file uploads
            if ($request->hasFile('*')) {
                $this->validateFileUploads($request);
            }
            
            // Check for suspicious patterns
            if ($this->hasSuspiciousPatterns($request)) {
                return $this->handleSuspiciousInput($request);
            }
            
            return $next($request);
            
        } catch (\Exception $e) {
            Log::error('Input validation failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-Id')
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Invalid input detected.',
                'code' => 'INPUT_VALIDATION_FAILED'
            ], 400);
        }
    }
    
    /**
     * Sanitize all input data
     */
    private function sanitizeInput(Request $request): void
    {
        $allInput = $request->all();
        
        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                // Remove potential XSS
                $sanitized = $this->sanitizeString($value);
                $request->merge([$key => $sanitized]);
            } elseif (is_array($value)) {
                // Recursively sanitize arrays
                $sanitized = $this->sanitizeArray($value);
                $request->merge([$key => $sanitized]);
            }
        }
    }
    
    /**
     * Sanitize string input
     */
    private function sanitizeString(string $input): string
    {
        // Remove HTML tags
        $input = strip_tags($input);
        
        // Escape special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Remove control characters
        $input = preg_replace('/[\x00-\x1F\x7F]/', '', $input);
        
        return trim($input);
    }
    
    /**
     * Sanitize array input
     */
    private function sanitizeArray(array $input): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate file uploads
     */
    private function validateFileUploads(Request $request): void
    {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        foreach ($request->allFiles() as $key => $files) {
            if (!is_array($files)) {
                $files = [$files];
            }
            
            foreach ($files as $file) {
                if (!$file->isValid()) {
                    throw new \Exception("Invalid file upload: {$key}");
                }
                
                // Check file size
                if ($file->getSize() > $maxFileSize) {
                    throw new \Exception("File too large: {$key}");
                }
                
                // Check MIME type
                if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                    throw new \Exception("Invalid file type: {$key}");
                }
                
                // Check file extension
                $extension = strtolower($file->getClientOriginalExtension());
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx'];
                
                if (!in_array($extension, $allowedExtensions)) {
                    throw new \Exception("Invalid file extension: {$key}");
                }
            }
        }
    }
    
    /**
     * Check for suspicious patterns
     */
    private function hasSuspiciousPatterns(Request $request): bool
    {
        $allInput = $request->all();
        $inputString = json_encode($allInput);
        
        // SQL injection patterns
        $sqlPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+set/i',
            '/or\s+1\s*=\s*1/i',
            '/and\s+1\s*=\s*1/i',
            '/\';\s*drop/i',
            '/\'\s*or\s*\'/i'
        ];
        
        // XSS patterns
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/i',
            '/<object[^>]*>.*?<\/object>/i',
            '/<embed[^>]*>.*?<\/embed>/i'
        ];
        
        // Check SQL injection patterns
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $inputString)) {
                return true;
            }
        }
        
        // Check XSS patterns
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $inputString)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Handle suspicious input
     */
    private function handleSuspiciousInput(Request $request): Response
    {
        Log::warning('Suspicious input detected', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'request_id' => $request->header('X-Request-Id')
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Suspicious input detected. Request blocked.',
            'code' => 'SUSPICIOUS_INPUT_BLOCKED'
        ], 400);
    }
}
