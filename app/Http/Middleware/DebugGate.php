<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugGate
{
    /**
     * Handle an incoming request.
     * Only allow debug routes in non-production environments or from allowed IPs
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if we're in production
        if (app()->environment('production')) {
            // In production, only allow from specific IPs
            $allowedIPs = $this->getAllowedIPs();
            $clientIP = $request->ip();
            
            if (!in_array($clientIP, $allowedIPs)) {
                Log::warning('Debug route access denied', [
                    'ip' => $clientIP,
                    'url' => $request->url(),
                    'user_agent' => $request->userAgent()
                ]);
                
                return response()->json([
                    'error' => 'Debug routes not available in production',
                    'message' => 'This endpoint is only available in development environments'
                ], 403);
            }
        }
        
        // Log debug route access
        Log::info('Debug route accessed', [
            'ip' => $request->ip(),
            'url' => $request->url(),
            'user_agent' => $request->userAgent(),
            'environment' => app()->environment()
        ]);
        
        return $next($request);
    }
    
    /**
     * Get allowed IPs from environment or config
     */
    private function getAllowedIPs(): array
    {
        $allowedIPs = config('app.debug_allowed_ips', []);
        
        // Add localhost IPs
        $allowedIPs = array_merge($allowedIPs, [
            '127.0.0.1',
            '::1',
            'localhost'
        ]);
        
        // Add IPs from environment variable
        if ($envIPs = env('DEBUG_ALLOWED_IPS')) {
            $envIPs = explode(',', $envIPs);
            $allowedIPs = array_merge($allowedIPs, $envIPs);
        }
        
        return array_unique(array_filter($allowedIPs));
    }
}