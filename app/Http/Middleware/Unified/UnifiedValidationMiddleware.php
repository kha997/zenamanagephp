<?php declare(strict_types=1);

namespace App\Http\Middleware\Unified;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Support\ApiResponse;

/**
 * Unified Validation Middleware
 * 
 * Consolidates all validation functionality into a single middleware
 * Replaces: EnhancedValidationMiddleware, InputValidationMiddleware,
 *           InputSanitizationMiddleware
 */
class UnifiedValidationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize input data
        $this->sanitizeInput($request);
        
        // Validate request structure
        $this->validateRequestStructure($request);
        
        // Check for required fields
        $this->validateRequiredFields($request);
        
        // Log validation events
        $this->logValidationEvents($request);
        
        return $next($request);
    }

    /**
     * Sanitize input data
     */
    protected function sanitizeInput(Request $request): void
    {
        $input = $request->all();
        $sanitized = $this->recursiveSanitize($input);
        
        // Replace request data with sanitized data
        $request->replace($sanitized);
    }

    /**
     * Recursively sanitize nested arrays
     */
    protected function recursiveSanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->sanitizeString($value);
            }
        }
        
        return $data;
    }

    /**
     * Sanitize string value
     */
    protected function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Trim whitespace
        $value = trim($value);
        
        // Remove excessive whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        
        // HTML encode special characters
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }

    /**
     * Validate request structure
     */
    protected function validateRequestStructure(Request $request): void
    {
        $route = $request->route();
        if (!$route) {
            return;
        }
        
        $routeName = $route->getName() ?? 'unnamed';
        $method = $request->method();
        
        // Validate API requests
        if ($request->is('api/*')) {
            $this->validateApiRequest($request, $routeName, $method);
        }
        
        // Validate web requests
        if ($request->is('app/*') || $request->is('admin/*')) {
            $this->validateWebRequest($request, $routeName, $method);
        }
    }

    /**
     * Validate API request structure
     */
    protected function validateApiRequest(Request $request, string $routeName, string $method): void
    {
        // Check for required headers
        $requiredHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        
        foreach ($requiredHeaders as $header => $expectedValue) {
            if ($request->hasHeader($header)) {
                $actualValue = $request->header($header);
                if (!str_contains($actualValue, $expectedValue)) {
                    Log::warning('Invalid API header', [
                        'header' => $header,
                        'expected' => $expectedValue,
                        'actual' => $actualValue,
                        'route' => $routeName,
                        'method' => $method,
                        'request_id' => $request->header('X-Request-Id')
                    ]);
                }
            }
        }
        
        // Validate JSON for POST/PUT/PATCH requests
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $this->validateJsonRequest($request);
        }
    }

    /**
     * Validate web request structure
     */
    protected function validateWebRequest(Request $request, string $routeName, string $method): void
    {
        // Check for CSRF token on state-changing requests
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (!$request->hasHeader('X-CSRF-TOKEN') && !$request->has('_token')) {
                Log::warning('Missing CSRF token', [
                    'route' => $routeName,
                    'method' => $method,
                    'ip' => $request->ip(),
                    'user_id' => auth()->id(),
                    'request_id' => $request->header('X-Request-Id')
                ]);
            }
        }
    }

    /**
     * Validate JSON request
     */
    protected function validateJsonRequest(Request $request): void
    {
        $content = $request->getContent();
        
        if (empty($content)) {
            return;
        }
        
        // Check if content is valid JSON
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Invalid JSON in request', [
                'json_error' => json_last_error_msg(),
                'content_length' => strlen($content),
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'request_id' => $request->header('X-Request-Id')
            ]);
            
            abort(400, 'Invalid JSON format');
        }
        
        // Check for excessive nesting
        if ($this->getArrayDepth($decoded) > 10) {
            Log::warning('Excessive JSON nesting', [
                'depth' => $this->getArrayDepth($decoded),
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'request_id' => $request->header('X-Request-Id')
            ]);
            
            abort(400, 'JSON structure too complex');
        }
    }

    /**
     * Get array depth
     */
    protected function getArrayDepth(array $array): int
    {
        $maxDepth = 1;
        
        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;
                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                }
            }
        }
        
        return $maxDepth;
    }

    /**
     * Validate required fields based on route
     */
    protected function validateRequiredFields(Request $request): void
    {
        $route = $request->route();
        if (!$route) {
            return;
        }
        
        $routeName = $route->getName() ?? 'unnamed';
        $method = $request->method();
        
        // Define required fields for different routes
        $requiredFields = $this->getRequiredFields($routeName, $method);
        
        if (empty($requiredFields)) {
            return;
        }
        
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!$request->has($field) || empty($request->input($field))) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            Log::warning('Missing required fields', [
                'missing_fields' => $missingFields,
                'route' => $routeName,
                'method' => $method,
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-Id')
            ]);
            
            abort(422, 'Missing required fields: ' . implode(', ', $missingFields));
        }
    }

    /**
     * Get required fields for route and method
     */
    protected function getRequiredFields(string $routeName, string $method): array
    {
        $requiredFields = [];
        
        // User management routes
        if (str_contains($routeName, 'user')) {
            if ($method === 'POST') {
                $requiredFields = ['name', 'email', 'password'];
            } elseif ($method === 'PUT') {
                $requiredFields = ['name', 'email'];
            }
        }
        
        // Project management routes
        if (str_contains($routeName, 'project')) {
            if ($method === 'POST') {
                $requiredFields = ['name', 'description', 'start_date', 'end_date'];
            } elseif ($method === 'PUT') {
                $requiredFields = ['name', 'description'];
            }
        }
        
        // Authentication routes
        if (str_contains($routeName, 'auth') || str_contains($routeName, 'login')) {
            if ($method === 'POST') {
                $requiredFields = ['email', 'password'];
            }
        }
        
        return $requiredFields;
    }

    /**
     * Log validation events
     */
    protected function logValidationEvents(Request $request): void
    {
        $validationEvents = [
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'content_length' => strlen($request->getContent()),
            'has_files' => $request->hasFile('file'),
            'request_id' => $request->header('X-Request-Id')
        ];
        
        Log::info('Request validation completed', $validationEvents);
    }
}
