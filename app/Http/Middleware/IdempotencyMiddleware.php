<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * IdempotencyMiddleware
 * 
 * Ensures critical POST operations are idempotent by checking idempotency_key.
 * Uses database storage for persistence across requests and cache for performance.
 * 
 * Critical operations that require idempotency:
 * - User invitations
 * - Change requests
 * - Payment processing
 * - Bulk operations
 * - Project/Task creation
 * 
 * Usage: Add 'idempotency' middleware to routes that need idempotency protection.
 */
class IdempotencyMiddleware
{
    /**
     * TTL for idempotency keys (24 hours as per OpenAPI spec)
     */
    private const IDEMPOTENCY_TTL = 86400; // 24 hours in seconds
    
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $mode  'required' or 'optional' (default: 'optional')
     */
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        // Only apply to POST, PUT, PATCH requests
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }
        
        // Get idempotency key from request
        $idempotencyKey = $request->header('Idempotency-Key') 
            ?? $request->input('idempotency_key')
            ?? null;
        
        if (!$idempotencyKey) {
            // If mode is 'required', always require idempotency key
            if ($mode === 'required') {
                return response()->json([
                    'ok' => false,
                    'code' => 'IDEMPOTENCY_KEY_REQUIRED',
                    'message' => 'Idempotency-Key header is required for this operation',
                    'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
                ], 400);
            }
            
            // For critical operations, require idempotency key even in optional mode
            if ($this->isCriticalOperation($request)) {
                return response()->json([
                    'ok' => false,
                    'code' => 'IDEMPOTENCY_KEY_REQUIRED',
                    'message' => 'Idempotency-Key header is required for this operation',
                    'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
                ], 400);
            }
            
            // For non-critical operations in optional mode, allow without key
            return $next($request);
        }
        
        $tenantId = Auth::user()?->tenant_id;
        $userId = Auth::id();
        $route = $request->path();
        $method = $request->method();
        
        // Hash request body to detect duplicate content
        $requestBody = $request->all();
        $bodyHash = hash('sha256', json_encode($requestBody, JSON_UNESCAPED_SLASHES));
        
        // Check cache first for performance
        $cacheKey = "idempotency:{$idempotencyKey}";
        $cachedResponse = Cache::get($cacheKey);
        
        if ($cachedResponse) {
            Log::info('Idempotent request detected (cache)', [
                'idempotency_key' => $idempotencyKey,
                'route' => $route,
                'method' => $method,
            ]);
            
            return response()->json($cachedResponse['data'], $cachedResponse['status'])
                ->header('X-Idempotent-Replayed', 'true')
                ->header('X-Idempotency-Cache', 'hit');
        }
        
        // Check database
        try {
            $keyRecord = IdempotencyKey::where('idempotency_key', $idempotencyKey)->first();
            
            if ($keyRecord && $keyRecord->isProcessed()) {
                // Check if request body matches (prevent replay with different content)
                $storedBodyHash = hash('sha256', json_encode($keyRecord->request_body ?? [], JSON_UNESCAPED_SLASHES));
                if ($bodyHash !== $storedBodyHash) {
                    Log::warning('Idempotency key reused with different request body', [
                        'idempotency_key' => $idempotencyKey,
                        'route' => $route,
                        'stored_hash' => $storedBodyHash,
                        'current_hash' => $bodyHash,
                    ]);
                    
                    return response()->json([
                        'ok' => false,
                        'code' => 'IDEMPOTENCY_KEY_CONFLICT',
                        'message' => 'Idempotency key already used with different request content',
                        'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
                    ], 409);
                }
                
                $cachedResponse = $keyRecord->getCachedResponse();
                
                // Cache it for future requests
                Cache::put($cacheKey, $cachedResponse, self::IDEMPOTENCY_TTL);
                
                Log::info('Idempotent request detected (database)', [
                    'idempotency_key' => $idempotencyKey,
                    'route' => $route,
                    'method' => $method,
                ]);
                
                return response()->json($cachedResponse['data'], $cachedResponse['status'])
                    ->header('X-Idempotent-Replayed', 'true')
                    ->header('X-Idempotency-Cache', 'hit');
            }
            
            // Create or get key record
            if (!$keyRecord) {
                $keyRecord = IdempotencyKey::findOrCreate(
                    $idempotencyKey,
                    $route,
                    $method,
                    $tenantId,
                    $userId,
                    $request->all()
                );
            }
        } catch (\Exception $e) {
            // If DB fails, fall back to cache-only mode
            Log::warning('Idempotency DB check failed, using cache only', [
                'error' => $e->getMessage(),
                'idempotency_key' => $idempotencyKey,
            ]);
        }
        
        // Process request
        $response = $next($request);
        
        // Store successful responses (2xx status codes)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $responseData = $response->getData(true);
            
            // Cache for quick access
            Cache::put($cacheKey, [
                'data' => $responseData,
                'status' => $response->getStatusCode(),
            ], self::IDEMPOTENCY_TTL);
            
            // Store in database for persistence
            try {
                if (isset($keyRecord)) {
                    $keyRecord->markAsProcessed($responseData, $response->getStatusCode());
                } else {
                    IdempotencyKey::findOrCreate(
                        $idempotencyKey,
                        $route,
                        $method,
                        $tenantId,
                        $userId,
                        $request->all()
                    )->markAsProcessed($responseData, $response->getStatusCode());
                }
            } catch (\Exception $e) {
                Log::warning('Failed to store idempotency key in DB', [
                    'error' => $e->getMessage(),
                    'idempotency_key' => $idempotencyKey,
                ]);
            }
        }
        
        return $response;
    }
    
    /**
     * Check if this is a critical operation that requires idempotency
     * 
     * @param Request $request
     * @return bool
     */
    private function isCriticalOperation(Request $request): bool
    {
        $path = $request->path();
        
        // Critical operations that MUST have idempotency key
        $criticalPaths = [
            'api/v1/admin/invitations',
            'api/v1/app/change-requests',
            'api/v1/app/payments',
            'api/v1/app/bulk',
            'api/v1/app/projects', // Project creation
            'api/v1/app/tasks', // Task creation and move
            'api/v1/app/tasks/', // Task operations (includes move)
            'api/v1/app/quotes', // Quote creation
            'api/v1/app/documents', // Document creation
        ];
        
        foreach ($criticalPaths as $criticalPath) {
            if (str_starts_with($path, $criticalPath)) {
                return true;
            }
        }
        
        return false;
    }
}

