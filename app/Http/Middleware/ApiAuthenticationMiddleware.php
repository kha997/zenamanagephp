<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Authentication Middleware
 * 
 * Handles authentication for API endpoints with multiple authentication methods:
 * 1. Sanctum token authentication
 * 2. Session-based authentication (for SPA)
 * 3. API key authentication (for external services)
 */
class ApiAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Try different authentication methods
            $user = $this->authenticateUser($request);
            
            if (!$user) {
                return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
            }
            
            // Set the authenticated user
            Auth::setUser($user);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
            
            // Add user context to request
            $request->attributes->set('auth_user', $user);
            $request->attributes->set('tenant_id', $user->tenant_id);
            
            // Log successful authentication
            Log::info('User authenticated via API', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id ? substr($user->tenant_id, 0, 8) . '...' : null,
                'method' => $this->getAuthMethod($request),
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent(), 0, 50) // Truncate for privacy
            ]);
            
            return $next($request);
            
        } catch (\Exception $e) {
            Log::error('API Authentication failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return ApiResponse::error('Authentication failed', 'AUTH_FAILED', 401);
        }
    }
    
    /**
     * Authenticate user using multiple methods
     */
    private function authenticateUser(Request $request): ?\App\Models\User
    {
        // Method 1: Sanctum token authentication
        if ($request->bearerToken()) {
            return $this->authenticateViaSanctum($request);
        }
        
        // Method 2: Session-based authentication (for SPA)
        if ($request->hasSession() && Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }
        
        // Method 3: API key authentication
        if ($request->header('X-API-Key')) {
            return $this->authenticateViaApiKey($request);
        }
        
        // Method 4: Check if already authenticated via Sanctum
        if (Auth::guard('sanctum')->check()) {
            return Auth::guard('sanctum')->user();
        }
        
        return null;
    }
    
    /**
     * Authenticate via Sanctum token
     */
    private function authenticateViaSanctum(Request $request): ?\App\Models\User
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }
        
        // Find the token in database
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        
        if (!$personalAccessToken) {
            return null;
        }
        
        // Check if token is expired
        if ($personalAccessToken->expires_at && $personalAccessToken->expires_at->isPast()) {
            return null;
        }
        
        // Get the user
        $user = $personalAccessToken->tokenable;
        
        if (!$user || !$user->is_active) {
            return null;
        }
        
        // Update last activity (debounced)
        $this->updateLastActivity($personalAccessToken, 'last_used_at');
        
        return $user;
    }
    
    
    /**
     * Authenticate via API key
     */
    private function authenticateViaApiKey(Request $request): ?\App\Models\User
    {
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey) {
            return null;
        }
        
        // Find user by API key with tenant scoping
        $user = \App\Models\User::where('api_key', $apiKey)
            ->where('is_active', true)
            ->whereNotNull('tenant_id') // Ensure user has tenant
            ->first();
        
        if (!$user) {
            return null;
        }
        
        // Update last activity (debounced)
        $this->updateLastActivity($user, 'last_activity_at');
        
        return $user;
    }
    
    /**
     * Get authentication method used
     */
    private function getAuthMethod(Request $request): string
    {
        if ($request->bearerToken()) {
            return 'sanctum_token';
        }
        
        if ($request->hasSession() && Auth::guard('web')->check()) {
            return 'session';
        }
        
        if ($request->header('X-API-Key')) {
            return 'api_key';
        }
        
        return Auth::guard('sanctum')->check() ? 'sanctum_already_authenticated' : 'unknown';
    }
    
    /**
     * Update last activity with debouncing (only if older than 5 minutes)
     */
    private function updateLastActivity($model, string $field): void
    {
        $now = now();
        $lastUpdate = $model->{$field};
        
        // Only update if last update was more than 5 minutes ago
        if (!$lastUpdate || $lastUpdate->diffInMinutes($now) >= 5) {
            if ($model instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $model->forceFill([$field => $now])->save();
            } else {
                $model->update([$field => $now]);
            }
        }
    }
}
