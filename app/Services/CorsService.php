<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CORS Service
 * 
 * Handles CORS validation with tenant-aware origin checking.
 * Ensures only allowed origins can access the API.
 */
class CorsService
{
    /**
     * Validate if origin is allowed
     * 
     * @param string|null $origin Request origin
     * @param Request $request Full request object for additional context
     * @return bool
     */
    public function isOriginAllowed(?string $origin, Request $request = null): bool
    {
        if (!$origin) {
            return false;
        }

        $config = config('cors');
        $allowedOrigins = $config['allowed_origins'] ?? [];
        $allowedPatterns = $config['allowed_origins_patterns'] ?? [];

        // Check exact match
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Check pattern match
        foreach ($allowedPatterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        // Log unauthorized origin attempts
        Log::warning('CORS: Unauthorized origin attempt', [
            'origin' => $origin,
            'path' => $request?->path(),
            'method' => $request?->method(),
            'ip' => $request?->ip(),
        ]);

        return false;
    }

    /**
     * Get allowed origin for response header
     * 
     * Returns the origin if allowed, or null if not allowed.
     * 
     * @param Request $request
     * @return string|null
     */
    public function getAllowedOrigin(Request $request): ?string
    {
        $origin = $request->header('Origin');
        
        if (!$origin) {
            return null;
        }

        if ($this->isOriginAllowed($origin, $request)) {
            return $origin;
        }

        return null;
    }

    /**
     * Validate tenant-specific origin (if needed)
     * 
     * For multi-tenant setups, you might want to restrict origins per tenant.
     * This is a placeholder for future tenant-aware CORS validation.
     * 
     * @param string $origin
     * @param string|null $tenantId
     * @return bool
     */
    public function isTenantOriginAllowed(string $origin, ?string $tenantId = null): bool
    {
        // For now, use global origin validation
        // In future, can add tenant-specific origin whitelist
        return $this->isOriginAllowed($origin);
    }

    /**
     * Get CORS headers for response
     * 
     * @param Request $request
     * @return array<string, string>
     */
    public function getCorsHeaders(Request $request): array
    {
        $config = config('cors');
        $origin = $this->getAllowedOrigin($request);
        
        $headers = [];

        if ($origin) {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }

        if ($config['supports_credentials'] ?? false) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        $headers['Access-Control-Allow-Methods'] = implode(', ', $config['allowed_methods'] ?? []);
        $headers['Access-Control-Allow-Headers'] = implode(', ', $config['allowed_headers'] ?? []);
        $headers['Access-Control-Expose-Headers'] = implode(', ', $config['exposed_headers'] ?? []);
        $headers['Access-Control-Max-Age'] = (string) ($config['max_age'] ?? 86400);

        return $headers;
    }
}

