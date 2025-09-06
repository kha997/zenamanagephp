<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * JWT Authentication Middleware
 * 
 * Middleware này sẽ:
 * - Validate JWT token từ Authorization header
 * - Decode và verify token signature
 * - Extract user information và add vào request
 * - Handle token expiration và invalid signatures
 */
class JWTAuthMiddleware
{
    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return $this->unauthorizedResponse('Missing authentication token');
        }
        
        try {
            $payload = $this->decodeToken($token);
            
            // Add decoded user info to request
            $request->merge([
                'jwt_payload' => $payload,
                'auth_user_id' => $payload->user_id,
                'auth_tenant_id' => $payload->tenant_id,
                'auth_system_roles' => $payload->system_roles ?? []
            ]);
            
            return $next($request);
            
        } catch (ExpiredException $e) {
            return $this->unauthorizedResponse('Token has expired');
        } catch (SignatureInvalidException $e) {
            return $this->unauthorizedResponse('Invalid token signature');
        } catch (\Exception $e) {
            Log::error('JWT Authentication Error', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
            return $this->unauthorizedResponse('Invalid authentication token');
        }
    }
    
    /**
     * Extract JWT token from request
     * 
     * @param Request $request
     * @return string|null
     */
    private function extractToken(Request $request): ?string
    {
        // Try Bearer token first
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            return $bearerToken;
        }
        
        // Fallback to Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        
        return null;
    }
    
    /**
     * Decode and validate JWT token
     * 
     * @param string $token
     * @return object
     * @throws \Exception
     */
    private function decodeToken(string $token): object
    {
        $secretKey = config('app.jwt_secret', env('JWT_SECRET'));
        
        if (!$secretKey) {
            throw new \Exception('JWT secret key not configured');
        }
        
        return JWT::decode($token, new Key($secretKey, 'HS256'));
    }
    
    /**
     * Return unauthorized response
     * 
     * @param string $message
     * @return JsonResponse
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 401);
    }
}