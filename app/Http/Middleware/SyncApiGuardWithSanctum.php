<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncApiGuardWithSanctum
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user('api') && $request->user('sanctum')) {
            Auth::guard('api')->setUser($request->user('sanctum'));
        }

        return $next($request);
    }
}
