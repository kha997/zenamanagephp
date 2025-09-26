<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Input Sanitization Service
 * 
 * Provides comprehensive input sanitization for security purposes
 */
class InputSanitizationService
{
    private HTMLPurifier $htmlPurifier;
    
    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,u,ol,ul,li,a[href],h1,h2,h3,h4,h5,h6');
        $config->set('HTML.AllowedAttributes', 'href,title');
        $config->set('AutoFormat.AutoParagraph', true);
        $config->set('AutoFormat.RemoveEmpty', true);
        
        $this->htmlPurifier = new HTMLPurifier($config);
    }

    /**
     * Sanitize string input
     */
    public function sanitizeString(?string $input, bool $allowHtml = false): ?string
    {
        if ($input === null) {
            return null;
        }

        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        if ($allowHtml) {
            // Use HTMLPurifier for HTML content
            return $this->htmlPurifier->purify($input);
        }
        
        // Escape HTML entities for plain text
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize array input recursively
     */
    public function sanitizeArray(array $input, bool $allowHtml = false): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $sanitizedKey = $this->sanitizeString($key, false);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value, $allowHtml);
            } elseif (is_string($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeString($value, $allowHtml);
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize email input
     */
    public function sanitizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = $this->sanitizeString($email, false);
        
        // Additional email-specific sanitization
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        return $email;
    }

    /**
     * Sanitize URL input
     */
    public function sanitizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = $this->sanitizeString($url, false);
        
        // Additional URL-specific sanitization
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        return $url;
    }

    /**
     * Sanitize integer input
     */
    public function sanitizeInteger($input): ?int
    {
        if ($input === null) {
            return null;
        }

        // Convert to string first, then sanitize
        $input = (string) $input;
        $input = $this->sanitizeString($input, false);
        
        // Remove non-numeric characters except minus sign
        $input = preg_replace('/[^0-9\-]/', '', $input);
        
        return is_numeric($input) ? (int) $input : null;
    }

    /**
     * Sanitize float input
     */
    public function sanitizeFloat($input): ?float
    {
        if ($input === null) {
            return null;
        }

        // Convert to string first, then sanitize
        $input = (string) $input;
        $input = $this->sanitizeString($input, false);
        
        // Remove non-numeric characters except minus sign and decimal point
        $input = preg_replace('/[^0-9\-\.]/', '', $input);
        
        return is_numeric($input) ? (float) $input : null;
    }

    /**
     * Sanitize boolean input
     */
    public function sanitizeBoolean($input): ?bool
    {
        if ($input === null) {
            return null;
        }

        if (is_bool($input)) {
            return $input;
        }

        $input = (string) $input;
        $input = strtolower(trim($input));
        
        return in_array($input, ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Sanitize file name
     */
    public function sanitizeFileName(?string $fileName): ?string
    {
        if ($fileName === null) {
            return null;
        }

        // Remove path traversal attempts
        $fileName = str_replace(['../', '..\\', '/', '\\'], '', $fileName);
        
        // Remove null bytes
        $fileName = str_replace("\0", '', $fileName);
        
        // Remove control characters
        $fileName = preg_replace('/[\x00-\x1F\x7F]/', '', $fileName);
        
        // Limit length
        $fileName = Str::limit($fileName, 255, '');
        
        return $fileName;
    }

    /**
     * Sanitize SQL input (basic protection)
     */
    public function sanitizeSqlInput(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $input = $this->sanitizeString($input, false);
        
        // Remove common SQL injection patterns
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\balter\b.*\btable\b)/i',
            '/(\bcreate\b.*\btable\b)/i',
            '/(\bexec\b|\bexecute\b)/i',
            '/(\bscript\b)/i',
            '/(\bjavascript\b)/i',
            '/(\bonload\b)/i',
            '/(\bonerror\b)/i',
        ];
        
        foreach ($patterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        return $input;
    }

    /**
     * Sanitize JSON input
     */
    public function sanitizeJson(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $json = $this->sanitizeString($json, false);
        
        // Validate JSON
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // Re-encode to ensure clean JSON
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sanitize phone number
     */
    public function sanitizePhoneNumber(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = $this->sanitizeString($phone, false);
        
        // Remove all non-numeric characters except + at the beginning
        $phone = preg_replace('/[^0-9\+]/', '', $phone);
        
        // Ensure + is only at the beginning
        if (strpos($phone, '+') !== 0 && strpos($phone, '+') !== false) {
            $phone = str_replace('+', '', $phone);
        }
        
        return $phone;
    }

    /**
     * Sanitize textarea content (allows basic HTML)
     */
    public function sanitizeTextarea(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        return $this->sanitizeString($content, true);
    }

    /**
     * Sanitize search query
     */
    public function sanitizeSearchQuery(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $query = $this->sanitizeString($query, false);
        
        // Remove SQL injection patterns
        $query = $this->sanitizeSqlInput($query);
        
        // Limit length
        $query = Str::limit($query, 500, '');
        
        return $query;
    }

    /**
     * Get sanitized request data
     */
    public function sanitizeRequest(array $data, array $rules = []): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $rule = $rules[$key] ?? 'string';
            
            switch ($rule) {
                case 'email':
                    $sanitized[$key] = $this->sanitizeEmail($value);
                    break;
                case 'url':
                    $sanitized[$key] = $this->sanitizeUrl($value);
                    break;
                case 'integer':
                    $sanitized[$key] = $this->sanitizeInteger($value);
                    break;
                case 'float':
                    $sanitized[$key] = $this->sanitizeFloat($value);
                    break;
                case 'boolean':
                    $sanitized[$key] = $this->sanitizeBoolean($value);
                    break;
                case 'filename':
                    $sanitized[$key] = $this->sanitizeFileName($value);
                    break;
                case 'json':
                    $sanitized[$key] = $this->sanitizeJson($value);
                    break;
                case 'phone':
                    $sanitized[$key] = $this->sanitizePhoneNumber($value);
                    break;
                case 'textarea':
                    $sanitized[$key] = $this->sanitizeTextarea($value);
                    break;
                case 'search':
                    $sanitized[$key] = $this->sanitizeSearchQuery($value);
                    break;
                case 'html':
                    $sanitized[$key] = $this->sanitizeString($value, true);
                    break;
                case 'array':
                    $sanitized[$key] = is_array($value) ? $this->sanitizeArray($value) : $value;
                    break;
                default:
                    $sanitized[$key] = $this->sanitizeString($value);
                    break;
            }
        }
        
        return $sanitized;
    }
}
