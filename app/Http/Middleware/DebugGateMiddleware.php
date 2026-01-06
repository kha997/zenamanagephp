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
        $debugRoutesEnabled = (bool) env('ZENA_DEBUG_ROUTES');
        $isDebugEnvironment = app()->environment(['local', 'testing']);

        if (! $isDebugEnvironment || ! $debugRoutesEnabled) {
            abort(404);
        }

        return $next($request);
    }
}
