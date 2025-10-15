<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SimpleAuthBypass
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Simple bypass for testing - just pass through
        return $next($request);
    }
}
