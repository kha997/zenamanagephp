<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFeatureFlagEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $flagKey
     * @param  string  $message
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $flagKey, string $message = 'Feature is not enabled')
    {
        if (!config($flagKey, false)) {
            abort(403, $message);
        }

        return $next($request);
    }
}
