<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SimpleTokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token required'
            ], 401);
        }
        
        try {
            $decoded = json_decode(base64_decode($token), true);
            
            if (!$decoded || !isset($decoded['user_id']) || !isset($decoded['expires'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token format'
                ], 401);
            }
            
            if ($decoded['expires'] < time()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token expired'
                ], 401);
            }
            
            // Set user data in request
            $request->merge(['auth_user' => $decoded]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token validation failed'
            ], 401);
        }
        
        return $next($request);
    }
}
