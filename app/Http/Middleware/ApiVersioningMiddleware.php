<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * ApiVersioningMiddleware
 * 
 * Advanced API versioning middleware with support for:
 * - Header-based versioning (X-API-Version)
 * - URL-based versioning (/api/v1/, /api/v2/)
 * - Query parameter versioning (?version=v1)
 * - Automatic version validation
 * - Backward compatibility support
 * - Version deprecation warnings
 */
class ApiVersioningMiddleware
{
    // Supported API versions
    private const SUPPORTED_VERSIONS = ['v1', 'v2'];
    
    // Current stable version
    private const CURRENT_VERSION = 'v2';
    
    // Deprecated versions (with deprecation dates)
    private const DEPRECATED_VERSIONS = [
        'v1' => '2025-06-01' // v1 will be deprecated on June 1, 2025
    ];
    
    // Version-specific route prefixes
    private const VERSION_ROUTES = [
        'v1' => 'api/v1',
        'v2' => 'api/v2'
    ];
    
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, string $version = null): Response
    {
        try {
            // Get API version from request
            $apiVersion = $this->getApiVersion($request, $version);
            
            // Validate API version
            if (!$this->validateApiVersion($apiVersion)) {
                return $this->createVersionErrorResponse($apiVersion);
            }
            
            // Check for deprecation warnings
            if ($this->isVersionDeprecated($apiVersion)) {
                $this->addDeprecationWarning($request, $apiVersion);
            }
            
            // Set version in request for use in controllers
            $request->merge(['api_version' => $apiVersion]);
            
            // Add version headers to response
            $response = $next($request);
            $this->addVersionHeaders($response, $apiVersion);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('API versioning middleware error', [
                'error' => $e->getMessage(),
                'request_url' => $request->fullUrl(),
                'request_method' => $request->method()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => 'API versioning error',
                    'code' => 'VERSIONING_ERROR'
                ]
            ], 500);
        }
    }
    
    /**
     * Get API version from request
     */
    public function getApiVersion(Request $request, string $version = null): string
    {
        // If version is provided as middleware parameter, use it
        if ($version) {
            return $version;
        }
        
        // Check X-API-Version header
        $headerVersion = $request->header('X-API-Version');
        if ($headerVersion) {
            return $this->normalizeVersion($headerVersion);
        }
        
        // Check Accept header for version
        $acceptHeader = $request->header('Accept');
        if ($acceptHeader && preg_match('/application\/vnd\.api\.v(\d+)/', $acceptHeader, $matches)) {
            return 'v' . $matches[1];
        }
        
        // Check query parameter
        $queryVersion = $request->query('version');
        if ($queryVersion) {
            return $this->normalizeVersion($queryVersion);
        }
        
        // Check URL path for version
        $pathVersion = $this->extractVersionFromPath($request->path());
        if ($pathVersion) {
            return $pathVersion;
        }
        
        // Default to current version
        return self::CURRENT_VERSION;
    }
    
    /**
     * Validate API version
     */
    public function validateApiVersion(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS);
    }
    
    /**
     * Check if version is deprecated
     */
    private function isVersionDeprecated(string $version): bool
    {
        return isset(self::DEPRECATED_VERSIONS[$version]);
    }
    
    /**
     * Add deprecation warning to response
     */
    private function addDeprecationWarning(Request $request, string $version): void
    {
        $deprecationDate = self::DEPRECATED_VERSIONS[$version];
        $warningMessage = "API version {$version} is deprecated and will be removed on {$deprecationDate}. Please upgrade to " . self::CURRENT_VERSION;
        
        Log::warning('Deprecated API version used', [
            'version' => $version,
            'deprecation_date' => $deprecationDate,
            'request_url' => $request->fullUrl(),
            'user_agent' => $request->userAgent()
        ]);
        
        // Store warning in request for later use
        $request->merge(['deprecation_warning' => $warningMessage]);
    }
    
    /**
     * Add version headers to response
     */
    private function addVersionHeaders(Response $response, string $version): void
    {
        $response->headers->set('X-API-Version', $version);
        $response->headers->set('X-API-Current-Version', self::CURRENT_VERSION);
        
        if ($this->isVersionDeprecated($version)) {
            $deprecationDate = self::DEPRECATED_VERSIONS[$version];
            $response->headers->set('X-API-Deprecation-Date', $deprecationDate);
            $response->headers->set('X-API-Deprecation-Warning', "API version {$version} is deprecated");
        }
        
        // Add supported versions header
        $response->headers->set('X-API-Supported-Versions', implode(', ', self::SUPPORTED_VERSIONS));
    }
    
    /**
     * Create version error response
     */
    private function createVersionErrorResponse(string $version): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'message' => "Unsupported API version: {$version}",
                'code' => 'UNSUPPORTED_VERSION',
                'supported_versions' => self::SUPPORTED_VERSIONS,
                'current_version' => self::CURRENT_VERSION
            ]
        ], 400);
    }
    
    /**
     * Normalize version string
     */
    private function normalizeVersion(string $version): string
    {
        $version = strtolower(trim($version));
        
        // Remove 'v' prefix if present and add it back
        if (str_starts_with($version, 'v')) {
            return $version;
        }
        
        return 'v' . $version;
    }
    
    /**
     * Extract version from URL path
     */
    private function extractVersionFromPath(string $path): ?string
    {
        // Match patterns like /api/v1/ or /api/v2/
        if (preg_match('/^api\/v(\d+)\//', $path, $matches)) {
            return 'v' . $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get version-specific route prefix
     */
    public function getVersionRoutePrefix(string $version): string
    {
        return self::VERSION_ROUTES[$version] ?? 'api';
    }
    
    /**
     * Get supported versions
     */
    public function getSupportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }
    
    /**
     * Get current version
     */
    public function getCurrentVersion(): string
    {
        return self::CURRENT_VERSION;
    }
    
    /**
     * Get deprecated versions
     */
    public function getDeprecatedVersions(): array
    {
        return self::DEPRECATED_VERSIONS;
    }
    
    /**
     * Check if version is current
     */
    public function isCurrentVersion(string $version): bool
    {
        return $version === self::CURRENT_VERSION;
    }
    
    /**
     * Get version migration guide URL
     */
    public function getMigrationGuideUrl(string $fromVersion, string $toVersion): string
    {
        return "/docs/api/migration/{$fromVersion}-to-{$toVersion}";
    }
    
    /**
     * Get version changelog URL
     */
    public function getChangelogUrl(string $version): string
    {
        return "/docs/api/changelog/{$version}";
    }
}
