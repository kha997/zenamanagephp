<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DebugGateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow debug routes only in explicitly safe environments
        if (!app()->environment(['local', 'testing', 'development'])) {
            abort(404, 'Debug routes not available in this environment');
        }

        return $next($request);
    }
}
